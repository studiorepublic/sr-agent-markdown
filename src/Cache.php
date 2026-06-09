<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class Cache
{
	public const TRANSIENT_PREFIX = 'sr_md_';

	public const KEY_INDEX_OPTION = 'sr_agent_markdown_cache_keys';

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

	public function register_invalidation_hooks(): void
	{
		add_action( 'save_post', array( $this, 'invalidate_on_post_change' ), 10, 1 );
		add_action( 'deleted_post', array( $this, 'invalidate_on_post_change' ), 10, 1 );
		add_action( 'switch_theme', array( $this, 'clear_all' ) );
		add_action( 'update_option_' . SettingsPage::OPTION_KEY, array( $this, 'clear_all' ), 10, 0 );
		add_action( 'permalink_structure_changed', array( $this, 'clear_all' ) );

		if ( function_exists( 'acf' ) ) {
			add_action( 'acf/save_post', array( $this, 'clear_all' ), 20 );
			add_action( 'acf/update_field_group', array( $this, 'clear_all' ), 20 );
		}
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function get( array $context ): ?string
	{
		if ( empty( $this->settings['caching_enabled'] ) ) {
			return null;
		}

		$key      = $this->build_key( $context );
		$markdown = get_transient( $key );

		return is_string( $markdown ) && '' !== $markdown ? $markdown : null;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function set( array $context, string $markdown ): void
	{
		$key = $this->build_key( $context );
		$ttl = max( 60, (int) ( $this->settings['cache_ttl'] ?? DAY_IN_SECONDS ) );

		set_transient( $key, $markdown, $ttl );
		$this->track_key( $key );
	}

	public function clear_all(): void
	{
		global $wpdb;

		$keys = get_option( self::KEY_INDEX_OPTION, array() );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				if ( is_string( $key ) ) {
					delete_transient( $key );
				}
			}
		}

		delete_option( self::KEY_INDEX_OPTION );

		if ( isset( $wpdb ) ) {
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
					'_transient_' . self::TRANSIENT_PREFIX . '%',
					'_transient_timeout_' . self::TRANSIENT_PREFIX . '%'
				)
			);
		}
	}

	/**
	 * @param int $post_id
	 */
	public function invalidate_on_post_change( $post_id ): void
	{
		$post_id = (int) $post_id;

		if ( $post_id <= 0 ) {
			return;
		}

		$post_type = get_post_type( $post_id );

		if ( ! is_string( $post_type ) || ! is_post_type_viewable( $post_type ) ) {
			return;
		}

		$this->clear_all();
	}

	/**
	 * @param array<string, mixed> $context
	 */
	private function build_key( array $context ): string
	{
		$payload = array(
			'canonical'  => $context['canonical_url'] ?? '',
			'post_id'    => $context['post_id'] ?? 0,
			'modified'   => $context['post_modified_gmt'] ?? '',
			'language'   => $context['language'] ?? '',
			'version'    => SR_AGENT_MARKDOWN_VERSION,
			'selectors'  => $this->settings['content_selectors'] ?? '',
			'exclusions' => $this->settings['excluded_selectors'] ?? '',
			'flags'      => array(
				'include_h1'              => ! empty( $this->settings['include_h1'] ),
				'include_canonical'       => ! empty( $this->settings['include_canonical'] ),
				'include_excerpt'         => ! empty( $this->settings['include_excerpt'] ),
				'include_featured_image'  => ! empty( $this->settings['include_featured_image'] ),
			),
		);

		return self::TRANSIENT_PREFIX . md5( wp_json_encode( $payload ) );
	}

	private function track_key( string $key ): void
	{
		$keys = get_option( self::KEY_INDEX_OPTION, array() );

		if ( ! is_array( $keys ) ) {
			$keys = array();
		}

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::KEY_INDEX_OPTION, $keys, false );
		}
	}
}
