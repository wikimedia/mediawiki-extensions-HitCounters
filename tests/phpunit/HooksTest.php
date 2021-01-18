<?php

namespace HitCounters;

use MediaWikiIntegrationTestCase;
use Skin;
use Title;

/**
 * @coversDefaultClass HitCounters\Hooks
 */
class HooksTest extends MediaWikiIntegrationTestCase {

	/**
	 * @covers ::onSkinAddFooterLinks
	 */
	public function testOnSkinAddFooterLinksDisabled() {
		global $wgDisableCounters;

		$wgDisableCounters = true;
		$skinMock = $this->getMockBuilder( Skin::class )
			->disableOriginalConstructor()
			->getMock();

		$footerLinks = null;
		Hooks::onSkinAddFooterLinks( $skinMock, "", $footerLinks );

		$this->assertNull( $footerLinks, "footerLinks is un-changed (null)" );

		$footerLinks = [];
		Hooks::onSkinAddFooterLinks( $skinMock, "", $footerLinks );

		$this->assertSame( $footerLinks, [], "footerLinks is un-changed (empty array)" );
	}

	/**
	 * @covers ::onSkinAddFooterLinks
	 */
	public function testOnSkinAddFooterLinksNotDisabledSpecialPage() {
		global $wgDisableCounters, $wgTitle;

		$wgTitle = Title::newFromText( "Special:Version" );

		$wgDisableCounters = false;
		$skinMock = $this->getMockBuilder( Skin::class )
			->disableOriginalConstructor()
			->getMock();

		$footerLinks = [];
		Hooks::onSkinAddFooterLinks( $skinMock, "", $footerLinks );

		$this->assertSame( $footerLinks, [], "Do not count views for special page" );
	}
}
