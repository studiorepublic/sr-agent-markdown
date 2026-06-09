# SR Agent Markdown

WordPress plugin that serves Markdown representations of public pages for AI agents.

Markdown is produced only from captured front-end HTML after theme and template rendering. The plugin does **not** read ACF fields, flexible content, or post meta directly for body content.

## Requirements

- PHP 8.1+
- WordPress 6.5+
- Composer dependency: [`league/html-to-markdown`](https://github.com/thephpleague/html-to-markdown)

## Installation

1. Copy this folder to `wp-content/plugins/sr-agent-markdown/`.
2. If `vendor/` is missing, run:

```bash
cd wp-content/plugins/sr-agent-markdown
composer install --no-dev
```

For development and tests:

```bash
composer install
```

3. Activate **SR Agent Markdown** in wp-admin.
4. Visit **Settings → Agent Markdown** to configure detection, content selectors, safety rules, caching, and debug options.

On activation the plugin registers `.md` rewrite rules and flushes permalinks.

## How it works

1. **Request detection** — Markdown is returned when any enabled method matches:
   - `Accept: text/markdown`
   - URL ending in `.md` (for example `/about.md`)
   - `?format=markdown` or `?output=markdown`
2. **Eligibility checks** — Admin, REST, AJAX, feeds, sitemaps, robots, cron, auth, previews, password-protected posts, private drafts, and other excluded contexts are skipped.
3. **Output buffering** — Eligible Markdown requests render the normal theme template into a buffer.
4. **HTML extraction** — `HtmlExtractor` uses DOMDocument/XPath to find the main content node and remove nav, footer, scripts, and configured exclusions.
5. **Conversion** — `league/html-to-markdown` converts cleaned HTML to Markdown with optional title, canonical URL, excerpt/Yoast description, and featured image from extracted HTML only.
6. **Caching** — Results are stored in transients and invalidated on post saves, theme switches, settings changes, ACF saves, and permalink updates.
7. **Discovery** — HTML responses can expose `Link: <url.md>; rel="alternate"; type="text/markdown"` and a `<link rel="alternate" type="text/markdown">` tag in `wp_head`.

## Settings

Default option key: `sr_agent_markdown_settings`

Sections:

- **General** — Enable plugin, detection methods, link headers, caching, TTL
- **Content** — Post types, content/excluded selectors, front matter toggles
- **Safety** — Logged-in users, Yoast noindex, 404/search/archive handling
- **Debug** — Error logging and HTTP 500 on explicit Markdown routes when conversion fails

Tools:

- Preview Markdown for homepage (`?format=markdown`)
- Clear Markdown cache

## Manual curl tests

Replace `https://example.com` with your site URL.

```bash
# Markdown via Accept header
curl -I -H "Accept: text/markdown" https://example.com/about/

# HTML with alternate discovery headers
curl -I https://example.com/about/

# Virtual .md URL
curl -I https://example.com/about.md

# Markdown body
curl -H "Accept: text/markdown" https://example.com/about/

# Query parameter route
curl "https://example.com/about/?format=markdown"

# Sanity check: no ACF/meta leakage in output
curl -H "Accept: text/markdown" https://example.com/services/charity-web-design/ | grep -iE 'acf|field_|flexible_content|post_meta'
```

Expected results:

- Markdown responses use `Content-Type: text/markdown; charset=utf-8`
- HTML responses include `Vary: Accept`
- When enabled, HTML responses include a `Link` alternate header
- Markdown output excludes navigation, cookie banners, and footer chrome
- Saving a post clears cached Markdown

## Cloudflare notes

- Send `Vary: Accept` through to origin and cache HTML and Markdown separately. Do not serve cached HTML to requests that advertise `Accept: text/markdown`.
- Page Rules or Cache Rules should bypass cache or use distinct cache keys for Markdown requests.
- Optional Worker pattern (future enhancement):

```javascript
async function handleRequest(request) {
  const accept = request.headers.get('Accept') || '';
  if (accept.includes('text/markdown')) {
    const url = new URL(request.url);
    if (!url.pathname.endsWith('.md')) {
      request = new Request(request, {
        headers: { ...Object.fromEntries(request.headers), Accept: 'text/markdown' },
      });
    }
  }
  return fetch(request);
}
```

## Development

Run unit tests:

```bash
cd wp-content/plugins/sr-agent-markdown
composer install
vendor/bin/phpunit
```

## Limitations

- Archive, search, and 404 Markdown are disabled by default.
- Content rendered only by client-side JavaScript will not appear in Markdown.
- Themes with multiple `<main>` elements may need custom selector tuning.
- Very large HTML documents may hit memory or time limits during DOM parsing.
- Password-protected and logged-in views are excluded by default.
- ACF frontmatter whitelisting is deferred to a future version.
- On conversion failure the plugin falls back to the captured HTML unless debug mode is enabled for explicit Markdown routes (`.md`, query params).

## Fail-safe behaviour

If Composer dependencies are missing, admins see a dashboard notice and the front-end no-ops safely.
