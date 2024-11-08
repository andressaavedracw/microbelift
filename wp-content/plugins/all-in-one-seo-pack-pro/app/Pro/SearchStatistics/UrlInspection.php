<?php
namespace AIOSEO\Plugin\Pro\SearchStatistics;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIOSEO\Plugin\Pro\Models\SearchStatistics as Models;

/**
 * Handles the Inspection Result scan.
 *
 * @since 4.5.0
 */
class UrlInspection {
	/**
	 * Gets the inspection result for the given path.
	 * Returning null will force it to be fetched again on the front-end.
	 *
	 * @since 4.5.5
	 *
	 * @param  string      $path The path to get the inspection result for.
	 * @return object|null       The inspection result object or null if the object needs to be fetched again.
	 */
	public function get( $path ) {
		$wpObject = Models\WpObject::getObject( $path );

		// Returning null for the scenarios below will force the object to be fetched again.
		if ( ! $wpObject->isUrlInspectionValid() ) {
			return null;
		}

		return $wpObject->inspection_result;
	}

	/**
	 * Resets all the inspection_results and force scanning again.
	 *
	 * @since 4.5.0
	 *
	 * @return void
	 */
	public function reset() {
		aioseo()->core->db->update( 'aioseo_search_statistics_objects as asso' )
			->set(
				[
					'inspection_result'      => null,
					'inspection_result_date' => null,
					'indexed'                => 0
				]
			)
			->run();
	}
}