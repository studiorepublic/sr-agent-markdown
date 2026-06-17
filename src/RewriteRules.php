<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class RewriteRules
{
	public const QUERY_VAR = 'sr_md_path';

	private const REWRITE_RULES_VERSION = '1.0.1';

	private const REWRITE_VERSION_OPTION = 'sr_agent_markdown_rewrite_rules_version';

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

		if ( get_option( self::REWRITE_VERSION_OPTION ) !== self::REWRITE_RULES_VERSION ) {
			flush_rewrite_rules( false );
			update_option( self::REWRITE_VERSION_OPTION, self::REWRITE_RULES_VERSION );
		}
	}

	public static function register_rewrite_rules(): void
	{
		add_rewrite_rule( '^\.md/?$', 'index.php?' . self::QUERY_VAR . '=', 'top' );
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
		if ( ! array_key_exists( self::QUERY_VAR, $wp->query_vars ) ) {
			return;
		}

		$path = trim( (string) $wp->query_vars[ self::QUERY_VAR ], '/' );

		unset( $wp->query_vars[ self::QUERY_VAR ] );

		if ( '' === $path ) {
			$this->resolve_front_page_query( $wp );
			return;
		}

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

		$post_id = url_to_postid( home_url( '/' . $resolved . '/' ) );

		if ( $post_id > 0 ) {
			$post = get_post( $post_id );

			if ( $post instanceof \WP_Post ) {
				unset( $wp->query_vars['pagename'], $wp->query_vars['name'], $wp->query_vars['p'] );

				$post_type_object = get_post_type_object( $post->post_type );
				$query_var        = is_object( $post_type_object ) && is_string( $post_type_object->query_var ) && '' !== $post_type_object->query_var
					? $post_type_object->query_var
					: $post->post_type;

				if ( 'page' === $post->post_type ) {
					$wp->query_vars['pagename'] = $resolved;
				} else {
					$wp->query_vars['post_type'] = $post->post_type;
					$wp->query_vars['name']      = $post->post_name;

					if ( 'post' !== $post->post_type && $query_var !== 'post_type' ) {
						$wp->query_vars[ $query_var ] = $post->post_name;
					}
				}

				return;
			}
		}

		$wp->query_vars['name'] = basename( $resolved );
	}

	private function resolve_front_page_query( \WP $wp ): void
	{
		if ( 'page' === get_option( 'show_on_front' ) ) {
			$page_on_front = (int) get_option( 'page_on_front' );

			if ( $page_on_front > 0 ) {
				$wp->query_vars['page_id'] = $page_on_front;
				unset( $wp->query_vars['pagename'], $wp->query_vars['name'] );

				return;
			}
		}

		unset( $wp->query_vars['page_id'], $wp->query_vars['pagename'], $wp->query_vars['name'] );
	}
}
