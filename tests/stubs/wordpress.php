<?php

declare(strict_types=1);

if ( ! defined( 'SR_AGENT_MARKDOWN_VERSION' ) ) {
	define( 'SR_AGENT_MARKDOWN_VERSION', '1.0.0' );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ): string {
		return trim( strip_tags( (string) $value ) );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ) {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'wp_trim_words' ) ) {
	function wp_trim_words( string $text, int $num_words = 55 ): string {
		$words = preg_split( '/\s+/', trim( $text ) ) ?: array();
		return implode( ' ', array_slice( $words, 0, $num_words ) );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ): string {
		return (string) json_encode( $data );
	}
}

if ( ! function_exists( 'is_admin' ) ) {
	function is_admin(): bool {
		return $GLOBALS['sr_md_test_is_admin'] ?? false;
	}
}

if ( ! function_exists( 'wp_doing_ajax' ) ) {
	function wp_doing_ajax(): bool {
		return $GLOBALS['sr_md_test_doing_ajax'] ?? false;
	}
}

if ( ! function_exists( 'is_feed' ) ) {
	function is_feed(): bool {
		return $GLOBALS['sr_md_test_is_feed'] ?? false;
	}
}

if ( ! function_exists( 'is_preview' ) ) {
	function is_preview(): bool {
		return $GLOBALS['sr_md_test_is_preview'] ?? false;
	}
}

if ( ! function_exists( 'is_404' ) ) {
	function is_404(): bool {
		return $GLOBALS['sr_md_test_is_404'] ?? false;
	}
}

if ( ! function_exists( 'is_search' ) ) {
	function is_search(): bool {
		return $GLOBALS['sr_md_test_is_search'] ?? false;
	}
}

if ( ! function_exists( 'is_archive' ) ) {
	function is_archive(): bool {
		return $GLOBALS['sr_md_test_is_archive'] ?? false;
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular(): bool {
		return $GLOBALS['sr_md_test_is_singular'] ?? true;
	}
}

if ( ! function_exists( 'is_front_page' ) ) {
	function is_front_page(): bool {
		return $GLOBALS['sr_md_test_is_front_page'] ?? false;
	}
}

if ( ! function_exists( 'is_home' ) ) {
	function is_home(): bool {
		return $GLOBALS['sr_md_test_is_home'] ?? false;
	}
}

if ( ! function_exists( 'post_password_required' ) ) {
	function post_password_required(): bool {
		return $GLOBALS['sr_md_test_password_required'] ?? false;
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post(): ?object {
		return $GLOBALS['sr_md_test_post'] ?? null;
	}
}

if ( ! function_exists( 'is_post_type_viewable' ) ) {
	function is_post_type_viewable( string $post_type ): bool {
		return in_array( $post_type, array( 'post', 'page' ), true );
	}
}

if ( ! function_exists( 'is_user_logged_in' ) ) {
	function is_user_logged_in(): bool {
		return $GLOBALS['sr_md_test_logged_in'] ?? false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return $GLOBALS['sr_md_test_can_manage'] ?? false;
	}
}

if ( ! function_exists( 'get_queried_object_id' ) ) {
	function get_queried_object_id(): int {
		return (int) ( $GLOBALS['sr_md_test_queried_id'] ?? 0 );
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		return $GLOBALS['sr_md_test_post_meta'][ $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'wp_get_document_title' ) ) {
	function wp_get_document_title(): string {
		return (string) ( $GLOBALS['sr_md_test_document_title'] ?? 'Test Page' );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post = 0 ): string {
		return (string) ( $GLOBALS['sr_md_test_permalink'] ?? 'https://example.com/about/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://example.com' . $path;
	}
}

if ( ! function_exists( 'has_excerpt' ) ) {
	function has_excerpt( $post = null ): bool {
		return ! empty( $GLOBALS['sr_md_test_excerpt'] );
	}
}

if ( ! function_exists( 'get_the_excerpt' ) ) {
	function get_the_excerpt( $post = null ): string {
		return (string) ( $GLOBALS['sr_md_test_excerpt'] ?? '' );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $string ): string {
		return rtrim( $string, '/\\' );
	}
}

if ( ! function_exists( 'user_trailingslashit' ) ) {
	function user_trailingslashit( string $string ): string {
		return rtrim( $string, '/' ) . '/';
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public string $post_type = 'page';
		public string $post_status = 'publish';
		public string $post_content = 'Sample content.';
	}
}

function sr_md_reset_test_globals(): void {
	$GLOBALS['sr_md_test_is_admin']           = false;
	$GLOBALS['sr_md_test_doing_ajax']         = false;
	$GLOBALS['sr_md_test_is_feed']            = false;
	$GLOBALS['sr_md_test_is_preview']         = false;
	$GLOBALS['sr_md_test_is_404']             = false;
	$GLOBALS['sr_md_test_is_search']          = false;
	$GLOBALS['sr_md_test_is_archive']         = false;
	$GLOBALS['sr_md_test_is_singular']        = true;
	$GLOBALS['sr_md_test_is_front_page']      = false;
	$GLOBALS['sr_md_test_is_home']            = false;
	$GLOBALS['sr_md_test_password_required']  = false;
	$GLOBALS['sr_md_test_logged_in']          = false;
	$GLOBALS['sr_md_test_can_manage']         = false;
	$GLOBALS['sr_md_test_queried_id']         = 1;
	$GLOBALS['sr_md_test_post_meta']          = array();
	$GLOBALS['sr_md_test_document_title']     = 'About Us';
	$GLOBALS['sr_md_test_permalink']          = 'https://example.com/about/';
	$GLOBALS['sr_md_test_excerpt']            = 'A short excerpt.';
	$GLOBALS['sr_md_test_post']               = new WP_Post();
	$_SERVER                                    = array();
	$_GET                                       = array();
}

sr_md_reset_test_globals();
