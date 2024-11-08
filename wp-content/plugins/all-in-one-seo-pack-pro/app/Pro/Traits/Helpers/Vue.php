<?php
namespace AIOSEO\Plugin\Pro\Traits\Helpers;

use AIOSEO\Plugin\Pro\Models;
use AIOSEO\Plugin\Common\Models as CommonModels;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Contains all Vue related helper methods.
 *
 * @since 4.1.4
 */
trait Vue {
	/**
	 * Holds the data for Vue.
	 *
	 * @since 4.4.9
	 *
	 * @var array
	 */
	private $data = [];

	/**
	 * Optional arguments for setting the data.
	 *
	 * @since 4.4.9
	 *
	 * @var array
	 */
	private $args = [];

	/**
	 * Holds the cached data.
	 *
	 * @since 4.5.1
	 *
	 * @var array
	 */
	private $cache = [];

	/**
	 * Returns the data for Vue.
	 *
	 * @since   4.0.0
	 * @version 4.4.9
	 *
	 * @param  string $page         The current page.
	 * @param  int    $staticPostId Data for a specific post.
	 * @param  string $integration  Data for integration (builder).
	 * @return array                The data.
	 */
	public function getVueData( $page = null, $staticPostId = null, $integration = null ) {
		$this->args = compact( 'page', 'staticPostId', 'integration' );
		$hash       = md5( implode( '', array_map( 'strval', $this->args ) ) );
		if ( isset( $this->cache[ $hash ] ) ) {
			return $this->cache[ $hash ];
		}

		$this->data = parent::getVueData( $page, $staticPostId, $integration );

		$this->setInitialData();
		$this->setLicenseData();
		$this->setTermData();
		$this->setSearchStatisticsData();
		$this->setSeoRevisionsData();
		$this->setPostData();
		$this->setProductData();
		$this->setToolsOrSettingsData();
		$this->maybeCheckForPluginUpdates();

		$this->data = aioseo()->addons->getVueData( $this->data, $page );

		$this->cleanSensitiveData();

		$this->cache[ $hash ] = $this->data;

		return $this->cache[ $hash ];
	}

	/**
	 * Set Vue initial data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setInitialData() {
		$videoSitemapFilename = aioseo()->sitemap->helpers->filename( 'video' );
		$videoSitemapFilename = $videoSitemapFilename ?: 'video-sitemap';
		$newsIndex            = apply_filters( 'aioseo_news_sitemap_index_name', 'news' );

		$this->data['data']['isMemberMouseCoursesActive'] = aioseo()->helpers->isMemberMouseCoursesActive();
		$this->data['data']['isMemberPressCoursesActive'] = aioseo()->helpers->isMemberPressCoursesActive();
		$this->data['data']['isWishListCourseCureActive'] = aioseo()->helpers->isWishListCourseCureActive();

		$this->data['urls']['videoSitemapUrl'] = home_url( "/$videoSitemapFilename.xml" );
		$this->data['urls']['newsSitemapUrl']  = home_url( "/$newsIndex-sitemap.xml" );

		$this->data['translationsPro'] = $this->getJedLocaleData( 'aioseo-pro' );
	}

	/**
	 * Set Vue license data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setLicenseData() {
		$license = is_network_admin() ? aioseo()->networkLicense : aioseo()->license;
		$internalOptions = is_network_admin() ? aioseo()->internalNetworkOptions : aioseo()->internalOptions;

		$this->data['license'] = [
			'isActive'   => $license->isActive(),
			'isExpired'  => $license->isExpired(),
			'isDisabled' => $license->isDisabled(),
			'isInvalid'  => $license->isInvalid(),
			'expires'    => $internalOptions->internal->license->expires,
			'features'   => $license->getLicenseFeatures()
		];

		// Check if this site is network licensed.
		$this->data['data']['isNetworkLicensed'] = aioseo()->license->isNetworkLicensed();
	}

	/**
	 * Set Vue term data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setTermData() {
		if ( ! aioseo()->helpers->isScreenBase( 'term' ) ) {
			return;
		}

		// phpcs:disable  HM.Security.ValidatedSanitizedInput.InputNotSanitized, HM.Security.NonceVerification.Recommended
		$termId = isset( $_GET['tag_ID'] ) ? absint( wp_unslash( $_GET['tag_ID'] ) ) : 0;
		// phpcs:enable

		$term     = Models\Term::getTerm( $termId );
		$taxonomy = get_term( $termId );
		$screen   = aioseo()->helpers->getCurrentScreen();

		$this->data['currentPost'] = [
			'context'                     => 'term',
			'tags'                        => aioseo()->tags->getDefaultTermTags( $termId ),
			'id'                          => $termId,
			'priority'                    => isset( $term->priority ) && null !== $term->priority ? (float) $term->priority : 'default',
			'frequency'                   => ! empty( $term->frequency ) ? $term->frequency : 'default',
			'permalink'                   => get_term_link( $termId ),
			'title'                       => ! empty( $term->title ) ? $term->title : aioseo()->meta->title->getTaxonomyTitle( $taxonomy->taxonomy ),
			'description'                 => ! empty( $term->description ) ? $term->description : aioseo()->meta->description->getTaxonomyDescription( $taxonomy->taxonomy ),
			'keywords'                    => ! empty( $term->keywords ) ? $term->keywords : [],
			'type'                        => get_taxonomy( $screen->taxonomy )->labels->singular_name,
			'termType'                    => 'type' === $screen->taxonomy ? '_aioseo_type' : $screen->taxonomy,
			'canonicalUrl'                => $term->canonical_url,
			'default'                     => ( (int) $term->robots_default ) === 0 ? false : true,
			'noindex'                     => ( (int) $term->robots_noindex ) === 0 ? false : true,
			'noarchive'                   => ( (int) $term->robots_noarchive ) === 0 ? false : true,
			'nosnippet'                   => ( (int) $term->robots_nosnippet ) === 0 ? false : true,
			'nofollow'                    => ( (int) $term->robots_nofollow ) === 0 ? false : true,
			'noimageindex'                => ( (int) $term->robots_noimageindex ) === 0 ? false : true,
			'noodp'                       => ( (int) $term->robots_noodp ) === 0 ? false : true,
			'notranslate'                 => ( (int) $term->robots_notranslate ) === 0 ? false : true,
			'maxSnippet'                  => null === $term->robots_max_snippet ? -1 : (int) $term->robots_max_snippet,
			'maxVideoPreview'             => null === $term->robots_max_videopreview ? -1 : (int) $term->robots_max_videopreview,
			'maxImagePreview'             => $term->robots_max_imagepreview,
			'modalOpen'                   => false,
			'generalMobilePrev'           => false,
			'og_object_type'              => ! empty( $term->og_object_type ) ? $term->og_object_type : 'default',
			'og_title'                    => $term->og_title,
			'og_description'              => $term->og_description,
			'og_image_custom_url'         => $term->og_image_custom_url,
			'og_image_custom_fields'      => $term->og_image_custom_fields,
			'og_image_type'               => ! empty( $term->og_image_type ) ? $term->og_image_type : 'default',
			'og_video'                    => ! empty( $term->og_video ) ? $term->og_video : '',
			'og_article_section'          => ! empty( $term->og_article_section ) ? $term->og_article_section : '',
			'og_article_tags'             => ! empty( $term->og_article_tags ) ? $term->og_article_tags : [],
			'twitter_use_og'              => ( (int) $term->twitter_use_og ) === 0 ? false : true,
			'twitter_card'                => $term->twitter_card,
			'twitter_image_custom_url'    => $term->twitter_image_custom_url,
			'twitter_image_custom_fields' => $term->twitter_image_custom_fields,
			'twitter_image_type'          => $term->twitter_image_type,
			'twitter_title'               => $term->twitter_title,
			'twitter_description'         => $term->twitter_description,
			'redirects'                   => [
				'modalOpen' => false
			]
		];

		if ( ! $term->exists() ) {
			$this->data['currentPost'] = array_merge(
				$this->data['currentPost'],
				aioseo()->migration->meta->getMigratedTermMeta( $termId )
			);
		}
	}

	/**
	 * Set Vue search statistics data.
	 *
	 * @since 4.7.0
	 *
	 * @return void
	 */
	private function setSearchStatisticsData() {
		$this->data['searchStatistics'] = array_merge(
			$this->data['searchStatistics'],
			[
				'range' => aioseo()->searchStatistics->stats->getDateRange()
			]
		);
	}

	/**
	 * Set Vue seo revisions data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setSeoRevisionsData() {
		if ( aioseo()->helpers->isScreenBase( 'term' ) ) {
			$this->data['seoRevisions'] = aioseo()->seoRevisions->getVueDataEdit();
		}
	}

	/**
	 * Set Vue post data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setPostData() {
		if ( 'post' !== $this->args['page'] ) {
			return;
		}

		$postId     = $this->args['staticPostId'] ?: get_the_ID();
		$aioseoPost = CommonModels\Post::getPost( $postId );
		$wpPost     = get_post( $postId );
		if ( is_object( $wpPost ) ) {
			$this->data['currentPost']['defaultSchemaType']  = '';
			$this->data['currentPost']['defaultWebPageType'] = '';

			$dynamicOptions = aioseo()->dynamicOptions->noConflict();
			if ( $dynamicOptions->searchAppearance->postTypes->has( $wpPost->post_type ) ) {
				$this->data['currentPost']['defaultSchemaType']  = $dynamicOptions->searchAppearance->postTypes->{$wpPost->post_type}->schemaType;
				$this->data['currentPost']['defaultWebPageType'] = $dynamicOptions->searchAppearance->postTypes->{$wpPost->post_type}->webPageType;
			}
		}

		$clonedSchema = json_decode( wp_json_encode( $this->data['currentPost']['schema'] ) );

		$this->data['schema']['output'] = aioseo()->schema->getValidatorOutput(
			$postId,
			$clonedSchema->graphs,
			$clonedSchema->blockGraphs,
			$clonedSchema->default,
			$clonedSchema->blockGraphs
		);

		$this->data['currentPost']['open_ai'] = CommonModels\Post::getDefaultOpenAiOptions( $aioseoPost->open_ai );

		$this->data['currentPost']['primary_term'] = ! empty( $aioseoPost->primary_term ) ? $aioseoPost->primary_term : [];
	}

	/**
	 * Set Vue product/download data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setProductData() {
		$wpPost = $this->getPost();
		if (
			! $wpPost ||
			! in_array( $wpPost->post_type, [ 'product', 'download' ], true )
		) {
			return;
		}

		$isWooCommerceActive = $this->isWooCommerceActive();
		$isEddActive         = $this->isEddActive();
		$this->data['data']  += [
			'isWooCommerceActive' => $isWooCommerceActive,
			'isEddActive'         => $isEddActive
		];

		if ( $isWooCommerceActive ) {
			$product = wc_get_product( $wpPost->ID );
			if ( ! is_a( $product, 'WC_Product' ) ) {
				return;
			}

			$this->data['data']['wooCommerce'] = [
				'currencySymbol'                => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '$',
				'isPerfectBrandsActive'         => $this->isPerfectBrandsActive(),
				'isWooCommerceBrandsActive'     => $this->isWooCommerceBrandsActive(),
				'isWooCommerceUpcEanIsbnActive' => $this->isWooCommerceUpcEanIsbnActive(),
				'reviewsEnabled'                => $product->get_reviews_allowed()
			];
		}

		if ( $isEddActive ) {
			$this->data['data']['edd']['isEddReviewsActive'] = $this->isEddReviewsActive();
		}
	}

	/**
	 * Set Vue tools or settings data.
	 *
	 * @since 4.4.9
	 *
	 * @return void
	 */
	private function setToolsOrSettingsData() {
		if (
			'tools' !== $this->args['page'] &&
			'settings' !== $this->args['page']
		) {
			return;
		}

		if ( 'settings' === $this->args['page'] ) {
			$this->data['breadcrumbs']['defaultTemplates'] = [];

			$postTypes = aioseo()->helpers->getPublicPostTypes();
			foreach ( $postTypes as $postType ) {
				if ( 'type' === $postType['name'] ) {
					$postType['name'] = '_aioseo_type';
				}

				// phpcs:ignore Generic.Files.LineLength.MaxExceeded
				$this->data['breadcrumbs']['defaultTemplates']['postTypes'][ $postType['name'] ] = aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'single', $postType ) );
			}

			$taxonomies = aioseo()->helpers->getPublicTaxonomies();
			foreach ( $taxonomies as $taxonomy ) {
				if ( 'type' === $taxonomy['name'] ) {
					$taxonomy['name'] = '_aioseo_type';
				}

				// phpcs:ignore Generic.Files.LineLength.MaxExceeded
				$this->data['breadcrumbs']['defaultTemplates']['taxonomies'][ $taxonomy['name'] ] = aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'taxonomy', $taxonomy ) );
			}

			$this->data['breadcrumbs']['defaultTemplates']['archives'] = [
				'blog'     => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'blog' ) ),
				'author'   => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'author' ) ),
				'search'   => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'search' ) ),
				'notFound' => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'notFound' ) ),
				'date'     => [
					'year'  => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'year' ) ),
					'month' => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'month' ) ),
					'day'   => aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'day' ) )
				]
			];

			$archives = aioseo()->helpers->getPublicPostTypes( false, true, true );
			foreach ( $archives as $archive ) {
				// phpcs:ignore Generic.Files.LineLength.MaxExceeded
				$this->data['breadcrumbs']['defaultTemplates']['archives']['postTypes'][ $archive['name'] ] = aioseo()->helpers->encodeOutputHtml( aioseo()->breadcrumbs->frontend->getDefaultTemplate( 'postTypeArchive', $archive ) );
			}
		}

		if (
			'tools' === $this->args['page'] &&
			is_multisite() &&
			is_network_admin() &&
			aioseo()->license->hasCoreFeature( 'tools', 'network-tools-import-export' )
		) {
			foreach ( aioseo()->helpers->getSites()['sites'] as $site ) {
				aioseo()->helpers->switchToBlog( $site->blog_id );
				$this->data['data']['network']['backups'][ $site->blog_id ] = array_reverse( aioseo()->backup->all() );
			}

			aioseo()->helpers->restoreCurrentBlog();
		}
	}

	/**
	 * We may need to force a check for plugin updates.
	 *
	 * @since 4.1.6
	 *
	 * @return void
	 */
	private function maybeCheckForPluginUpdates() {
		// If we aren't on one of the addon pages, return early.
		if ( ! in_array( $this->args['page'], [
			'feature-manager',
			'link-assistant',
			'local-seo',
			'redirects',
			'search-appearance',
			'sitemaps'
		], true ) ) {
			return;
		}

		$shouldCheckForUpdates = false;

		// Loop through all addons and see if the addon needing an update matches the current page.
		foreach ( aioseo()->addons->getAddons() as $addon ) {
			if ( $addon->hasMinimumVersion ) {
				continue;
			}

			if ( 'feature-manager' === $this->args['page'] ) {
				$shouldCheckForUpdates = true;
				continue;
			}

			if ( 'aioseo-' . $this->args['page'] === $addon->sku ) {
				$shouldCheckForUpdates = true;
				continue;
			}

			if (
				'sitemaps' === $this->args['page'] &&
				in_array( $addon->sku, [ 'aioseo-video-sitemap', 'aioseo-news-sitemap' ], true )
			) {
				$shouldCheckForUpdates = true;
			}
		}

		// We want to force checks for updates, so let's go ahead and do that now.
		if ( $shouldCheckForUpdates ) {
			delete_site_transient( 'update_plugins' );
		}
	}

	/**
	 * Clean sensitive data.
	 *
	 * @since 4.5.3
	 *
	 * @return void
	 */
	private function cleanSensitiveData() {
		// If the user is an admin, we don't need to clean the data.
		if ( aioseo()->access->isAdmin() ) {
			return;
		}

		// Check for the license key and override it with a placeholder.
		if ( ! empty( $this->data['options']['general']['licenseKey'] ) ) {
			$this->data['options']['general']['licenseKey'] = '*****************';
		}

		if ( ! empty( $this->data['networkOptions']['general']['licenseKey'] ) ) {
			$this->data['networkOptions']['general']['licenseKey'] = '*****************';
		}
	}
}