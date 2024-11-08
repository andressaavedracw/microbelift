<?php
namespace AIOSEO\Plugin\Pro\Models\SearchStatistics;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIOSEO\Plugin\Common\Models as CommonModels;

/**
 * The Object DB Model.
 * It's called WPObject because Object is a reserved word in PHP.
 *
 * @since 4.3.0
 */
class WpObject extends CommonModels\Model {
	/**
	 * The name of the table in the database, without the prefix.
	 *
	 * @since 4.3.0
	 *
	 * @var string
	 */
	protected $table = 'aioseo_search_statistics_objects';

	/**
	 * Fields that should be numeric values.
	 *
	 * @since 4.3.0
	 *
	 * @var array
	 */
	protected $integerFields = [ 'id', 'object_id' ];

	/**
	 * List of fields that should be hidden when serialized.
	 *
	 * @since 4.3.0
	 *
	 * @var array
	 */
	protected $hidden = [ 'id' ];

	/**
	 * Fields that should be json encoded on save and decoded on get.
	 *
	 * @since 4.5.0
	 *
	 * @var array
	 */
	protected $jsonFields = [ 'inspection_result' ];

	/**
	 * Updates (or inserts) a given row.
	 *
	 * @since 4.3.0
	 *
	 * @param  array $data The new data.
	 * @return void
	 */
	public static function update( $data ) {
		if (
			empty( $data['id'] ) ||
			empty( $data['object_path'] )
		) {
			return;
		}

		$data += [
			'object_path_hash' => sha1( $data['object_path'] ),
		];

		$wpObject = aioseo()->core->db->start( 'aioseo_search_statistics_objects' )
			->where( 'id', $data['id'] )
			->run()
			->model( 'AIOSEO\\Plugin\\Pro\\Models\\SearchStatistics\\WpObject' );

		$wpObject->set( $data );
		$wpObject->save();
	}

	/**
	 * Gets a row by its path.
	 *
	 * @since 4.5.0
	 *
	 * @param  string        $path The path.
	 * @return WpObject|null       The object or null if not found.
	 */
	public static function getObject( $path ) {
		$wpObject = aioseo()->core->db->start( 'aioseo_search_statistics_objects' )
			->where( 'object_path_hash', sha1( $path ) )
			->run()
			->model( 'AIOSEO\\Plugin\\Pro\\Models\\SearchStatistics\\WpObject' );

		return $wpObject;
	}

	/**
	 * Gets a row by the given field => value.
	 *
	 * @since 4.5.3
	 *
	 * @param  string        $field The field to look for.
	 * @param  string        $value The value to look for.
	 * @return WpObject|null        The object or null if not found.
	 */
	public static function getObjectBy( $field, $value ) {
		$wpObject = aioseo()->core->db->start( 'aioseo_search_statistics_objects' );

		switch ( $field ) {
			case 'path':
				$wpObject = $wpObject->where( 'object_path_hash', sha1( $value ) );
				break;
			case 'post_id':
				$wpObject = $wpObject->where( 'object_id', $value );
				$wpObject = $wpObject->where( 'object_type', 'post' );
				break;
			case 'term_id':
				$wpObject = $wpObject->where( 'object_id', $value );
				$wpObject = $wpObject->where( 'object_type', 'term' );
				break;
			default:
				$wpObject = $wpObject->where( $field, $value );
		}

		$wpObject = $wpObject->run()->model( 'AIOSEO\\Plugin\\Pro\\Models\\SearchStatistics\\WpObject' );

		return $wpObject;
	}

	/**
	 * Checks if the URL inspection is valid.
	 *
	 * @since 4.6.1
	 *
	 * @return bool Whether the URL inspection is valid.
	 */
	public function isUrlInspectionValid() {
		if (
			empty( $this->inspection_result ) || // If there is no inspection result.
			$this->inspection_result_date && strtotime( $this->inspection_result_date ) < strtotime( '-1 month' ) || // If the inspection result is older than 30 days.
			(
				! $this->indexed && // If the object is not indexed.
				$this->inspection_result_date && strtotime( $this->inspection_result_date ) < strtotime( '-1 day' ) // If the inspection result is older than 1 day.
			)
		) {
			return false;
		}

		return true;
	}

	/**
	 * Bulk inserts a set of rows.
	 *
	 * @since 4.3.0
	 *
	 * @param  array $rows The rows to insert.
	 * @return void
	 */
	public static function bulkInsert( $rows ) {
		$currentDate = gmdate( 'Y-m-d H:i:s' );

		$addValues = [];
		foreach ( $rows as $row ) {
			$row = json_decode( wp_json_encode( $row ), true );

			if ( empty( $row['object_path'] ) ) {
				continue;
			}

			$addValues[] = vsprintf(
				"(%d, '%s', '%s', '%s', '%s', %d, '%s', '%s', %d, '$currentDate', '$currentDate')",
				[
					! empty( $row['object_id'] ) ? (int) $row['object_id'] : null,
					! empty( $row['object_type'] ) ? $row['object_type'] : null,
					! empty( $row['object_subtype'] ) ? $row['object_subtype'] : null,
					$row['object_path'],
					sha1( $row['object_path'] ),
					! empty( $row['seo_score'] ) ? (int) $row['seo_score'] : null,
					! empty( $row['inspection_result'] ) ? aioseo()->core->db->db->_real_escape( wp_json_encode( $row['inspection_result'] ) ) : null,
					! empty( $row['inspection_result_date'] ) ? $row['inspection_result_date'] : null,
					! empty( $row['indexed'] ) ? $row['indexed'] : 0,
				]
			);
		}

		if ( empty( $addValues ) ) {
			return;
		}

		$tableName         = aioseo()->core->db->prefix . 'aioseo_search_statistics_objects';
		$implodedAddValues = implode( ',', $addValues );
		aioseo()->core->db->execute(
			"INSERT INTO $tableName (
				`object_id`, `object_type`, `object_subtype`, `object_path`, `object_path_hash`,
				`seo_score`, `inspection_result`, `inspection_result_date`, `indexed`, `created`, `updated`
			)
			VALUES $implodedAddValues"
		);
	}
}