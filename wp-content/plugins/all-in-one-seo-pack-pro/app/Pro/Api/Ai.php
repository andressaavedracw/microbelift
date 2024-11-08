<?php
namespace AIOSEO\Plugin\Pro\Api;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use AIOSEO\Plugin\Pro\Ai\Ai as Client;
use AIOSEO\Plugin\Common\Models;

/**
 * Contains the OpenAI class.
 *
 * @since 4.3.2
 */
class Ai {
	/**
	 * Generate title or description suggestions.
	 *
	 * @since 4.3.2
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function generate( $request ) {
		$apiKey = aioseo()->options->advanced->openAiKey;
		if ( ! $apiKey ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'No API key found.'
			], 400 );
		}

		$body        = $request->get_json_params();
		$type        = ! empty( $body['type'] ) ? sanitize_text_field( $body['type'] ) : '';
		$postId      = ! empty( $body['postId'] ) ? intval( $body['postId'] ) : 0;
		$postContent = ! empty( $body['postContent'] ) ? sanitize_text_field( $body['postContent'] ) : '';
		$focusKw     = ! empty( $body['focusKw'] ) ? sanitize_text_field( $body['focusKw'] ) : '';
		if ( ! $type || ! $postId || ! $postContent ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'Missing required parameters.'
			], 400 );
		}

		$result = [];
		switch ( $type ) {
			case 'title':
				$result = aioseo()->ai->getTitleSuggestions( $postContent, $focusKw );
				break;
			case 'description':
				$result = aioseo()->ai->getDescriptionSuggestions( $postContent, $focusKw );
				break;
			default:
				return new \WP_REST_Response( [
					'success' => false,
					'message' => 'Invalid type.'
				], 400 );
		}

		if ( is_wp_error( $result ) ) {
			return new \WP_REST_Response( [
				'success' => false,
				'error'   => [
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message()
				]
			], 200 );
		}

		// Save the data to prevent redundant costs/API calls.
		$aioseoPost          = Models\Post::getPost( $postId );
		$aioseoPost->open_ai = Models\Post::getDefaultOpenAiOptions( $aioseoPost->open_ai );

		$aioseoPost->open_ai->{$type}->suggestions = $result;
		$aioseoPost->save();

		return new \WP_REST_Response( [
			'success'     => true,
			'suggestions' => $result
		], 200 );
	}

	/**
	 * Saves the OpenAI API key to the options.
	 *
	 * @since 4.3.2
	 *
	 * @param  \WP_REST_Request  $request The REST Request
	 * @return \WP_REST_Response          The response.
	 */
	public static function saveApiKey( $request ) {
		$body   = $request->get_json_params();
		$apiKey = ! empty( $body['apiKey'] ) ? sanitize_text_field( $body['apiKey'] ) : '';
		if ( ! $apiKey ) {
			return new \WP_REST_Response( [
				'success' => false,
				'message' => 'No API key is missing.'
			], 400 );
		}

		aioseo()->options->advanced->openAiKey = $apiKey;

		return new \WP_REST_Response( [
			'success' => true
		], 200 );
	}
}