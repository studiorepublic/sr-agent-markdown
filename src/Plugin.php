<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class Plugin
{
	private static ?self $instance = null;

	private RequestDetector $request_detector;

	private OutputBuffer $output_buffer;

	private Cache $cache;

	private Headers $headers;

	private RewriteRules $rewrite_rules;

	private SettingsPage $settings_page;

	private YoastCompatibility $yoast;

	private bool $markdown_request = false;

	private bool $buffer_started = false;

	/**
	 * @var array<string, mixed>
	 */
	private array $settings;

	private function __construct()
	{
		$this->settings         = get_option( SettingsPage::OPTION_KEY, SettingsPage::default_settings() );
		$this->yoast            = new YoastCompatibility();
		$this->request_detector = new RequestDetector( $this->settings, $this->yoast );
		$this->cache            = new Cache( $this->settings );
		$this->headers          = new Headers( $this->settings );
		$this->rewrite_rules    = new RewriteRules( $this->settings );
		$this->output_buffer    = new OutputBuffer(
			$this->settings,
			new HtmlExtractor( $this->settings ),
			new MarkdownConverter( $this->settings, $this->yoast ),
			$this->cache,
			$this->headers
		);
		$this->settings_page    = new SettingsPage( $this->cache );
	}

	public static function init(): void
	{
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->register_hooks();
		}
	}

	public static function activate(): void
	{
		if ( ! get_option( SettingsPage::OPTION_KEY ) ) {
			add_option( SettingsPage::OPTION_KEY, SettingsPage::default_settings() );
		}

		RewriteRules::register_rewrite_rules();
		flush_rewrite_rules();
	}

	public static function deactivate(): void
	{
		flush_rewrite_rules();
	}

	private function register_hooks(): void
	{
		$this->rewrite_rules->register();
		$this->settings_page->register();
		$this->cache->register_invalidation_hooks();

		add_action( 'parse_request', array( $this, 'handle_parse_request' ), 1 );
		add_action( 'template_redirect', array( $this, 'handle_cache_hit' ), 0 );
		add_action( 'template_redirect', array( $this, 'maybe_start_buffer' ), 1 );
		add_action( 'shutdown', array( $this, 'handle_shutdown' ), 0 );
		add_action( 'send_headers', array( $this, 'handle_send_headers' ) );
		add_action( 'wp_head', array( $this, 'handle_wp_head' ), 1 );
	}

	public function handle_parse_request( \WP $wp ): void
	{
		$this->rewrite_rules->normalize_md_request( $wp );
	}

	public function handle_cache_hit(): void
	{
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->request_detector->is_markdown_request() ) {
			return;
		}

		if ( ! $this->request_detector->is_eligible() ) {
			return;
		}

		$this->markdown_request = true;

		if ( ! $this->is_caching_enabled() ) {
			return;
		}

		$cached = $this->cache->get( $this->cache_context() );

		if ( null === $cached ) {
			return;
		}

		$this->headers->send_markdown_headers();
		echo $cached; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	public function maybe_start_buffer(): void
	{
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( ! $this->request_detector->is_markdown_request() ) {
			return;
		}

		if ( ! $this->request_detector->is_eligible() ) {
			return;
		}

		$this->markdown_request = true;

		if ( $this->output_buffer->start() ) {
			$this->buffer_started = true;
		}
	}

	public function handle_shutdown(): void
	{
		if ( ! $this->buffer_started ) {
			return;
		}

		$this->output_buffer->finish_and_output( $this->cache_context(), $this->request_detector->is_explicit_markdown_route() );
	}

	public function handle_send_headers(): void
	{
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( $this->request_detector->is_markdown_request() && $this->request_detector->is_eligible() ) {
			$this->headers->send_markdown_headers();

			return;
		}

		if ( $this->markdown_request || $this->buffer_started ) {
			return;
		}

		if ( ! $this->request_detector->is_eligible_for_alternate() ) {
			return;
		}

		$this->headers->send_html_alternate_headers();
	}

	public function handle_wp_head(): void
	{
		if ( ! $this->is_enabled() ) {
			return;
		}

		if ( empty( $this->settings['head_alternate_link'] ) ) {
			return;
		}

		if ( $this->markdown_request || $this->buffer_started ) {
			return;
		}

		if ( ! $this->request_detector->is_eligible_for_alternate() ) {
			return;
		}

		$url = $this->headers->get_markdown_url();

		if ( '' === $url ) {
			return;
		}

		printf(
			'<link rel="alternate" type="text/markdown" href="%s" />' . "\n",
			esc_url( $url )
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function cache_context(): array
	{
		return array(
			'post_id'          => get_queried_object_id(),
			'post_modified_gmt'=> $this->get_post_modified_gmt(),
			'canonical_url'    => $this->headers->get_canonical_url(),
			'language'         => $this->get_language_code(),
		);
	}

	private function get_post_modified_gmt(): string
	{
		$post_id = get_queried_object_id();

		if ( $post_id <= 0 ) {
			return '';
		}

		$modified = get_post_field( 'post_modified_gmt', $post_id );

		return is_string( $modified ) ? $modified : '';
	}

	private function get_language_code(): string
	{
		if ( function_exists( 'pll_current_language' ) ) {
			$lang = pll_current_language( 'slug' );

			return is_string( $lang ) ? $lang : '';
		}

		if ( defined( 'ICL_LANGUAGE_CODE' ) && is_string( ICL_LANGUAGE_CODE ) ) {
			return ICL_LANGUAGE_CODE;
		}

		return '';
	}

	private function is_enabled(): bool
	{
		return ! empty( $this->settings['enabled'] );
	}

	private function is_caching_enabled(): bool
	{
		return ! empty( $this->settings['caching_enabled'] );
	}
}
