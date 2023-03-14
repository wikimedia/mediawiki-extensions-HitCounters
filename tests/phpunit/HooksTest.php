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

		$footerItems = [];
		Hooks::onSkinAddFooterLinks( $skinMock, "", $footerItems );

		$this->assertSame( [], $footerItems, "footerItems is un-changed (empty array)" );
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

		$footerItems = [];
		Hooks::onSkinAddFooterLinks( $skinMock, "", $footerItems );

		$this->assertSame( [], $footerItems, "Do not count views for special page" );
	}
}
