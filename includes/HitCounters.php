<?php

namespace HitCounters;

use BagOStuff;
use MediaWiki\MediaWikiServices;
use ObjectCache;
use Parser;
use PPFrame;
use Title;

class HitCounters {
	protected static ?int $mViews;

	protected static function cacheStore( BagOStuff $cache, string $key, ?int $views ): void {
		if ( $views < 100 ) {
			// Only cache for a minute
			$cache->set( $key, $views, 60 );
		} else {
			/* update only once a day */
			$cache->set( $key, $views, 24 * 3600 );
		}
	}

	/**
	 * @param Title $title
	 * @return int|null The view count for the page
	 */
	public static function getCount( Title $title ): ?int {
		if ( $title->isSpecialPage() ) {
			return null;
		}

		/*
		 * Use the cache to avoid hitting the DB if available since
		 * page views are pretty common and this is a tiny bit of
		 * information.
		 */
		$cache = ObjectCache::getLocalClusterInstance();
		$key = $cache->makeKey( 'viewcount', $title->getPrefixedDBkey() );
		$views = $cache->get( $key );

		if ( $views === false || $views <= 1 ) {
			$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()
				 ->getMaintenanceConnectionRef( DB_REPLICA );
			$hits = $dbr->selectField(
				[ 'hit_counter' ],
				[ 'hits' => 'page_counter' ],
				[ 'page_id' => $title->getArticleID() ],
				__METHOD__
			);

			if ( $hits !== false ) {
				$views = (int)$hits;
				self::cacheStore( $cache, $key, $views );
			}
		}

		return (int)$views;
	}

	public static function views(): ?int {
		# Should check for MiserMode here
		$cache = ObjectCache::getInstance( CACHE_ANYTHING );
		$key = $cache->makeKey( 'sitestats', 'activeusers-updated' );
		// Re-calculate the count if the last tally is old...
		if ( !isset( self::$mViews ) ) {
			self::$mViews = $cache->get( $key );
			wfDebugLog( "HitCounters", __METHOD__
				. ": got " . var_export( self::$mViews, true ) .
				" from cache." );
			if ( !self::$mViews || self::$mViews == 1 ) {
				$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()
					 ->getMaintenanceConnectionRef( DB_REPLICA );
				self::$mViews = $dbr->selectField(
					'hit_counter', 'SUM(page_counter)', '', __METHOD__
				);
				wfDebugLog( "HitCounters", __METHOD__ . ": got " .
					var_export( self::$mViews, true ) .
					" from select." );
				self::cacheStore( $cache, $key, self::$mViews );
			}
		}
		return self::$mViews;
	}

	/**
	 * {{NUMBEROFVIEWS}} - number of total views of the site
	 *
	 * We can't choose our parameters since this is a hook and we
	 * don't really need to use the $parser and $cache parameters.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return int|null
	 */
	public static function numberOfViews(
		Parser $parser, PPFrame $frame, $args
	) {
		return self::views();
	}

	/**
	 * {{NUMBEROFPAGEVIEWS}} - number of total views of the page
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @param array $args
	 * @return int|null
	 */
	public static function numberOfPageViews(
		Parser $parser, PPFrame $frame, $args
	) {
		return self::getCount( $frame->getTitle() );
	}

	/**
	 * @return array
	 */
	public static function getQueryInfo() {
		return [
			'tables' => [ 'p' => 'page', 'h' => 'hit_counter' ],
			'fields' => [
				'namespace' => 'p.page_namespace',
				'title'  => 'p.page_title',
				'value'  => 'h.page_counter',
				'length' => 'p.page_len'
			],
			'conds' => [
				'p.page_is_redirect' => 0,
				'p.page_namespace' => MediaWikiServices::getInstance()
					->getNamespaceInfo()
					->getContentNamespaces(),
			],
			'join_conds' => [
				'p' => [
					'INNER JOIN',
					'p.page_id = h.page_id'
				]
			]
		];
	}
}
