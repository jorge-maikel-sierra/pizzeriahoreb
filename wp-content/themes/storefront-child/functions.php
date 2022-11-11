<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

// BEGIN ENQUEUE PARENT ACTION
// AUTO GENERATED - Do not modify or remove comment markers above or below:

if ( !function_exists( 'chld_thm_cfg_locale_css' ) ):
    function chld_thm_cfg_locale_css( $uri ){
        if ( empty( $uri ) && is_rtl() && file_exists( get_template_directory() . '/rtl.css' ) )
            $uri = get_template_directory_uri() . '/rtl.css';
        return $uri;
    }
endif;
add_filter( 'locale_stylesheet_uri', 'chld_thm_cfg_locale_css' );

// END ENQUEUE PARENT ACTION
/** Codigo para eliminar campos de formulario de finalizar compra en woocommerce
* Ciudad
* Departamento
* Codigo postal
* Pais
*/
add_filter( 'woocommerce_checkout_fields' , 'custom_remove_woo_checkout_fields' );
 
function custom_remove_woo_checkout_fields( $fields ) {

    // remove billing fields
    unset($fields['billing']['billing_company']);
    unset($fields['billing']['billing_address_2']);
    unset($fields['billing']['billing_city']);
    unset($fields['billing']['billing_postcode']);
    unset($fields['billing']['billing_country']);
    unset($fields['billing']['billing_state']);

    // remove shipping fields 
     
    unset($fields['shipping']['shipping_company']);
    unset($fields['shipping']['shipping_address_2']);
    unset($fields['shipping']['shipping_city']);
    unset($fields['shipping']['shipping_postcode']);
    unset($fields['shipping']['shipping_country']);
    unset($fields['shipping']['shipping_state']);
    
    
    return $fields;
}

/* Quitar redirecciones y avisos de Yoast SEO */
add_filter( 'wpseo_premium_post_redirect_slug_change', '__return_true' );
add_filter( 'wpseo_premium_term_redirect_slug_change', '__return_true' );
add_filter( 'wpseo_enable_notification_post_trash', '__return_false' );
add_filter( 'wpseo_enable_notification_post_slug_change', '__return_false' );
add_filter( 'wpseo_enable_notification_term_delete', '__return_false' );
add_filter( 'wpseo_enable_notification_term_slug_change', '__return_false' );