<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/wordpress.php';

use PHPUnit\Framework\TestCase;
use SRAgentMarkdown\HtmlExtractor;
use SRAgentMarkdown\SettingsPage;

final class HtmlExtractorTest extends TestCase
{
	protected function setUp(): void
	{
		sr_md_reset_test_globals();
	}

	public function test_prefers_main_content_and_removes_navigation(): void
	{
		$html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<header class="site-header"><nav>Skip this navigation menu with many links</nav></header>
<main>
<h1>About Us</h1>
<p>This is the primary page content that should be extracted for Markdown conversion output.</p>
</main>
<footer class="site-footer">Cookie banner and footer links should be removed from output.</footer>
</body>
</html>
HTML;

		$extractor = new HtmlExtractor( SettingsPage::default_settings() );
		$result    = $extractor->extract( $html );

		$this->assertStringContainsString( 'About Us', $result );
		$this->assertStringContainsString( 'primary page content', $result );
		$this->assertStringNotContainsString( 'Skip this navigation', $result );
		$this->assertStringNotContainsString( 'Cookie banner', $result );
	}

	public function test_falls_back_to_article_selector(): void
	{
		$html = <<<'HTML'
<!DOCTYPE html>
<html>
<body>
<article>
<h2>Services</h2>
<p>We provide charity web design and digital support for nonprofit organisations across the UK.</p>
</article>
</body>
</html>
HTML;

		$extractor = new HtmlExtractor( SettingsPage::default_settings() );
		$result    = $extractor->extract( $html );

		$this->assertStringContainsString( 'charity web design', $result );
	}

	public function test_returns_empty_for_meaningless_content(): void
	{
		$html = '<!DOCTYPE html><html><body><main>Short</main></body></html>';

		$extractor = new HtmlExtractor( SettingsPage::default_settings() );
		$result    = $extractor->extract( $html );

		$this->assertSame( '', $result );
	}
}
