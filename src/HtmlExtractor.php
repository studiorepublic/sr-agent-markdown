<?php

declare(strict_types=1);

namespace SRAgentMarkdown;

final class HtmlExtractor
{
	private const MIN_TEXT_LENGTH = 50;

	/**
	 * @var array<string, mixed>
	 */
	private array $settings;

	/**
	 * @param array<string, mixed> $settings
	 */
	public function __construct( array $settings )
	{
		$this->settings = $settings;
	}

	public function extract( string $html ): string
	{
		$html = $this->normalize_html( $html );

		$document = $this->load_dom( $html );

		if ( null === $document ) {
			return '';
		}

		$xpath = new \DOMXPath( $document );
		$node  = $this->find_content_node( $xpath );

		if ( ! $node instanceof \DOMNode ) {
			return '';
		}

		$this->remove_excluded_elements( $xpath, $node );
		$this->sanitize_node( $node );
		$this->remove_empty_elements( $xpath, $node );

		return $this->get_inner_html( $node );
	}

	private function normalize_html( string $html ): string
	{
		if ( ! str_contains( strtolower( $html ), '<html' ) ) {
			$html = '<!DOCTYPE html><html><body>' . $html . '</body></html>';
		}

		return $html;
	}

	private function load_dom( string $html ): ?\DOMDocument
	{
		$document = new \DOMDocument();

		$previous = libxml_use_internal_errors( true );
		$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, LIBXML_NOWARNING | LIBXML_NOERROR );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		return $loaded ? $document : null;
	}

	private function find_content_node( \DOMXPath $xpath ): ?\DOMNode
	{
		foreach ( $this->get_content_selectors() as $selector ) {
			$query = $this->css_to_xpath( trim( $selector ) );

			if ( '' === $query ) {
				continue;
			}

			$nodes = $xpath->query( $query );

			if ( false === $nodes ) {
				continue;
			}

			foreach ( $nodes as $node ) {
				if ( $this->has_meaningful_text( $node ) ) {
					return $node;
				}
			}
		}

		$body = $xpath->query( '//body' );

		if ( false !== $body && $body->length > 0 ) {
			$body_node = $body->item( 0 );

			if ( $body_node instanceof \DOMNode && $this->has_meaningful_text( $body_node ) ) {
				return $body_node;
			}
		}

		return null;
	}

	/**
	 * @return list<string>
	 */
	private function get_content_selectors(): array
	{
		$configured = $this->settings['content_selectors'] ?? 'main, article, .entry-content, .site-main, #content, body';

		if ( ! is_string( $configured ) || '' === trim( $configured ) ) {
			$configured = 'main, article, .entry-content, .site-main, #content, body';
		}

		$selectors = array_map( 'trim', explode( ',', $configured ) );

		return array_values( array_filter( $selectors ) );
	}

	/**
	 * @return list<string>
	 */
	private function get_excluded_selectors(): array
	{
		$defaults = array(
			'nav',
			'header',
			'footer',
			'aside',
			'.site-header',
			'.site-footer',
			'.cookie',
			'.cookies',
			'.sr-only',
			'[aria-hidden="true"]',
			'script',
			'style',
			'noscript',
			'iframe',
		);

		$configured = $this->settings['excluded_selectors'] ?? '';

		if ( is_string( $configured ) && '' !== trim( $configured ) ) {
			$custom = array_map( 'trim', explode( ',', $configured ) );
			$defaults = array_merge( $defaults, $custom );
		}

		return array_values( array_unique( array_filter( $defaults ) ) );
	}

	private function remove_excluded_elements( \DOMXPath $xpath, \DOMNode $root ): void
	{
		foreach ( $this->get_excluded_selectors() as $selector ) {
			$query = $this->css_to_xpath( trim( $selector ) );

			if ( '' === $query ) {
				continue;
			}

			$scoped = $this->scope_xpath_to_node( $query, $root );
			$nodes  = $xpath->query( $scoped, $root );

			if ( false === $nodes ) {
				continue;
			}

			$to_remove = array();

			foreach ( $nodes as $node ) {
				$to_remove[] = $node;
			}

			foreach ( $to_remove as $node ) {
				if ( $node->parentNode instanceof \DOMNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}
	}

	private function remove_empty_elements( \DOMXPath $xpath, \DOMNode $root ): void
	{
		$changed = true;

		while ( $changed ) {
			$changed   = false;
			$to_remove = array();

			foreach ( $xpath->query( './/*', $root ) ?: array() as $node ) {
				if ( ! $node instanceof \DOMElement ) {
					continue;
				}

				$tag = strtolower( $node->tagName );

				if ( ! in_array( $tag, array( 'p', 'div', 'span', 'a', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
					continue;
				}

				$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ?? '' ) ?? '' );

				if ( '' !== $text ) {
					continue;
				}

				if ( 'a' === $tag && $node->hasAttribute( 'href' ) && '' !== trim( $node->getAttribute( 'href' ) ) ) {
					continue;
				}

				$to_remove[] = $node;
			}

			foreach ( $to_remove as $node ) {
				if ( $node->parentNode instanceof \DOMNode ) {
					$node->parentNode->removeChild( $node );
					$changed = true;
				}
			}
		}

		foreach ( $xpath->query( './/img', $root ) ?: array() as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}

			$src = trim( $node->getAttribute( 'src' ) );

			if ( '' !== $src ) {
				continue;
			}

			if ( $node->parentNode instanceof \DOMNode ) {
				$node->parentNode->removeChild( $node );
			}
		}
	}

	private function sanitize_node( \DOMNode $node ): void
	{
		if ( ! $node instanceof \DOMElement ) {
			return;
		}

		if ( $node->hasAttribute( 'style' ) ) {
			$style = strtolower( $node->getAttribute( 'style' ) );

			if ( str_contains( $style, 'display:none' ) || str_contains( $style, 'display: none' ) ) {
				if ( $node->parentNode instanceof \DOMNode ) {
					$node->parentNode->removeChild( $node );
				}

				return;
			}
		}

		if ( $node->hasAttribute( 'href' ) ) {
			$href = trim( $node->getAttribute( 'href' ) );

			if ( str_starts_with( strtolower( $href ), 'javascript:' ) ) {
				$node->removeAttribute( 'href' );
			}
		}

		$remove_attrs = array();

		foreach ( iterator_to_array( $node->attributes ?? array() ) as $attribute ) {
			$name = $attribute->nodeName;

			if ( 'onclick' === strtolower( $name ) || 'style' === strtolower( $name ) ) {
				$remove_attrs[] = $name;
				continue;
			}

			if ( str_starts_with( strtolower( $name ), 'data-' ) ) {
				$remove_attrs[] = $name;
			}
		}

		foreach ( $remove_attrs as $name ) {
			$node->removeAttribute( $name );
		}

		$children = array();

		foreach ( $node->childNodes as $child ) {
			$children[] = $child;
		}

		foreach ( $children as $child ) {
			if ( $child instanceof \DOMElement ) {
				$this->sanitize_node( $child );
			}
		}
	}

	private function has_meaningful_text( \DOMNode $node ): bool
	{
		$text = trim( preg_replace( '/\s+/u', ' ', $node->textContent ?? '' ) ?? '' );

		return strlen( $text ) >= self::MIN_TEXT_LENGTH;
	}

	private function get_inner_html( \DOMNode $node ): string
	{
		$html = '';

		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument?->saveHTML( $child ) ?? '';
		}

		return trim( $html );
	}

	private function css_to_xpath( string $selector ): string
	{
		$selector = trim( $selector );

		if ( '' === $selector ) {
			return '';
		}

		if ( str_starts_with( $selector, '#' ) ) {
			return '//*[@id="' . $this->escape_xpath( substr( $selector, 1 ) ) . '"]';
		}

		if ( str_starts_with( $selector, '.' ) ) {
			return '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $this->escape_xpath( substr( $selector, 1 ) ) . ' ")]';
		}

		if ( preg_match( '/^\[(.+)\]$/', $selector, $matches ) ) {
			if ( preg_match( '/^([^\=]+)\=\"([^\"]+)\"$/', $matches[1], $attr ) ) {
				return '//*[@' . $this->escape_xpath( $attr[1] ) . '="' . $this->escape_xpath( $attr[2] ) . '"]';
			}
		}

		if ( preg_match( '/^[a-z][a-z0-9]*$/i', $selector ) ) {
			return '//' . $selector;
		}

		return '';
	}

	private function scope_xpath_to_node( string $query, \DOMNode $root ): string
	{
		if ( str_starts_with( $query, '//' ) ) {
			return '.' . $query;
		}

		return $query;
	}

	private function escape_xpath( string $value ): string
	{
		if ( str_contains( $value, '"' ) ) {
			return str_replace( "'", "''", $value );
		}

		return $value;
	}
}
