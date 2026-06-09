<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/wordpress.php';

use PHPUnit\Framework\TestCase;
use SRAgentMarkdown\RequestDetector;
use SRAgentMarkdown\SettingsPage;
use SRAgentMarkdown\YoastCompatibility;

final class RequestDetectorTest extends TestCase
{
	protected function setUp(): void
	{
		sr_md_reset_test_globals();
	}

	public function test_detects_accept_header(): void
	{
		$_SERVER['HTTP_ACCEPT'] = 'text/html, text/markdown;q=0.9';
		$detector               = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertTrue( $detector->is_markdown_request() );
		$this->assertTrue( $this->invoke_private( $detector, 'matches_accept_header' ) );
	}

	public function test_detects_md_url(): void
	{
		$_SERVER['REQUEST_URI'] = '/about.md';
		$detector               = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertTrue( $detector->is_markdown_request() );
		$this->assertTrue( $this->invoke_private( $detector, 'matches_md_url' ) );
	}

	public function test_detects_query_params(): void
	{
		$_GET['format'] = 'markdown';
		$detector       = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertTrue( $detector->is_markdown_request() );
		$this->assertTrue( $this->invoke_private( $detector, 'matches_query_param' ) );
	}

	public function test_excludes_admin_requests(): void
	{
		$GLOBALS['sr_md_test_is_admin'] = true;
		$_SERVER['HTTP_ACCEPT']         = 'text/markdown';
		$detector                       = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertFalse( $detector->is_eligible() );
	}

	public function test_excludes_logged_in_users_when_enabled(): void
	{
		$GLOBALS['sr_md_test_logged_in'] = true;
		$_SERVER['HTTP_ACCEPT']          = 'text/markdown';
		$detector                        = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertFalse( $detector->is_eligible() );
	}

	public function test_excludes_noindex_pages_when_enabled(): void
	{
		$GLOBALS['sr_md_test_queried_id'] = 10;
		$GLOBALS['sr_md_test_post_meta']  = array(
			10 => array(
				'_yoast_wpseo_meta-robots-noindex' => '1',
			),
		);
		$_SERVER['HTTP_ACCEPT']           = 'text/markdown';
		$detector                         = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertFalse( $detector->is_eligible() );
	}

	public function test_allows_singular_page_when_eligible(): void
	{
		$_SERVER['HTTP_ACCEPT'] = 'text/markdown';
		$detector               = new RequestDetector( SettingsPage::default_settings(), new YoastCompatibility() );

		$this->assertTrue( $detector->is_eligible() );
		$this->assertTrue( $detector->is_eligible_for_alternate() );
	}

	private function invoke_private( RequestDetector $detector, string $method ): bool
	{
		$reflection = new \ReflectionMethod( RequestDetector::class, $method );
		$reflection->setAccessible( true );

		return (bool) $reflection->invoke( $detector );
	}
}
