<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class Headers
{
	/**
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings )
	{
		$this->settings = $settings;
	}

	public function send_markdown_headers(): void
	{
		if ( headers_sent() ) {
			return;
		}

		header( 'Content-Type: text/markdown; charset=utf-8' );
		header( 'Vary: Accept' );
		header( 'X-Robots-Tag: index, follow' );
		header( 'Cache-Control: public, max-age=3600' );
	}

	public function send_html_alternate_headers(): void
	{
		if ( headers_sent() ) {
			return;
		}

		if ( ! empty( $this->settings['link_headers_enabled'] ) ) {
			$this->send_alternate_markdown_link_header();
		}

		header( 'Vary: Accept' );
	}

	public function send_alternate_markdown_link_header(): void
	{
		if ( headers_sent() ) {
			return;
		}

		$link = $this->get_alternate_markdown_link_header();

		if ( '' === $link ) {
			return;
		}

		header( $link, false );
	}

	public function get_alternate_markdown_url(): string
	{
		$url = $this->get_markdown_url();

		if ( '' === $url ) {
			return '';
		}

		/**
		 * Filter the Markdown alternate URL used in Link response headers and head tags.
		 *
		 * @param string $url Absolute Markdown URL.
		 */
		$url = (string) apply_filters( 'sr_agent_markdown_alternate_link_url', $url );

		return $url;
	}

	public function get_alternate_markdown_link_header(): string
	{
		$url = $this->get_alternate_markdown_url();

		if ( '' === $url ) {
			return '';
		}

		return 'Link: <' . $url . '>; rel="alternate"; type="text/markdown"';
	}

	public function get_markdown_url(): string
	{
		$canonical = $this->get_canonical_url();

		if ( '' === $canonical ) {
			return '';
		}

		$parts = wp_parse_url( $canonical );

		if ( ! is_array( $parts ) ) {
			return '';
		}

		$scheme = $parts['scheme'] ?? 'https';
		$host   = $parts['host'] ?? '';
		$port   = isset( $parts['port'] ) ? ':' . $parts['port'] : '';
		$path   = $parts['path'] ?? '/';
		$query  = isset( $parts['query'] ) ? '?' . $parts['query'] : '';

		if ( '/' === $path || '' === $path ) {
			return $scheme . '://' . $host . $port . '/.md' . $query;
		}

		$path = untrailingslashit( $path );

		return $scheme . '://' . $host . $port . $path . '.md' . $query;
	}

	public function get_canonical_url(): string
	{
		if ( is_singular() ) {
			$permalink = get_permalink();

			return is_string( $permalink ) ? $permalink : '';
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_home() ) {
			$posts_page = get_permalink( (int) get_option( 'page_for_posts' ) );

			return is_string( $posts_page ) ? $posts_page : home_url( '/' );
		}

		global $wp;

		if ( isset( $wp ) && $wp instanceof \WP && ! empty( $wp->request ) ) {
			return home_url( user_trailingslashit( $wp->request ) );
		}

		return '';
	}
}
