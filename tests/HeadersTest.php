<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/wordpress.php';

use PHPUnit\Framework\TestCase;
use SRAgentMarkdown\Headers;
use SRAgentMarkdown\SettingsPage;

final class HeadersTest extends TestCase
{
	protected function setUp(): void
	{
		sr_md_reset_test_globals();
	}

	public function test_builds_homepage_markdown_link_header(): void
	{
		$GLOBALS['sr_md_test_is_singular']   = false;
		$GLOBALS['sr_md_test_is_front_page'] = true;

		$headers = new Headers( SettingsPage::default_settings() );

		$this->assertSame(
			'Link: <https://example.com/.md>; rel="alternate"; type="text/markdown"',
			$headers->get_alternate_markdown_link_header()
		);
	}

	public function test_builds_page_markdown_link_header(): void
	{
		$GLOBALS['sr_md_test_is_singular'] = true;
		$GLOBALS['sr_md_test_permalink']   = 'https://example.com/about/';

		$headers = new Headers( SettingsPage::default_settings() );

		$this->assertSame(
			'Link: <https://example.com/about.md>; rel="alternate"; type="text/markdown"',
			$headers->get_alternate_markdown_link_header()
		);
	}
}
