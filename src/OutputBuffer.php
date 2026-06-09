<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class OutputBuffer
{
	/**
	 * @var array<string, mixed>
	 */
	private array $settings;

	private HtmlExtractor $extractor;

	private MarkdownConverter $converter;

	private Cache $cache;

	private Headers $headers;

	private int $start_level = 0;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct(
		array $settings,
		HtmlExtractor $extractor,
		MarkdownConverter $converter,
		Cache $cache,
		Headers $headers
	) {
		$this->settings   = $settings;
		$this->extractor  = $extractor;
		$this->converter  = $converter;
		$this->cache      = $cache;
		$this->headers    = $headers;
	}

	public function start(): bool
	{
		if ( $this->start_level > 0 ) {
			return false;
		}

		// Nested buffers are required when another plugin (e.g. Super Page Cache advanced-cache.php)
		// has already started output buffering.
		ob_start();
		$this->start_level = ob_get_level();

		return true;
	}

	/**
	 * @param array<string, mixed> $context
	 */
	public function finish_and_output( array $context, bool $explicit_route ): void
	{
		if ( $this->start_level <= 0 ) {
			return;
		}

		$html = $this->capture_buffered_html();
		$this->start_level = 0;

		if ( '' === $html ) {
			return;
		}

		try {
			$extracted = $this->extractor->extract( $html );

			if ( '' === trim( $extracted ) ) {
				throw new \RuntimeException( 'No meaningful content extracted from HTML.' );
			}

			$markdown = $this->converter->convert( $extracted, $context );

			if ( '' === trim( $markdown ) ) {
				throw new \RuntimeException( 'Markdown conversion produced empty output.' );
			}

			if ( ! empty( $this->settings['caching_enabled'] ) ) {
				$this->cache->set( $context, $markdown );
			}

			echo $markdown; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		} catch ( \Throwable $exception ) {
			$this->handle_failure( $html, $exception, $explicit_route );
		}
	}

	private function capture_buffered_html(): string
	{
		$html = '';

		while ( ob_get_level() >= $this->start_level ) {
			$chunk = ob_get_clean();

			if ( false === $chunk ) {
				break;
			}

			if ( '' !== trim( $chunk ) ) {
				$html = $chunk;
			}
		}

		while ( ob_get_level() > 0 ) {
			ob_end_clean();
		}

		return $html;
	}

	private function handle_failure( string $html, \Throwable $exception, bool $explicit_route ): void
	{
		if ( ! empty( $this->settings['debug_enabled'] ) ) {
			error_log( '[SR Agent Markdown] ' . $exception->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		if ( $explicit_route && ! empty( $this->settings['debug_enabled'] ) ) {
			status_header( 500 );
			header( 'Content-Type: text/plain; charset=utf-8' );
			echo 'Markdown conversion failed.'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			exit;
		}

		if ( ! headers_sent() ) {
			header( 'Content-Type: text/html; charset=utf-8' );
		}

		echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}
}
