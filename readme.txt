=== SR Agent Markdown ===
Contributors: srwebsite
Tags: markdown, ai, agents, seo, content
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Serve Markdown representations of rendered public WordPress pages for AI agents.

== Description ==

SR Agent Markdown detects Markdown requests via the Accept header, virtual `.md` URLs, or query parameters. It renders the normal public page, captures the HTML output buffer, extracts the main content region, converts it to Markdown, caches the result, and advertises Markdown alternates on HTML responses.

Markdown is always generated from captured front-end HTML. The plugin never reads ACF fields or post meta directly for content generation.

== Installation ==

1. Upload the `sr-agent-markdown` folder to `/wp-content/plugins/`.
2. Ensure `vendor/` is present. If not, run `composer install` inside the plugin directory.
3. Activate the plugin through the Plugins screen.
4. Configure options under Settings → Agent Markdown.

== Frequently Asked Questions ==

= How do agents request Markdown? =

Send `Accept: text/markdown`, visit a `.md` URL such as `/about.md`, or append `?format=markdown`.

= Does this replace llms.txt? =

No. This plugin does not modify `/llms.txt`.

== Changelog ==

= 1.0.0 =
* Initial release.
