<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class RequestDetector
{
	/**
	 * @var array<string, mixed>
	 */
	private array $settings;

	private YoastCompatibility $yoast;

	private ?bool $markdown_request = null;

	private ?bool $explicit_route = null;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings, YoastCompatibility $yoast )
	{
		$this->settings = $settings;
		$this->yoast    = $yoast;
	}

	public function is_markdown_request(): bool
	{
		if ( null !== $this->markdown_request ) {
			return $this->markdown_request;
		}

		if ( ! empty( $this->settings['enabled'] ) && $this->matches_accept_header() ) {
			return $this->markdown_request = true;
		}

		if ( ! empty( $this->settings['md_urls_enabled'] ) && $this->matches_md_url() ) {
			return $this->markdown_request = true;
		}

		if ( ! empty( $this->settings['query_param_enabled'] ) && $this->matches_query_param() ) {
			return $this->markdown_request = true;
		}

		if ( $this->has_rewrite_md_path() ) {
			return $this->markdown_request = true;
		}

		return $this->markdown_request = false;
	}

	public function is_explicit_markdown_route(): bool
	{
		if ( null !== $this->explicit_route ) {
			return $this->explicit_route;
		}

		return $this->explicit_route = $this->matches_md_url()
			|| $this->matches_query_param()
			|| $this->has_rewrite_md_path();
	}

	public function is_eligible(): bool
	{
		if ( $this->is_hard_excluded() ) {
			return false;
		}

		return $this->passes_conditional_exclusions();
	}

	public function is_eligible_for_alternate(): bool
	{
		if ( empty( $this->settings['enabled'] ) ) {
			return false;
		}

		if ( $this->is_hard_excluded() ) {
			return false;
		}

		if ( ! $this->passes_conditional_exclusions() ) {
			return false;
		}

		return $this->is_supported_view();
	}

	/**
	 * Whether an HTML response should advertise the Markdown alternate via Link / head tags.
	 *
	 * Link discovery headers are still sent for logged-in users when Markdown body output
	 * is disabled, so agents and audits see alternates on normal HTML responses.
	 */
	public function is_eligible_for_link_header(): bool
	{
		if ( empty( $this->settings['enabled'] ) ) {
			return false;
		}

		if ( empty( $this->settings['link_headers_enabled'] ) && empty( $this->settings['head_alternate_link'] ) ) {
			return false;
		}

		if ( $this->is_hard_excluded() ) {
			return false;
		}

		if ( ! empty( $this->settings['disable_noindex'] ) && $this->yoast->is_noindex() ) {
			return false;
		}

		return $this->is_supported_view();
	}

	private function is_hard_excluded(): bool
	{
		if ( is_admin() ) {
			return true;
		}

		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() ) {
			return true;
		}

		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return true;
		}

		if ( is_feed() ) {
			return true;
		}

		if ( $this->is_sitemap_request() ) {
			return true;
		}

		if ( $this->is_robots_request() ) {
			return true;
		}

		if ( defined( 'DOING_CRON' ) && DOING_CRON ) {
			return true;
		}

		if ( $this->is_auth_request() ) {
			return true;
		}

		if ( is_preview() ) {
			return true;
		}

		if ( is_404() && empty( $this->settings['allow_404'] ) ) {
			return true;
		}

		if ( is_search() && empty( $this->settings['allow_search'] ) ) {
			return true;
		}

		if ( is_archive() && empty( $this->settings['allow_archives'] ) ) {
			return true;
		}

		if ( post_password_required() ) {
			return true;
		}

		$post = get_post();

		if ( $post instanceof \WP_Post ) {
			if ( in_array( $post->post_status, array( 'private', 'draft', 'pending', 'future' ), true ) ) {
				return true;
			}

			if ( ! $this->is_allowed_post_type( $post->post_type ) ) {
				return true;
			}
		}

		return false;
	}

	private function passes_conditional_exclusions(): bool
	{
		if ( ! empty( $this->settings['disable_logged_in'] ) && is_user_logged_in() ) {
			if ( ! $this->admin_preview_bypass() ) {
				return false;
			}
		}

		if ( ! empty( $this->settings['disable_noindex'] ) && $this->yoast->is_noindex() ) {
			return false;
		}

		return $this->is_supported_view();
	}

	private function is_supported_view(): bool
	{
		if ( is_singular() ) {
			return true;
		}

		if ( is_front_page() || is_home() ) {
			return true;
		}

		if ( is_404() && ! empty( $this->settings['allow_404'] ) ) {
			return true;
		}

		if ( is_search() && ! empty( $this->settings['allow_search'] ) ) {
			return true;
		}

		if ( is_archive() && ! empty( $this->settings['allow_archives'] ) ) {
			return true;
		}

		return false;
	}

	private function admin_preview_bypass(): bool
	{
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		return $this->matches_query_param() || $this->matches_md_url() || $this->has_rewrite_md_path();
	}

	private function matches_accept_header(): bool
	{
		if ( empty( $this->settings['accept_header_enabled'] ) ) {
			return false;
		}

		$accept = $this->get_server_value( 'HTTP_ACCEPT' );

		if ( '' === $accept ) {
			return false;
		}

		return false !== stripos( $accept, 'text/markdown' );
	}

	private function matches_md_url(): bool
	{
		if ( empty( $this->settings['md_urls_enabled'] ) ) {
			return false;
		}

		$uri = $this->get_request_path();

		return (bool) preg_match( '/\.md\/?$/i', $uri );
	}

	private function matches_query_param(): bool
	{
		if ( empty( $this->settings['query_param_enabled'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$format = isset( $_GET['format'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['format'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$output = isset( $_GET['output'] ) ? sanitize_text_field( wp_unslash( (string) $_GET['output'] ) ) : '';

		return 'markdown' === strtolower( $format ) || 'markdown' === strtolower( $output );
	}

	private function has_rewrite_md_path(): bool
	{
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['sr_md_path'] ) && '' !== sanitize_text_field( wp_unslash( (string) $_GET['sr_md_path'] ) );
	}

	private function is_allowed_post_type( string $post_type ): bool
	{
		$allowed = $this->settings['post_types'] ?? array( 'post', 'page' );

		if ( ! is_array( $allowed ) ) {
			$allowed = array( 'post', 'page' );
		}

		if ( ! in_array( $post_type, $allowed, true ) ) {
			return false;
		}

		if ( ! is_post_type_viewable( $post_type ) ) {
			return false;
		}

		return true;
	}

	private function is_sitemap_request(): bool
	{
		$uri = strtolower( $this->get_request_path() );

		return str_contains( $uri, 'sitemap' ) && str_ends_with( $uri, '.xml' );
	}

	private function is_robots_request(): bool
	{
		$uri = strtolower( $this->get_request_path() );

		return '/robots.txt' === $uri || 'robots.txt' === ltrim( $uri, '/' );
	}

	private function is_auth_request(): bool
	{
		global $pagenow;

		if ( isset( $pagenow ) && in_array( $pagenow, array( 'wp-login.php', 'wp-register.php' ), true ) ) {
			return true;
		}

		$uri = strtolower( $this->get_request_path() );

		return str_contains( $uri, 'wp-login.php' ) || str_contains( $uri, 'wp-register.php' );
	}

	private function get_request_path(): string
	{
		$uri = $this->get_server_value( 'REQUEST_URI' );

		if ( '' === $uri ) {
			return '';
		}

		$path = wp_parse_url( $uri, PHP_URL_PATH );

		return is_string( $path ) ? $path : '';
	}

	private function get_server_value( string $key ): string
	{
		if ( ! isset( $_SERVER[ $key ] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( (string) $_SERVER[ $key ] ) );
	}
}
