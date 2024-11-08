<?php
namespace AIOSEO\Plugin\Pro\Ai;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class to handle AI via OpenAI.
 *
 * @since 4.3.2
 */
class Ai {
	/**
	 * The temperature parameter controls randomness in Boltzmann distributions.
	 * The higher the temperature, the more random the completions.
	 *
	 * @since 4.3.2
	 *
	 * @var float
	 */
	private $temperature = 0.7;

	/**
	 * The AI model to use.
	 * @see https://platform.openai.com/docs/models/gpt-3 for a list of models.
	 *
	 * @since 4.3.2
	 *
	 * @var string
	 */
	private $model = 'gpt-3.5-turbo';

	/**
	 * The slug for the API.
	 *
	 * @since 4.5.5
	 *
	 * @var string
	 */
	private $slug = 'chat/completions';

	/**
	 * The maximum number of tokens to return.
	 *
	 * @since 4.5.5
	 *
	 * @var int
	 */
	private $maxTokens = 0;

	/**
	 * The maximum number of results to return.
	 *
	 * @since 4.5.5
	 *
	 * @var int
	 */
	private $amountOfResults = 1;

	/**
	 * The "target" we're generating suggestions for ("title" or "description").
	 *
	 * @since 4.5.5
	 *
	 * @var string
	 */
	private $target = '';

	/**
	 * The post content.
	 *
	 * @since 4.5.5
	 *
	 * @var string
	 */
	private $postContent = '';

	/**
	 * The focus KW.
	 *
	 * @since 4.5.5
	 *
	 * @var string
	 */
	private $focusKw = '';

	/**
	 * The base URL for the API.
	 *
	 * @since 4.3.2
	 *
	 * @var string
	 */
	private $baseUrl = 'https://api.openai.com/v1/';

	/**
	 * Returns title suggestions.
	 *
	 * @since 4.5.5
	 *
	 * @param  string $postContent The post content.
	 * @param  string $focusKw     The focus KW.
	 * @return array|\WP_Error     The suggestions or the WP error.
	 */
	public function getTitleSuggestions( $postContent, $focusKw ) {
		$this->target      = 'title';
		$this->maxTokens   = 100;
		$this->postContent = $this->cleanPostContent( $postContent );
		$this->focusKw     = $focusKw;

		return $this->getSuggestions();
	}

	/**
	 * Returns description suggestions.
	 *
	 * @since 4.5.5
	 *
	 * @param  string $postContent The post content.
	 * @param  string $focusKw     The focus KW.
	 * @return array|\WP_Error     The suggestions or the WP error.
	 */
	public function getDescriptionSuggestions( $postContent, $focusKw ) {
		$this->target      = 'description';
		$this->maxTokens   = 300;
		$this->postContent = $this->cleanPostContent( $postContent );
		$this->focusKw     = $focusKw;

		return $this->getSuggestions();
	}

	/**
	 * Returns suggestions for the current target.
	 *
	 * @since 4.5.5
	 *
	 * @return array|\WP_Error The suggestions or the WP error.
	 */
	private function getSuggestions() {
		$messages     = $this->getMessages();
		$responseData = $this->sendRequest( $messages );
		if ( is_wp_error( $responseData ) ) {
			return $responseData;
		}

		$result = $this->extractSuggestions( $responseData );

		return $result;
	}

	/**
	 * Sends a stream of requests to OpenAI in order to get suggestions for the title or meta description.
	 *
	 * @since   4.3.2
	 * @version 4.5.5 Slimmed down to just send the request.
	 *
	 * @param  string               $messages The messages to send to the AI.
	 * @return array|bool|\WP_Error           The response data or the WP error.
	 */
	public function sendRequest( $messages ) {
		$response = aioseo()->helpers->wpRemotePost( $this->getUrl() . $this->slug, $this->getRequestArgs( $messages ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		if ( isset( $data->error ) ) {
			return new \WP_Error(
				$data->error->type,
				$data->error->message
			);
		}

		return $data;
	}

	/**
	 * Extracts the suggestions from the response.
	 *
	 * @since 4.5.5
	 *
	 * @param  object $response The response data.
	 * @return array            The suggestions.
	 */
	private function extractSuggestions( $response ) {
		if ( empty( $response->choices[0]->message->content ) ) {
			return [];
		}

		$rawSuggestions = $response->choices[0]->message->content;
		preg_match_all( '/\"\"\"(.*?)\"\"\"/', $rawSuggestions, $matches );

		$suggestions = [];
		foreach ( $matches[1] as $suggestion ) {
			$suggestions[] = $this->cleanSuggestion( $suggestion );
		}

		return array_unique( array_filter( $suggestions ) );
	}

	/**
	 * Cleans the given post content.
	 *
	 * @since 4.5.5
	 *
	 * @param  string $postContent The post content to clean.
	 * @return string              The cleaned post content.
	 */
	private function cleanPostContent( $postContent ) {
		$postContent = strip_shortcodes( wp_strip_all_tags( $postContent ) );
		$postContent = normalize_whitespace( $postContent );
		$postContent = preg_replace( '/\v+/', ' ', $postContent ); // Remove new lines.
		$postContent = aioseo()->helpers->decodeHtmlEntities( $postContent );
		$postContent = wp_trim_words( $postContent, 800 );

		return $postContent;
	}

	/**
	 * Cleans the given title/description suggestion.
	 *
	 * @since 4.5.5
	 *
	 * @param  string $suggestion The suggestion to clean.
	 * @return string             The cleaned suggestion.
	 */
	private function cleanSuggestion( $suggestion ) {
		$suggestion = stripslashes_deep( wp_filter_nohtml_kses( wp_strip_all_tags( $suggestion ) ) );
		$suggestion = aioseo()->helpers->decodeHtmlEntities( $suggestion );
		$suggestion = preg_replace( '/\v+/', '', $suggestion );

		// Trim quotes from beginning/end and redundant whitespace.
		$suggestion = preg_replace( '/^["\']/', '', $suggestion );
		$suggestion = preg_replace( '/["\']$/', '', $suggestion );
		$suggestion = trim( normalize_whitespace( $suggestion ) );

		// Replace the focus KW with the actual focus KW to preserve the casing.
		if ( $this->focusKw ) {
			$escapedFocusKw        = aioseo()->helpers->escapeRegex( $this->focusKw );
			$escapedReplaceFocusKw = aioseo()->helpers->escapeRegexReplacement( $this->focusKw );
			$suggestion            = preg_replace( "/\b{$escapedFocusKw}\b/i", $escapedReplaceFocusKw, $suggestion );
		}

		$suggestion = ucfirst( $suggestion );

		return $suggestion;
	}

	/**
	 * Returns the initial messages for the AI.
	 *
	 * @since 4.5.5
	 *
	 * @return array The initial messages.
	 */
	private function getMessages() {
		$languageCode = get_locale();

		$instructions = [
			'You will be provided with an online blog article.',
		];

		if ( 'en_US' !== $languageCode ) {
			$instructions[2] = "Make sure each suggestion is in the same language as the article. The relevant language code is {$languageCode}.";
		}

		if ( $this->focusKw ) {
			$instructions[3] = "Second, ensure each suggestion contains the keyword '{$this->focusKw}'.";
		}

		$instructions[5] = 'Finally, make sure every suggestion starts and ends with triple double quotes in order to delimit them.';

		switch ( $this->target ) {
			case 'description':
				$instructions[1] = 'First, generate 5 suggestions that can be used as the meta description for the article, based on its text.';
				$instructions[4] = 'Then, make sure each suggestion is between 120 and 160 characters long.';
				break;
			case 'title':
			default:
				$instructions[1] = 'First, generate 5 suggestions that can be used as the SEO title for the article, based on its text.';
				$instructions[4] = 'Then, make sure each suggestion is between 50 and 70 characters long and in title case.';
				break;
		}

		$userMessage = 'Below is the article\'s content, delimited with three consecutive double quotes.' . "\n\n" . '"""' . $this->postContent . '"""';

		ksort( $instructions, SORT_NUMERIC );

		return [
			[
				'role'    => 'system',
				'content' => implode( "\n", array_filter( $instructions ) )
			],
			[
				'role'    => 'user',
				'content' => $userMessage
			]
		];
	}

	/**
	 * Returns the request args.
	 *
	 * @since 4.5.5
	 *
	 * @param  array $messages The messages to send to the AI.
	 * @return array           The request args.
	 */
	private function getRequestArgs( $messages ) {
		$args = [
			'timeout'   => 120,
			'headers'   => [
				'Authorization' => 'Bearer ' . aioseo()->options->advanced->openAiKey,
				'Content-Type'  => 'application/json'
			],
			'body'      => [
				'max_tokens'  => $this->maxTokens,
				'temperature' => $this->temperature,
				'model'       => $this->model,
				'stop'        => null,
				'n'           => $this->amountOfResults,
				'messages'    => $messages
			],
			'sslverify' => false
		];

		$args['body'] = wp_json_encode( $args['body'] );

		return $args;
	}

	/**
	 * Returns the API URL.
	 *
	 * @since 4.3.2
	 *
	 * @return string The API URL.
	 */
	public function getUrl() {
		if ( defined( 'AIOSEO_AI_URL' ) ) {
			return AIOSEO_AI_URL;
		}

		return $this->baseUrl;
	}
}