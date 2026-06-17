<?php

declare(strict_types=1);

require_once __DIR__ . '/stubs/wordpress.php';

use PHPUnit\Framework\TestCase;
use SRAgentMarkdown\RewriteRules;
use SRAgentMarkdown\SettingsPage;

final class RewriteRulesTest extends TestCase
{
	protected function setUp(): void
	{
		sr_md_reset_test_globals();
	}

	public function test_registers_homepage_and_nested_md_rewrite_rules(): void
	{
		$rules = new RewriteRules( SettingsPage::default_settings() );
		$rules->add_rewrite_rules();

		$this->assertSame(
			array(
				array(
					'regex' => '^\.md/?$',
					'query' => 'index.php?sr_md_path=',
					'after' => 'top',
				),
				array(
					'regex' => '(.+?)\.md/?$',
					'query' => 'index.php?sr_md_path=$matches[1]',
					'after' => 'top',
				),
			),
			$GLOBALS['sr_md_test_rewrite_rules']
		);
	}

	public function test_normalizes_homepage_md_request_to_front_page(): void
	{
		$wp    = new WP();
		$wp->query_vars = array(
			RewriteRules::QUERY_VAR => '',
		);
		$rules = new RewriteRules( SettingsPage::default_settings() );

		$rules->normalize_md_request( $wp );

		$this->assertSame( 4186, $wp->query_vars['page_id'] );
		$this->assertArrayNotHasKey( RewriteRules::QUERY_VAR, $wp->query_vars );
	}

	public function test_normalizes_nested_md_request_to_page_path(): void
	{
		$page = new WP_Post();
		$GLOBALS['sr_md_test_page_by_path']['about'] = $page;

		$wp             = new WP();
		$wp->query_vars = array(
			RewriteRules::QUERY_VAR => 'about',
		);
		$rules          = new RewriteRules( SettingsPage::default_settings() );

		$rules->normalize_md_request( $wp );

		$this->assertSame( 'about', $wp->query_vars['pagename'] );
	}

	public function test_normalizes_custom_post_type_md_request(): void
	{
		$post = new WP_Post();
		$post->ID        = 242;
		$post->post_type = 'case_studies';
		$post->post_name = 'ellen-macarthur-cancer-trust';

		$GLOBALS['sr_md_test_url_to_postid']['https://example.com/work/ellen-macarthur-cancer-trust/'] = 242;
		$GLOBALS['sr_md_test_posts'][242]                                                            = $post;
		$GLOBALS['sr_md_test_post_type_objects']['case_studies']                                     = (object) array(
			'query_var' => 'case_studies',
		);

		$wp             = new WP();
		$wp->query_vars = array(
			RewriteRules::QUERY_VAR => 'work/ellen-macarthur-cancer-trust',
		);
		$rules          = new RewriteRules( SettingsPage::default_settings() );

		$rules->normalize_md_request( $wp );

		$this->assertSame( 'case_studies', $wp->query_vars['post_type'] );
		$this->assertSame( 'ellen-macarthur-cancer-trust', $wp->query_vars['name'] );
		$this->assertSame( 'ellen-macarthur-cancer-trust', $wp->query_vars['case_studies'] );
	}
}
