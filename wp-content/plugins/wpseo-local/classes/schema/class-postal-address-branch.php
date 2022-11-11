<?php
/**
 * @package WPSEO_Local\Frontend\Schema
 */

/**
 * Class WPSEO_Local_JSON_LD
 *
 * Manages the Schema for a branch Postal Address.
 *
 * @property WPSEO_Schema_Context $context A value object with context variables.
 * @property array                $options Local SEO options.
 */
class WPSEO_Local_Postal_Address_Branch extends WPSEO_Local_Postal_Address {

	/**
	 * Determines whether or not this piece should be added to the graph.
	 *
	 * @return bool
	 */
	public function is_needed() {
		return wpseo_schema_will_have_branch_organization( $this->context->site_represents === 'company' );
	}

	/**
	 * Generates JSON+LD output for locations.
	 *
	 * @return false|array Array with Postal Address schema data. Returns false no valid location is found.
	 */
	public function generate() {
		$repository = new WPSEO_Local_Locations_Repository();
		$location   = $repository->for_current_page();

		// Bail if the $location object is empty.
		if ( ! $location ) {
			return false;
		}

		return $this->get_data( $location );
	}

	/**
	 * Gets the desired ID of the schema node.
	 *
	 * @return string ID of the schema node.
	 */
	public function get_schema_id() {
		return WPSEO_Local_Schema_IDs::BRANCH_ADDRESS_ID;
	}
}
