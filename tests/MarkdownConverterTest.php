<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/wordpress.php';

use PHPUnit\Framework\TestCase;
use SRAgentMarkdown\MarkdownConverter;
use SRAgentMarkdown\SettingsPage;
use SRAgentMarkdown\YoastCompatibility;

final class MarkdownConverterTest extends TestCase
{
	protected function setUp(): void
	{
		sr_md_reset_test_globals();
	}

	public function test_preserves_headings_lists_and_links(): void
	{
		$html = <<<'HTML'
<h2>Services</h2>
<p>We help charities with:</p>
<ul>
<li>Web design</li>
<li>Fundraising</li>
</ul>
<p>Learn more on <a href="https://example.com/contact/">our contact page</a>.</p>
HTML;

		$converter = new MarkdownConverter( SettingsPage::default_settings(), new YoastCompatibility() );
		$markdown  = $converter->convert( $html );

		$this->assertStringContainsString( '## Services', $markdown );
		$this->assertStringContainsString( '- Web design', $markdown );
		$this->assertStringContainsString( '[our contact page](https://example.com/contact/)', $markdown );
	}

	public function test_prepends_front_matter_when_enabled(): void
	{
		$html = '<p>Body content with enough text to remain after conversion and front matter prepending.</p>';

		$converter = new MarkdownConverter( SettingsPage::default_settings(), new YoastCompatibility() );
		$markdown  = $converter->convert(
			$html,
			array(
				'canonical_url' => 'https://example.com/about/',
			)
		);

		$this->assertStringStartsWith( '# About Us', $markdown );
		$this->assertStringContainsString( 'Source: https://example.com/about/', $markdown );
		$this->assertStringContainsString( 'A short excerpt.', $markdown );
	}

	public function test_strips_empty_headings(): void
	{
		$html      = "<h2></h2><p>Valid paragraph content that should survive post-processing cleanup.</p>";
		$converter = new MarkdownConverter( SettingsPage::default_settings(), new YoastCompatibility() );
		$settings  = SettingsPage::default_settings();
		$settings['include_h1'] = false;
		$settings['include_canonical'] = false;
		$settings['include_excerpt'] = false;
		$converter = new MarkdownConverter( $settings, new YoastCompatibility() );
		$markdown  = $converter->convert( $html );

		$this->assertStringNotContainsString( '##', $markdown );
		$this->assertStringContainsString( 'Valid paragraph content', $markdown );
	}

	public function test_decodes_entities_and_normalizes_links(): void
	{
		$settings = SettingsPage::default_settings();
		$settings['include_h1']       = false;
		$settings['include_canonical'] = false;
		$settings['include_excerpt']   = false;

		$html      = '<p>Tom &amp; Jerry</p><p><a href="https://example.com/work/"> Our work </a></p>';
		$converter = new MarkdownConverter( $settings, new YoastCompatibility() );
		$markdown  = $converter->convert( $html );

		$this->assertStringContainsString( 'Tom & Jerry', $markdown );
		$this->assertStringContainsString( '[Our work](https://example.com/work/)', $markdown );
		$this->assertStringNotContainsString( '[ Our work ]', $markdown );
	}

	public function test_demotes_body_h1_when_front_matter_title_enabled(): void
	{
		$html = '<h1>Hero heading</h1><p>Supporting copy for the hero section on this page.</p>';
		$converter = new MarkdownConverter( SettingsPage::default_settings(), new YoastCompatibility() );
		$markdown  = $converter->convert(
			$html,
			array(
				'canonical_url' => 'https://example.com/',
			)
		);

		$this->assertStringStartsWith( '# About Us', $markdown );
		$this->assertStringContainsString( '## Hero heading', $markdown );
		$this->assertDoesNotMatchRegularExpression( '/^# Hero heading/m', $markdown );
	}

	public function test_removes_blank_lines_and_duplicate_lines(): void
	{
		$settings = SettingsPage::default_settings();
		$settings['include_h1']        = false;
		$settings['include_canonical'] = false;
		$settings['include_excerpt']   = false;

		$html      = "<p>Featured</p>\n\n<p>Featured</p>\n\n<p>Case study summary with enough words to remain.</p>";
		$converter = new MarkdownConverter( $settings, new YoastCompatibility() );
		$markdown  = $converter->convert( $html );

		$this->assertSame( 1, substr_count( $markdown, 'Featured' ) );
		$this->assertStringNotContainsString( "\n\n\n", $markdown );
	}
}
