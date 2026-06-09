<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

use League\HTMLToMarkdown\HtmlConverter;

final class MarkdownConverter
{
	/**
	 * @var array<string, mixed>
	 */
	private array $settings;

	private YoastCompatibility $yoast;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings, YoastCompatibility $yoast )
	{
		$this->settings = $settings;
		$this->yoast    = $yoast;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function convert( string $html, array $context = array() ): string
	{
		$converter = new HtmlConverter(
			array(
				'strip_tags'      => true,
				'remove_nodes'    => 'script style noscript iframe',
				'hard_break'      => true,
				'header_style'    => 'atx',
				'bold_style'      => '**',
				'italic_style'    => '*',
				'strip_placeholder_links' => true,
			)
		);

		$markdown = $converter->convert( $html );
		$markdown = $this->post_process( $markdown );

		return $this->prepend_front_matter( $markdown, $html, $context );
	}

	private function post_process( string $markdown ): string
	{
		$markdown = html_entity_decode( $markdown, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		if ( ! empty( $this->settings['include_h1'] ) && '' !== $this->get_title() ) {
			$markdown = preg_replace( '/^# /m', '## ', $markdown ) ?? $markdown;
		}

		$markdown = preg_replace( '/\[\s*([^\]]+?)\s*\]\(\s*([^)]+?)\s*\)/', '[$1]($2)', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/!\[[^\]]*\]\(\s*\)/', '', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/!\[\s*\]\([^)]+\)/', '', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/^!\s*$/m', '', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/^#+\s*$/m', '', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/\[\s*\]\([^)]*\)/', '', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/^[ \t]+(#+\s)/m', '$1', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/^[ \t]+(\[)/m', '$1', $markdown ) ?? $markdown;
		$markdown = preg_replace( '/[ \t]+$/m', '', $markdown ) ?? $markdown;

		$markdown = $this->remove_empty_lines( $markdown );
		$markdown = $this->remove_consecutive_duplicate_lines( $markdown );
		$markdown = preg_replace( '/\)\s+\[/', ")\n[", $markdown ) ?? $markdown;
		$markdown = preg_replace( '/([^\n#])\n(#{1,6} )/', "$1\n\n$2", $markdown ) ?? $markdown;
		$markdown = preg_replace( "/\n{3,}/", "\n\n", $markdown ) ?? $markdown;

		return trim( $markdown );
	}

	private function remove_empty_lines( string $markdown ): string
	{
		$lines = preg_split( "/\r\n|\n|\r/", $markdown ) ?: array();

		return implode(
			"\n",
			array_values(
				array_filter(
					$lines,
					static function ( string $line ): bool {
						return '' !== trim( $line );
					}
				)
			)
		);
	}

	private function remove_consecutive_duplicate_lines( string $markdown ): string
	{
		$lines    = preg_split( "/\r\n|\n|\r/", $markdown ) ?: array();
		$deduped  = array();
		$previous = null;

		foreach ( $lines as $line ) {
			$trimmed = trim( $line );

			if ( null !== $previous && $trimmed === $previous && '' !== $trimmed ) {
				continue;
			}

			$deduped[] = $line;
			$previous  = $trimmed;
		}

		return implode( "\n", $deduped );
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function prepend_front_matter( string $markdown, string $html, array $context ): string
	{
		$parts = array();

		if ( ! empty( $this->settings['include_h1'] ) ) {
			$title = $this->get_title();

			if ( '' !== $title ) {
				$parts[] = '# ' . $title;
			}
		}

		if ( ! empty( $this->settings['include_canonical'] ) ) {
			$canonical = is_string( $context['canonical_url'] ?? null ) ? $context['canonical_url'] : $this->get_canonical_url();

			if ( '' !== $canonical ) {
				$parts[] = 'Source: ' . $canonical;
			}
		}

		if ( ! empty( $this->settings['include_excerpt'] ) ) {
			$description = $this->yoast->get_meta_description();

			if ( '' === $description ) {
				$description = $this->get_excerpt();
			}

			if ( '' !== $description ) {
				$parts[] = html_entity_decode( $description, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			}
		}

		if ( ! empty( $this->settings['include_featured_image'] ) ) {
			$image = $this->extract_featured_image_from_html( $html );

			if ( '' !== $image ) {
				if ( ! empty( $this->settings['featured_image_position'] ) && 'prepend' === $this->settings['featured_image_position'] ) {
					array_unshift( $parts, $image );
				} else {
					$parts[] = $image;
				}
			}
		}

		if ( empty( $parts ) ) {
			return $markdown;
		}

		return implode( "\n\n", $parts ) . "\n\n" . $markdown;
	}

	private function get_title(): string
	{
		$title = wp_get_document_title();

		if ( ! is_string( $title ) ) {
			return '';
		}

		return trim( html_entity_decode( $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
	}

	private function get_canonical_url(): string
	{
		if ( is_singular() ) {
			$permalink = get_permalink();

			return is_string( $permalink ) ? $permalink : '';
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		return '';
	}

	private function get_excerpt(): string
	{
		$post = get_post();

		if ( ! $post instanceof \WP_Post ) {
			return '';
		}

		$excerpt = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( $post->post_content ), 55 );

		return trim( wp_strip_all_tags( (string) $excerpt ) );
	}

	private function extract_featured_image_from_html( string $html ): string
	{
		if ( ! preg_match( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $html, $match ) ) {
			return '';
		}

		$src = esc_url_raw( $match[1] );

		if ( '' === $src ) {
			return '';
		}

		$alt = '';

		if ( preg_match( '/alt=["\']([^"\']*)["\']/i', $match[0], $alt_match ) ) {
			$alt = trim( $alt_match[1] );
		}

		return '![' . $alt . '](' . $src . ')';
	}
}
