<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class SettingsPage
{
	public const OPTION_KEY = 'sr_agent_markdown_settings';

	public const PAGE_SLUG = 'sr-agent-markdown';

	private Cache $cache;

	public function __construct( Cache $cache )
	{
		$this->cache = $cache;
	}

	public function register(): void
	{
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_sr_agent_markdown_clear_cache', array( $this, 'handle_clear_cache' ) );
	}

	public function register_menu(): void
	{
		add_options_page(
			__( 'Agent Markdown', 'sr-agent-markdown' ),
			__( 'Agent Markdown', 'sr-agent-markdown' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void
	{
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	public function enqueue_assets( string $hook_suffix ): void
	{
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'sr-agent-markdown-admin',
			SR_AGENT_MARKDOWN_URL . 'assets/admin.css',
			array(),
			SR_AGENT_MARKDOWN_VERSION
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function default_settings(): array
	{
		return array(
			'enabled'                  => true,
			'accept_header_enabled'    => true,
			'md_urls_enabled'          => true,
			'query_param_enabled'      => true,
			'link_headers_enabled'     => true,
			'head_alternate_link'      => true,
			'caching_enabled'          => true,
			'cache_ttl'                => DAY_IN_SECONDS,
			'post_types'               => array( 'post', 'page' ),
			'content_selectors'        => 'main, article, .entry-content, .site-main, #content, body',
			'excluded_selectors'       => '',
			'include_h1'               => true,
			'include_canonical'        => true,
			'include_excerpt'          => true,
			'include_featured_image'   => false,
			'featured_image_position'  => 'append',
			'disable_logged_in'        => true,
			'disable_noindex'          => true,
			'allow_404'                => false,
			'allow_search'             => false,
			'allow_archives'           => false,
			'debug_enabled'            => false,
		);
	}

	/**
	 * @param array<string, mixed>|mixed $input
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ): array
	{
		$defaults = self::default_settings();
		$input    = is_array( $input ) ? $input : array();
		$output   = $defaults;

		$checkboxes = array(
			'enabled',
			'accept_header_enabled',
			'md_urls_enabled',
			'query_param_enabled',
			'link_headers_enabled',
			'head_alternate_link',
			'caching_enabled',
			'include_h1',
			'include_canonical',
			'include_excerpt',
			'include_featured_image',
			'disable_logged_in',
			'disable_noindex',
			'allow_404',
			'allow_search',
			'allow_archives',
			'debug_enabled',
		);

		foreach ( $checkboxes as $key ) {
			$output[ $key ] = ! empty( $input[ $key ] );
		}

		$output['cache_ttl'] = max( 60, absint( $input['cache_ttl'] ?? $defaults['cache_ttl'] ) );

		$post_types = $input['post_types'] ?? array();

		if ( is_string( $post_types ) ) {
			$post_types = array_map( 'trim', explode( ',', $post_types ) );
		}

		$allowed_post_types = array_keys( self::get_viewable_post_types() );
		$sanitized          = array();

		foreach ( is_array( $post_types ) ? $post_types : array() as $type ) {
			$type = sanitize_key( (string) $type );

			if ( '' === $type || ! in_array( $type, $allowed_post_types, true ) ) {
				continue;
			}

			$sanitized[] = $type;
		}

		$output['post_types'] = array_values( array_unique( $sanitized ) );

		if ( empty( $output['post_types'] ) ) {
			$output['post_types'] = $defaults['post_types'];
		}

		$output['content_selectors']  = sanitize_text_field( (string) ( $input['content_selectors'] ?? $defaults['content_selectors'] ) );
		$output['excluded_selectors']   = sanitize_text_field( (string) ( $input['excluded_selectors'] ?? $defaults['excluded_selectors'] ) );
		$output['featured_image_position'] = in_array( $input['featured_image_position'] ?? 'append', array( 'prepend', 'append' ), true )
			? (string) $input['featured_image_position']
			: 'append';

		return $output;
	}

	public function handle_clear_cache(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to clear the cache.', 'sr-agent-markdown' ) );
		}

		check_admin_referer( 'sr_agent_markdown_clear_cache' );

		$this->cache->clear_all();

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'             => self::PAGE_SLUG,
					'sr_md_cache'      => 'cleared',
				),
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	public function render_page(): void
	{
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = get_option( self::OPTION_KEY, self::default_settings() );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['sr_md_cache'] ) && 'cleared' === sanitize_text_field( wp_unslash( (string) $_GET['sr_md_cache'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Markdown cache cleared.', 'sr-agent-markdown' ) . '</p></div>';
		}

		$preview_url = '';

		if ( is_admin() ) {
			$front = home_url( '/' );
			$preview_url = add_query_arg( 'format', 'markdown', $front );
		}
		?>
		<div class="wrap sr-agent-markdown-settings">
			<h1><?php echo esc_html__( 'Agent Markdown', 'sr-agent-markdown' ); ?></h1>
			<p class="description"><?php echo esc_html__( 'Serve Markdown representations of rendered public pages for AI agents.', 'sr-agent-markdown' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( self::PAGE_SLUG ); ?>

				<h2><?php echo esc_html__( 'General', 'sr-agent-markdown' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $this->render_checkbox_row( 'enabled', __( 'Enable plugin', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'accept_header_enabled', __( 'Detect Accept: text/markdown', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'md_urls_enabled', __( 'Enable .md virtual URLs', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'query_param_enabled', __( 'Enable ?format=markdown and ?output=markdown', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'link_headers_enabled', __( 'Send Link alternate header on HTML responses', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'head_alternate_link', __( 'Add wp_head alternate link tag', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'caching_enabled', __( 'Enable transient caching', 'sr-agent-markdown' ), $settings ); ?>
					<tr>
						<th scope="row"><label for="cache_ttl"><?php echo esc_html__( 'Cache TTL (seconds)', 'sr-agent-markdown' ); ?></label></th>
						<td><input type="number" min="60" step="1" class="small-text" id="cache_ttl" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cache_ttl]" value="<?php echo esc_attr( (string) ( $settings['cache_ttl'] ?? DAY_IN_SECONDS ) ); ?>" /></td>
					</tr>
				</table>

				<h2><?php echo esc_html__( 'Content', 'sr-agent-markdown' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $this->render_post_types_row( $settings ); ?>
					<tr>
						<th scope="row"><label for="content_selectors"><?php echo esc_html__( 'Content selectors', 'sr-agent-markdown' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="content_selectors" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[content_selectors]" value="<?php echo esc_attr( (string) ( $settings['content_selectors'] ?? '' ) ); ?>" />
							<p class="description"><?php echo esc_html__( 'Comma-separated CSS selectors tried in order.', 'sr-agent-markdown' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="excluded_selectors"><?php echo esc_html__( 'Excluded selectors', 'sr-agent-markdown' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="excluded_selectors" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[excluded_selectors]" value="<?php echo esc_attr( (string) ( $settings['excluded_selectors'] ?? '' ) ); ?>" />
						</td>
					</tr>
					<?php $this->render_checkbox_row( 'include_h1', __( 'Prepend document title as H1', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'include_canonical', __( 'Include Source: canonical URL', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'include_excerpt', __( 'Include Yoast meta description or excerpt', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'include_featured_image', __( 'Include first image from extracted HTML', 'sr-agent-markdown' ), $settings ); ?>
				</table>

				<h2><?php echo esc_html__( 'Safety', 'sr-agent-markdown' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $this->render_checkbox_row( 'disable_logged_in', __( 'Disable Markdown for logged-in users', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'disable_noindex', __( 'Disable for Yoast noindex pages', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'allow_404', __( 'Allow 404 pages', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'allow_search', __( 'Allow search results', 'sr-agent-markdown' ), $settings ); ?>
					<?php $this->render_checkbox_row( 'allow_archives', __( 'Allow archive pages', 'sr-agent-markdown' ), $settings ); ?>
				</table>

				<h2><?php echo esc_html__( 'Debug', 'sr-agent-markdown' ); ?></h2>
				<table class="form-table" role="presentation">
					<?php $this->render_checkbox_row( 'debug_enabled', __( 'Enable debug logging and explicit-route 500 errors', 'sr-agent-markdown' ), $settings ); ?>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr />

			<h2><?php echo esc_html__( 'Tools', 'sr-agent-markdown' ); ?></h2>
			<p>
				<a class="button button-secondary" href="<?php echo esc_url( $preview_url ); ?>" target="_blank" rel="noopener noreferrer">
					<?php echo esc_html__( 'Preview Markdown for homepage', 'sr-agent-markdown' ); ?>
				</a>
			</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'sr_agent_markdown_clear_cache' ); ?>
				<input type="hidden" name="action" value="sr_agent_markdown_clear_cache" />
				<?php submit_button( __( 'Clear Markdown cache', 'sr-agent-markdown' ), 'delete', 'submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * @return array<string, \WP_Post_Type>
	 */
	public static function get_viewable_post_types(): array
	{
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$viewable = array();

		foreach ( $post_types as $name => $post_type ) {
			if ( is_post_type_viewable( $name ) ) {
				$viewable[ $name ] = $post_type;
			}
		}

		uasort(
			$viewable,
			static function ( \WP_Post_Type $a, \WP_Post_Type $b ): int {
				return strcasecmp( $a->labels->singular_name, $b->labels->singular_name );
			}
		);

		return $viewable;
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function render_post_types_row( array $settings ): void
	{
		$selected   = (array) ( $settings['post_types'] ?? array() );
		$post_types = self::get_viewable_post_types();
		?>
		<tr>
			<th scope="row"><?php echo esc_html__( 'Post types', 'sr-agent-markdown' ); ?></th>
			<td>
				<fieldset class="post-type-checkboxes">
					<legend class="screen-reader-text"><?php echo esc_html__( 'Post types', 'sr-agent-markdown' ); ?></legend>
					<?php if ( empty( $post_types ) ) : ?>
						<p><?php echo esc_html__( 'No public post types found.', 'sr-agent-markdown' ); ?></p>
					<?php else : ?>
						<?php foreach ( $post_types as $name => $post_type ) : ?>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( self::OPTION_KEY ); ?>[post_types][]"
									value="<?php echo esc_attr( $name ); ?>"
									<?php checked( in_array( $name, $selected, true ) ); ?>
								/>
								<?php echo esc_html( $post_type->labels->singular_name ); ?>
								<code><?php echo esc_html( $name ); ?></code>
							</label>
						<?php endforeach; ?>
					<?php endif; ?>
				</fieldset>
				<p class="description"><?php echo esc_html__( 'Select which public post types may return Markdown.', 'sr-agent-markdown' ); ?></p>
			</td>
		</tr>
		<?php
	}

	/**
	 * @param array<string, mixed> $settings
	 */
	private function render_checkbox_row( string $key, string $label, array $settings ): void
	{
		$checked = ! empty( $settings[ $key ] );
		?>
		<tr>
			<th scope="row"><?php echo esc_html( $label ); ?></th>
			<td>
				<label>
					<input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( $checked ); ?> />
					<?php echo esc_html__( 'Enabled', 'sr-agent-markdown' ); ?>
				</label>
			</td>
		</tr>
		<?php
	}
}
