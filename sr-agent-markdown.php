<?php
/**
 * Plugin Name: SR Agent Markdown
 * Plugin URI: https://github.com/studiorepublic/sr-agent-markdown
 * Description: Serves Markdown representations of public pages for AI agents via Accept header, .md URLs, or query parameters.
 * Version: 1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: SR Website
 * License: GPL-2.0-or-later
 * Text Domain: sr-agent-markdown
 *
 * @package SRAgentMarkdown
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'SR_AGENT_MARKDOWN_VERSION', '1.0.0' );
define( 'SR_AGENT_MARKDOWN_PATH', plugin_dir_path( __FILE__ ) );
define( 'SR_AGENT_MARKDOWN_URL', plugin_dir_url( __FILE__ ) );
define( 'SR_AGENT_MARKDOWN_BASENAME', plugin_basename( __FILE__ ) );

$sr_agent_markdown_autoload = SR_AGENT_MARKDOWN_PATH . 'vendor/autoload.php';

if ( ! file_exists( $sr_agent_markdown_autoload ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			printf(
				'<div class="notice notice-error"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'SR Agent Markdown:', 'sr-agent-markdown' ),
				esc_html__( 'Composer dependencies are missing. Run composer install in the plugin directory.', 'sr-agent-markdown' )
			);
		}
	);
	return;
}

require_once $sr_agent_markdown_autoload;

if ( class_exists( 'YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
	$sr_agent_markdown_repo_url = apply_filters(
		'sr_agent_markdown_update_repo_url',
		'https://github.com/studiorepublic/sr-agent-markdown'
	);

	$sr_agent_markdown_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		$sr_agent_markdown_repo_url,
		__FILE__,
		'sr-agent-markdown'
	);

	$sr_agent_markdown_update_checker->getVcsApi()->enableReleaseAssets( '/\.zip$/' );
}

register_activation_hook(
	__FILE__,
	static function (): void {
		SRAgentMarkdown\Plugin::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		SRAgentMarkdown\Plugin::deactivate();
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		SRAgentMarkdown\Plugin::init();
	}
);
