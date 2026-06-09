<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class RewriteRules
{
	public const QUERY_VAR = 'sr_md_path';

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

	public function register(): void
	{
		add_action( 'init', array( $this, 'add_rewrite_rules' ) );
		add_filter( 'query_vars', array( $this, 'add_query_vars' ) );
		add_action( 'permalink_structure_changed', array( $this, 'flush_rules' ) );
	}

	public function add_rewrite_rules(): void
	{
		if ( empty( $this->settings['md_urls_enabled'] ) ) {
			return;
		}

		self::register_rewrite_rules();
	}

	public static function register_rewrite_rules(): void
	{
		add_rewrite_rule( '(.+?)\.md/?$', 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/**
	 * @param array<int, string> $vars
	 * @return array<int, string>
	 */
	public function add_query_vars( array $vars ): array
	{
		$vars[] = self::QUERY_VAR;

		return $vars;
	}

	public function flush_rules(): void
	{
		self::register_rewrite_rules();
		flush_rewrite_rules();
	}

	public function normalize_md_request( \WP $wp ): void
	{
		if ( empty( $wp->query_vars[ self::QUERY_VAR ] ) ) {
			return;
		}

		$path = trim( (string) $wp->query_vars[ self::QUERY_VAR ], '/' );

		if ( '' === $path ) {
			return;
		}

		unset( $wp->query_vars[ self::QUERY_VAR ] );

		$segments = explode( '/', $path );
		$last     = array_pop( $segments );

		if ( is_string( $last ) && str_ends_with( strtolower( $last ), '.md' ) ) {
			$last = substr( $last, 0, -3 );
		}

		if ( '' !== $last ) {
			$segments[] = $last;
		}

		$resolved = implode( '/', $segments );

		if ( '' === $resolved ) {
			$wp->query_vars['pagename'] = '';
			return;
		}

		$page = get_page_by_path( $resolved );

		if ( $page instanceof \WP_Post ) {
			$wp->query_vars['pagename'] = $resolved;
			return;
		}

		$wp->query_vars['name'] = basename( $resolved );
	}
}
