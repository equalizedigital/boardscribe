<?php
/**
 * Tests for Helpers::is_valid_external_url() (PRO-1414).
 *
 * @package EqualizeDigital\BoardScribe
 */

use EqualizeDigital\BoardScribe\Helpers\Helpers;
use Yoast\WPTestUtils\WPIntegration\TestCase;

/**
 * The PHP counterpart of the editor's isValidExternalUrl(): esc_url_raw()
 * alone percent-encodes "http://not a valid url" into a dead link instead of
 * rejecting it, so importers need a real validator.
 */
class HelpersExternalUrlTest extends TestCase {

	/**
	 * @dataProvider valid_urls
	 *
	 * @param string $url A URL that must be accepted.
	 */
	public function test_accepts_valid_urls( string $url ): void {
		$this->assertTrue( Helpers::is_valid_external_url( $url ), $url );
	}

	/**
	 * @dataProvider invalid_urls
	 *
	 * @param string $url A value that must be rejected.
	 */
	public function test_rejects_invalid_urls( string $url ): void {
		$this->assertFalse( Helpers::is_valid_external_url( $url ), $url );
	}

	/**
	 * Values that are complete, usable http(s) URLs.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function valid_urls(): array {
		return [
			'https'          => [ 'https://example.com/agenda.pdf' ],
			'http'           => [ 'http://example.com/agenda.pdf' ],
			'port and query' => [ 'https://example.com:8080/a?b=1#c' ],
			'subdomain'      => [ 'https://sub-domain.example.com/x' ],
			'localhost'      => [ 'http://localhost/agenda' ],
			'ipv4'           => [ 'http://192.168.1.10/agenda.pdf' ],
			'uppercase'      => [ 'HTTPS://EXAMPLE.COM/Agenda.pdf' ],
		];
	}

	/**
	 * Values that must not be saved as a link.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function invalid_urls(): array {
		return [
			'empty'              => [ '' ],
			'plain text'         => [ 'not a url' ],
			'space in host'      => [ 'http://not a valid url' ],
			'space in host, tls' => [ 'https://exa mple.com/agenda.pdf' ],
			'encoded space'      => [ 'https://not%20a%20valid%20url' ],
			'scheme only'        => [ 'https://' ],
			'no scheme'          => [ 'example.com/agenda.pdf' ],
			'javascript'         => [ 'javascript:alert(1)' ],
			'ftp'                => [ 'ftp://example.com/file.pdf' ],
		];
	}
}
