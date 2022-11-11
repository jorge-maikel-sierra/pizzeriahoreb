<?php

namespace CreativeMail\Managers;

use CreativeMail\CreativeMail;
use CreativeMail\Helpers\EnvironmentHelper;
use CreativeMail\Helpers\OptionsHelper;
use CreativeMail\Helpers\SsoHelper;
use CreativeMail\Models\Response;
use CreativeMail\Modules\DashboardWidgetModule;
use CreativeMail\Modules\FeedbackNoticeModule;
use Exception;

/**
 * The AdminManager will manage the admin section of the plugin.
 *
 * @ignore
 */
final class AdminManager {

	/**
	 * Holds the instance of the AdminManager class.
	 *
	 * @var string
	 */
	protected $instance_name;

	/**
	 * Holds the instance UUID.
	 *
	 * @var string
	 */
	protected $instance_uuid;

	/**
	 * Holds the Instance Handshake Token.
	 *
	 * @var string
	 */
	protected $instance_handshake_token;

	/**
	 * Holds the Instance Key.
	 *
	 * @var int|null
	 */
	protected $instance_id;

	/**
	 * Holds the Instance URL.
	 *
	 * @var string
	 */
	protected $instance_url;

	/**
	 * Holds the Instance Callback URL.
	 *
	 * @var string
	 */
	protected $instance_callback_url;

	/**
	 * Holds the Dashboard URL.
	 *
	 * @var string
	 */
	protected $dashboard_url;

	const ADMIN_NOTICES_HOOK         = 'admin_notices';
	const ADMIN_INIT_HOOK            = 'admin_init';
	const ADMIN_MENU_HOOK            = 'admin_menu';
	const ADMIN_ENQUEUE_SCRIPTS_HOOK = 'admin_enqueue_scripts';

	const ADMIN_AJAX_NONCE = 'ajax-nonce';
	const ADMIN_NONCE      = 'nonce';

	const ADMIN_WOOCOMMERCE    = 'woocommerce';
	const DOMAIN_CE4WP         = 'creative-mail-by-constant-contact';
	const ADMIN_CE4WP_DATA_VAR = 'ce4wp_data';

	/**
	 * AdminManager constructor.
	 */
	public function __construct() {
		$this->instance_name            = rawurlencode(get_bloginfo('name'));
		$this->instance_handshake_token = OptionsHelper::get_handshake_token();
		$this->instance_uuid            = OptionsHelper::get_instance_uuid();
		$this->instance_id              = OptionsHelper::get_instance_id();
		$this->instance_url             = rawurlencode(get_bloginfo('wpurl'));
		$this->instance_callback_url    = rawurlencode(get_bloginfo('wpurl') . '?rest_route=/creativemail/v1/callback');
		$this->dashboard_url            = EnvironmentHelper::get_app_url() . 'marketing/dashboard?wp_site_name=' . $this->instance_name
								. '&wp_site_uuid=' . $this->instance_uuid
								. '&wp_callback_url=' . $this->instance_callback_url
								. '&wp_instance_url=' . $this->instance_url
								. '&wp_version=' . get_bloginfo('version')
								. '&plugin_version=' . CE4WP_PLUGIN_VERSION;
	}

	/**
	 * Will register all the hooks for the admin portion of the plugin.
	 *
	 * @return void
	 */
	public function add_hooks(): void {
		add_action(self::ADMIN_MENU_HOOK, array( $this, 'build_menu' ));
		add_action(self::ADMIN_ENQUEUE_SCRIPTS_HOOK, array( $this, 'add_assets' ));
		add_action(self::ADMIN_NOTICES_HOOK, array( $this, 'add_admin_notice_permalink' ));
		add_action(self::ADMIN_NOTICES_HOOK, array( $this, 'add_admin_notice_review' ));
		add_action(self::ADMIN_NOTICES_HOOK, array( $this, 'add_admin_get_started_banner' ));
		add_action(self::ADMIN_NOTICES_HOOK, array( $this, 'add_admin_feedback_notice' ));
		add_action(self::ADMIN_INIT_HOOK, array( $this, 'activation_redirect' ));
		add_action(self::ADMIN_INIT_HOOK, array( $this, 'ignore_review_notice' ));

		add_filter('admin_footer_text', array( $this, 'admin_footer_text' ), 1 );
		add_action('wp_ajax_woocommerce_ce4wp_rated', array( $this, 'mark_as_rated' ) );
		add_action('wp_dashboard_setup', array( $this, 'add_admin_dashboard_widget' ) );

		// Sso request.
		add_action('wp_ajax_ce4wp_request_sso', array( $this, 'request_single_sign_on_url' ) );

		// Deactivation footer.
		add_action(self::ADMIN_ENQUEUE_SCRIPTS_HOOK, array( $this, 'deactivation_modal_js' ), 20);
		add_action(self::ADMIN_ENQUEUE_SCRIPTS_HOOK, array( $this, 'deactivation_modal_css' ));
		add_action('admin_footer', array( $this, 'show_deactivation_modal' ));
		add_action('wp_ajax_ce4wp_deactivate_survey', array( $this, 'deactivate_survey_post' ) );
	}

	/**
	 * Check for the nonce.
	 *
	 * @return void
	 */
	private function check_nonce(): void {
		$nonce = '';
		if ( isset( $_POST[ self::ADMIN_NONCE ] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::ADMIN_NONCE ] ) ), self::ADMIN_AJAX_NONCE ) ) {
			$nonce = sanitize_text_field( wp_unslash( $_POST[ self::ADMIN_NONCE ] ) );
		}
		if ( ! wp_verify_nonce( $nonce, self::ADMIN_AJAX_NONCE ) ) {
			$response      = new Response();
			$response->url = admin_url('admin.php?page=creativemail');

			wp_send_json_success($response);
		}
	}

	/**
	 * Creates the nonce for the Admin Manager.
	 *
	 * @return false|string
	 */
	private function create_nonce(): string {
		return wp_create_nonce(self::ADMIN_AJAX_NONCE);
	}

	/**
	 * Sends the SSO URL for internal purposes.
	 *
	 * @param string|null               $linkReference The link reference.
	 * @param array<string,string>|null $linkParameters The link parameters.
	 *
	 * @return string
	 */
	public function request_single_sign_on_url_internal(
		?string $linkReference = null,
		?array $linkParameters = null
	): string {
		$sso = $this->get_sso_link($linkReference, $linkParameters);

		if ( is_null($sso) ) {
			$current_user  = wp_get_current_user();
			$redirectUrl   = EnvironmentHelper::get_app_gateway_url('wordpress/v1.0/instances/open?clearSession=true&redirectUrl=');
			$onboardingUrl = EnvironmentHelper::get_app_url() . 'marketing/onboarding/signup?wp_site_name=' . $this->instance_name
				. '&wp_site_uuid=' . $this->instance_uuid
				. '&wp_handshake=' . $this->instance_handshake_token
				. '&wp_callback_url=' . $this->instance_callback_url
				. '&wp_instance_url=' . $this->instance_url
				. '&wp_version=' . get_bloginfo('version')
				. '&plugin_version=' . CE4WP_PLUGIN_VERSION
				. '&first_name=' . urlencode( $current_user->user_firstname )
				. '&last_name=' . urlencode( $current_user->user_lastname )
				. '&email=' . urlencode( $current_user->user_email );
			$referred_by   = OptionsHelper::get_referred_by();

			if ( ! empty($referred_by) ) {
				$utm_campaign = '';

				if ( is_string($referred_by) ) {
					$utm_campaign = str_replace(';', '|', $referred_by);
				}

				$onboardingUrl .= '&utm_source=wordpress&utm_medium=plugin&utm_campaign=' . $utm_campaign;
			}

			return $redirectUrl . rawurlencode($onboardingUrl);
		}

		return $sso;
	}

	/**
	 * Sends the SSO URL for external purposes.
	 *
	 * @return void
	 */
	public function request_single_sign_on_url() {
		// Check for nonce security.
		$this->check_nonce();

		$linkReference  = array_key_exists('link_reference', $_POST) ? sanitize_text_field( wp_unslash( $_POST['link_reference'] ) ) : null;
		$linkParameters = array_key_exists('link_parameters', $_POST) ? array_map( 'sanitize_text_field', wp_unslash( $_POST['link_parameters'] ) ) : null;
		$response       = new Response();
		$response->url  = $this->request_single_sign_on_url_internal($linkReference, $linkParameters);

		wp_send_json_success($response);
	}

	/**
	 * Deactivates the survey.
	 *
	 * @return void
	 */
	public function deactivate_survey_post(): void {
		// Check for nonce security.
		$this->check_nonce();

		$instance_id          = OptionsHelper::get_instance_id();
		$instance_api_key     = OptionsHelper::get_instance_api_key();
		$connected_account_id = OptionsHelper::get_connected_account_id();

		if ( isset($_POST['data']) ) {
			parse_str(sanitize_text_field(wp_unslash($_POST['data'])), $post_data);
		}

		$survey_value = $post_data['ce4wp_deactivation_option'];

		if ( is_null($survey_value) ) {
			wp_send_json_success();
		}

		$arguments = array(
			'method'  => 'POST',
			'headers' => array(
				'x-api-key'    => $instance_api_key,
				'x-account-id' => $connected_account_id,
				'content-type' => 'application/json',
			),
			'body'    => wp_json_encode(
				array(
					'instance_id' => $instance_id,
					'survey_id'   => 1,
					'value'       => $survey_value,
					'message'     => $post_data['other'],
				)
			),
		);

		wp_remote_post(EnvironmentHelper::get_app_gateway_url('wordpress/v1.0/survey'), $arguments);
		wp_send_json_success();
	}

	/**
	 * Verifies if it can show the deactivation modal when required.
	 *
	 * @return bool
	 */
	private function should_show_deactivation_modal(): bool {
		if ( ! function_exists('get_current_screen') ) {
			return false;
		}
		$screen = get_current_screen();
		if ( is_null($screen) ) {
			return false;
		}
		return ( in_array($screen->id, array( 'plugins', 'plugins-network' ), true) );
	}

	/**
	 * Add the deactivation modal JS if not added before.
	 *
	 * @return void
	 */
	public function deactivation_modal_js(): void {
		if ( ! $this->should_show_deactivation_modal() ) {
			return;
		}

		wp_enqueue_script('ce4wp_deactivate_survey', CE4WP_PLUGIN_URL . 'assets/js/deactivation.js', array(), CE4WP_PLUGIN_VERSION, true);
		wp_localize_script('ce4wp_deactivate_survey', self::ADMIN_CE4WP_DATA_VAR, array(
			'url'   => admin_url('admin-ajax.php'),
			'nonce' => $this->create_nonce(),
		));
	}

	/**
	 * Returns the deactivation modal CSS.
	 *
	 * @return void
	 */
	public function deactivation_modal_css(): void {
		if ( ! $this->should_show_deactivation_modal() ) {
			return;
		}

		wp_enqueue_style('ce4wp_deactivate_survey', CE4WP_PLUGIN_URL . 'assets/css/deactivation.css', array(), CE4WP_PLUGIN_VERSION, '');
	}

	/**
	 * Returns the deactivation modal HTML.
	 *
	 * @return void
	 */
	public function show_deactivation_modal(): void {
		if ( ! $this->should_show_deactivation_modal() ) {
			return;
		}

		printf('<div class="ce4wp-deactivate-survey-modal" id="ce4wp-deactivate-survey">
          <div class="ce4wp-deactivate-survey-wrap">
            <div class="ce4wp-deactivate-survey">
                <h2>%s</h2>
                <form method="post" id="ce4wp-deactivate-survey-form">
                    <fieldset>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="0"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="1"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="2"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="3"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="4"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="5"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="6"> %s</span>
                    <span><input type="radio" name="ce4wp_deactivation_option" value="7"> %s: <input type="text" name="other" /></span>
                    <br>
                    <span><input type="submit" class="button button-primary" value="Submit"></span>
                    </fieldset>
                </form>
                <p id="ce4wp-deactivate-survey-form-success">%s</p>
                <a class="button" id="ce4wp-deactivate-survey-close">%s</a>
            </div>
          </div>
        </div>',
			esc_html__('Sadness... why leave so soon?', self::DOMAIN_CE4WP),
			esc_html__('I’m not sending email campaigns right now', self::DOMAIN_CE4WP),
			esc_html__('It didn’t have the features I want', self::DOMAIN_CE4WP),
			esc_html__('I didn’t like the email editor', self::DOMAIN_CE4WP),
			esc_html__('It was too confusing', self::DOMAIN_CE4WP),
			esc_html__('There were technical issues', self::DOMAIN_CE4WP),
			esc_html__('I don’t have enough email contacts', self::DOMAIN_CE4WP),
			esc_html__('It’s a temporary deactivation', self::DOMAIN_CE4WP),
			esc_html__('Other', self::DOMAIN_CE4WP),
			esc_html__('Thank you', self::DOMAIN_CE4WP),
			esc_html__('Close this window and deactivate Creative Mail', self::DOMAIN_CE4WP)
		);
	}

	/**
	 * Adds the admin notice review.
	 *
	 * @return false|void
	 */
	public function add_admin_notice_review() {
		$install_date = get_option('ce4wp_install_date');

		if ( ! $install_date ) {
			return false;
		}

		$install_date = date_create($install_date);
		$date_now     = date_create(gmdate('Y-m-d G:i:s'));
		// @phpstan-ignore-next-line
		$date_diff = date_diff($install_date, $date_now);

		if ( $date_diff->format('%d') < 7 ) {
			return false;
		}

		if ( ! get_option('ce4wp_ignore_review_notice') ) {
			include_once CE4WP_PLUGIN_DIR . 'src/views/admin-feedback-notice/after-week-notice.php';
		}
	}

	/**
	 * Updates the value that checks the review notice.
	 *
	 * @return void
	 */
	public function ignore_review_notice(): void {
		if ( isset($_GET['ce4wp-ignore-notice']) && '0' == $_GET['ce4wp-ignore-notice'] ) {
			update_option('ce4wp_ignore_review_notice', 'true');
		}
	}

	/**
	 * Marks the plugin as rated if clicked the footer rating text.
	 *
	 * @return void
	 */
	public function mark_as_rated(): void {
		update_option('ce4wp_admin_footer_text_rated', 1);
		wp_send_json_success();
	}

	/**
	 * Changes the admin footer text on admin pages.
	 *
	 * @param string $footer_text The existing footer text.
	 *
	 * @return string
	 */
	public function admin_footer_text( string $footer_text ): string {
		if ( $this->is_cm_screen_and_show_footer() ) {
			$footer_text = sprintf(
				// translators: text.
				esc_html__('If you like %1$s please leave us a %2$s rating. A huge thanks in advance!', self::DOMAIN_CE4WP),
				sprintf('<strong>%s</strong>', esc_html__('Creative Mail', self::DOMAIN_CE4WP)),
				'<a href="https://wordpress.org/plugins/creative-mail-by-constant-contact/#reviews?rate=5#new-post" target="_blank" class="ce4wp-rating-link" data-rated="' . esc_attr__('Thank You', self::DOMAIN_CE4WP) . '">&#9733;&#9733;&#9733;&#9733;&#9733;</a>'
			);
		}

		return $footer_text;
	}

	/**
	 * Checks if the current screen is a Creative Mail screen and if the footer should be shown.
	 *
	 * @return bool
	 */
	private function is_cm_screen_and_show_footer(): bool {
		$screen = get_current_screen();

		if ( ! empty($screen)
			&& ( 'toplevel_page_creativemail' === $screen->id || 'creative-mail_page_creativemail_settings' === $screen->id )
			&& ! get_option('ce4wp_admin_footer_text_rated')
		) {
			return true;
		}
		return false;
	}

	/**
	 * Call for the activation redirect.
	 *
	 * @return void
	 */
	public function activation_redirect(): void {
		if ( intval(get_option('ce4wp_activation_redirect', false)) === wp_get_current_user()->ID ) {
			// Make sure we don't redirect again after this one.
			delete_option('ce4wp_activation_redirect');

			// Don't do the redirect while activating the plugin through the rest request.
			if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
				return;
			}

			// The woocommerce onboarding wizard will have a profile.
			$onboarding_profile = get_option('woocommerce_onboarding_profile');
			// If the onboarding profile has business extensions.
			if ( is_array($onboarding_profile) && array_key_exists('business_extensions', $onboarding_profile) ) {
				// If the business extensions contains our plugin, we just skip this.
				if ( is_array($onboarding_profile['business_extensions']) && in_array('creative-mail-by-constant-contact', $onboarding_profile['business_extensions'], true) ) {
					return;
				}
			}
			// Only do this for single site installs.
			if ( isset( $_GET['activate-multi'] ) || is_network_admin() ) {
				return;
			}

			wp_safe_redirect(admin_url('admin.php?page=creativemail'));
			exit;
		}
	}

	/**
	 * Add the required assets.
	 *
	 * @return void
	 */
	public function add_assets(): void {
		wp_register_style('ce4wp_admin', CE4WP_PLUGIN_URL . 'assets/css/admin.css', array(), CE4WP_PLUGIN_VERSION);
		wp_enqueue_style('ce4wp_admin');
		wp_enqueue_style('ce4wp-font-poppins', 'https://fonts.googleapis.com/css?family=Poppins:400,500', array(), CE4WP_PLUGIN_VERSION);
		wp_enqueue_script('wp-api');

		$this->enqueue_dashboard_js();

		if ( $this->is_cm_screen_and_show_footer() ) {
			wp_enqueue_script('ce4wp_admin_footer_rating', CE4WP_PLUGIN_URL . 'assets/js/footer_rating.js', array( 'wp-api' ), CE4WP_PLUGIN_VERSION, true);
		}
	}

	/**
	 * Will build the menu for WP-Admin.
	 *
	 * @return void
	 */
	public function build_menu() {
		$hasConnectedAccount = OptionsHelper::get_instance_id() !== null;
		// Did the user complete the entire setup?
		$main_action = $hasConnectedAccount
			? array( $this, 'show_dashboard' )
			: array( $this, 'show_setup' );

		// Create the root menu item.
		$icon = (string) file_get_contents(CE4WP_PLUGIN_DIR . 'assets/images/icon.svg');
		// Filter to change the menu position if there is any conflict.
		$position = apply_filters( 'ce4wp_menu_position', '35.5' );
		// @phpstan-ignore-next-line
		add_menu_page(
			'Creative Mail',
			esc_html__('Creative Mail', self::DOMAIN_CE4WP),
			'manage_options',
			'creativemail',
			$main_action,
			'data:image/svg+xml;base64,' . base64_encode($icon),
			$position
		);

		$sub_actions = array();

		if ( $hasConnectedAccount ) {
			$sub_actions[] = array(
				'title'    => esc_html__('Campaigns', self::DOMAIN_CE4WP),
				'text'     => '<span id="ce4wp-menu-campaigns" data-link_reference="5166faec-1dbb-4434-bad0-bb2f75898f92">' . __('Campaigns', self::DOMAIN_CE4WP) . '</span>',
				'slug'     => 'creativemail_campaigns',
				'callback' => null,
			);
			$sub_actions[] = array(
				'title'    => esc_html__('Contacts', self::DOMAIN_CE4WP),
				'text'     => '<span id="ce4wp-menu-contacts" data-link_reference="836b20fc-9ff1-41b2-912b-a8646caf05a4">' . __('Contacts', self::DOMAIN_CE4WP) . '</span>',
				'slug'     => 'creativemail_contacts',
				'callback' => null,
			);
			$sub_actions[] = array(
				'title'    => esc_html__('WooCommerce', self::DOMAIN_CE4WP),
				'text'     => '<span id="ce4wp-menu-woocommerce" data-link_reference="1fabdbe2-95ed-4e1e-a2f3-ba0278f5096f">' . __('WooCommerce', self::DOMAIN_CE4WP) . '</span>',
				'slug'     => 'creativemail_woocommerce',
				'callback' => null,
			);
			$sub_actions[] = array(
				'title'    => esc_html__('Automation', self::DOMAIN_CE4WP),
				'text'     => '<span id="ce4wp-menu-automation" data-link_reference="d5baea05-c603-4cca-852e-f8e82414f6b0">' . __('Automation', self::DOMAIN_CE4WP) . '</span>',
				'slug'     => 'creativemail_automation',
				'callback' => null,
			);
		}
		$sub_actions[] = array(
			'title'    => esc_html__('Settings', self::DOMAIN_CE4WP),
			'text'     => __('Settings', self::DOMAIN_CE4WP),
			'slug'     => 'creativemail_settings',
			'callback' => array( $this, 'show_settings_page' ),
		);

		foreach ( $sub_actions as $sub_action ) {
			add_submenu_page(
				'creativemail',
				'Creative Mail - ' . $sub_action['title'],
				$sub_action['text'],
				'manage_options',
				$sub_action['slug'],
				// @phpstan-ignore-next-line
				$sub_action['callback']
			);
		}

		// Add woocommerce sub-menu page.
		add_submenu_page(
			self::ADMIN_WOOCOMMERCE,
			esc_html__('Creative Mail', self::DOMAIN_CE4WP),
			esc_html__('Creative Mail', self::DOMAIN_CE4WP),
			'manage_woocommerce',
			'ce4wp-woo-settings',
			// @phpstan-ignore-next-line
			$main_action
		);
	}

	/**
	 * Adds the Admin Notice Permalink on Creative Mail
	 *
	 * @return void
	 */
	public function add_admin_notice_permalink(): void {
		if ( CreativeMail::get_instance()->get_integration_manager()->is_plugin_active(self::ADMIN_WOOCOMMERCE) ) {
			if ( ! CreativeMail::get_instance()->get_integration_manager()->get_permalinks_enabled() ) {
				print( '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Ohoh, pretty permalinks are disabled. To enable the CreativeMail WooCommerce integration', self::DOMAIN_CE4WP) . ' <a href="/wp-admin/options-permalink.php">' . esc_html__('please update your permalink settings', self::DOMAIN_CE4WP) . '</a>.</p></div>' );
				return;
			}
		}
	}

	public function add_admin_notice_password_protected(): void {
		print( '<div class="notice notice-error is-dismissible"><p>' . esc_html__('We see that you have the Password Protected plugin installed and activated on your WordPress site. While this plugin is active, CreativeMail wont be able to complete the setup since the Password Protected plugin is prohibiting us from interacting with your WordPress site.', self::DOMAIN_CE4WP) . ' ' . esc_html__('Please disable this plugin to start using CreativeMail.', self::DOMAIN_CE4WP) . '</p></div>' );
		return;
	}

	public function add_admin_get_started_banner(): void {
		$ce_has_account = OptionsHelper::get_instance_id() != null;
		$ce_hide_banner = OptionsHelper::get_hide_banner('get_started');

		global $pagenow;

		if ( 'plugins.php' == $pagenow && ! $ce_has_account && ! $ce_hide_banner ) {
			$ce_hide_banner_url = get_rest_url( null, 'creativemail/v1/hide_banner?banner=get_started' );
			include CE4WP_PLUGIN_DIR . 'src/views/admin-get-started-banner.php';
		}
	}

	public function add_admin_feedback_notice(): void {
		global $pagenow;
		global $post_type;

		if ( 'edit.php' == $pagenow && 'feedback' == $post_type ) {
			$feedback_notice_module = new FeedbackNoticeModule();
			$feedback_notice_module->display();
		}
	}

	public function add_admin_dashboard_widget(): void {
		$widget_title = wp_kses(
		/* translators: Placeholder is a CreativeMail logo. */
			__( 'Email Marketing <span class="floater">By<div class="ce4wp_dashboard_icon"></div></span>', self::DOMAIN_CE4WP),
			array(
				'span' => array( 'class' => array() ),
				'div'  => array( 'class' => array() ),
			)
		);

		add_meta_box(
			'ce4wp_admin_dashboard_widget',
			$widget_title,
			array( $this, 'show_ce4wp_admin_dashboard_widget' ),
			'dashboard',
			'normal',
			'high'
		);
	}

	public function show_ce4wp_admin_dashboard_widget(): void {
		$dashboard_widget_module = new DashboardWidgetModule();
		$dashboard_widget_module->show();
	}

	/**
	 * Renders the onboarding flow.
	 *
	 * @return void
	 */
	public function show_setup(): void {
		include CE4WP_PLUGIN_DIR . 'src/views/onboarding.php';
	}

	/**
	 * Renders the Creative Mail dashboard when the site is connected to an account.
	 *
	 * @return void
	 */
	public function show_dashboard(): void {
		include CE4WP_PLUGIN_DIR . 'src/views/dashboard.php';
	}

	private function enqueue_dashboard_js(): void {
		wp_enqueue_script('ce4wp_dashboard', CE4WP_PLUGIN_URL . 'assets/js/dashboard.js', array( 'jquery' ), CE4WP_PLUGIN_VERSION, true);
		wp_localize_script('ce4wp_dashboard', self::ADMIN_CE4WP_DATA_VAR, array(
			'url'   => admin_url('admin-ajax.php'),
			'nonce' => $this->create_nonce(),
		));
	}

	/**
	 * Generates an SSO link for the current user.
	 *
	 * @param string|null $linkReference The link reference.
	 * @param array|null  $linkParameters The link parameters.
	 *
	 * @return string|null
	 * @since  1.1.5
	 */
	public function get_sso_link( ?string $linkReference = null, ?array $linkParameters = null ): ?string {
		// Only if you are running in wp-admin.
		if ( ! current_user_can('administrator') ) {
			return null;
		}

		// If all the three values are available, we can use the SSO flow.
		$instance_id          = OptionsHelper::get_instance_id();
		$instance_api_key     = OptionsHelper::get_instance_api_key();
		$connected_account_id = OptionsHelper::get_connected_account_id();

		if ( isset($instance_id) && ! empty($instance_api_key) && isset($connected_account_id) ) {
			try {
				return SsoHelper::generate_sso_link($instance_id, $instance_api_key, $connected_account_id, $linkReference, $linkParameters);
			} catch ( Exception $ex ) {
				RaygunManager::get_instance()->exception_handler($ex);
			}
		}

		return null;
	}

	/**
	 * Renders the settings page for this plugin.
	 *
	 * @return void
	 */
	public function show_settings_page(): void {
		include CE4WP_PLUGIN_DIR . 'src/views/settings.php';
	}
}
