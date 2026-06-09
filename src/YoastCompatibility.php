<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class YoastCompatibility
{
	public function is_noindex(): bool
	{
		if ( class_exists( '\Yoast\WP\SEO\Surfaces\Meta_Surface' ) && function_exists( 'YoastSEO' ) ) {
			try {
				$meta = YoastSEO()->meta->for_current_page();

				if ( $meta && isset( $meta->robots ) && isset( $meta->robots->noindex ) ) {
					return (bool) $meta->robots->noindex;
				}
			} catch ( \Throwable $exception ) {
				// Fall through to legacy meta lookup.
			}
		}

		$post_id = get_queried_object_id();

		if ( $post_id <= 0 ) {
			return false;
		}

		$legacy = get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true );

		return '1' === (string) $legacy || 1 === $legacy;
	}

	public function get_meta_description(): string
	{
		if ( class_exists( '\Yoast\WP\SEO\Surfaces\Meta_Surface' ) && function_exists( 'YoastSEO' ) ) {
			try {
				$meta = YoastSEO()->meta->for_current_page();

				if ( $meta && method_exists( $meta, 'description' ) ) {
					$description = $meta->description;

					if ( is_string( $description ) && '' !== trim( $description ) ) {
						return trim( $description );
					}
				}
			} catch ( \Throwable $exception ) {
				// Fall through to legacy meta lookup.
			}
		}

		$post_id = get_queried_object_id();

		if ( $post_id <= 0 ) {
			return '';
		}

		$legacy = get_post_meta( $post_id, '_yoast_wpseo_metadesc', true );

		return is_string( $legacy ) ? trim( $legacy ) : '';
	}
}
