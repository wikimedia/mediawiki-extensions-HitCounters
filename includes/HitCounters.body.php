<?php

namespace HitCounters;

use MWNamespace;
use ObjectCache;
use Parser;
use PPFrame;
use Title;

class HitCounters {
	protected static $mViews;

	protected static function cacheStore( $cache, $key, $views ) {
		if ( $views < 100 ) {
			// Only cache for a minute
			$cache->set( $key, $views, 60 );
		} else {
			/* update only once a day */
			$cache->set( $key, $views, 24 * 3600 );
		}
	}

	/**
	 * @return int The view count for the page
	 */
	public static function getCount( Title $title ) {
		if ( $title->isSpecialPage() ) {
			return null;
		}

		/*
		 * Use the cache to avoid hitting the DB if available since
		 * page views are pretty common and this is a tiny bit of
		 * information.
		 */
		$cache = ObjectCache::getInstance();
		$key = $cache->makeKey( 'viewcount', $title->getPrefixedDBkey() );
		$views = $cache->get( $key );
		wfDebugLog( "HitCounters", "Got viewcount=" .
			var_export( $views, true ) . " from cache" );

		if ( !$views || $views == 1 ) {
			$dbr = wfGetDB( DB_REPLICA );
			$row = $dbr->select(
				[ 'hit_counter' ],
				[ 'hits' => 'page_counter' ],
				[ 'page_id' => $title->getArticleID() ],
				__METHOD__ );

			if ( $row !== false && $current = $row->current() ) {
				$views = $current->hits;
				wfDebugLog( "HitCounters", "Got result=" .
					var_export( $current, true ) .
					" from DB and setting cache." );
				self::cacheStore( $cache, $key, $views );
			}
		}

		return $views;
	}

	public static function views() {
		# Should check for MiserMode here
		$cache = ObjectCache::getInstance( CACHE_ANYTHING );
		$key = $cache->makeKey( 'sitestats', 'activeusers-updated' );
		// Re-calculate the count if the last tally is old...
		if ( !self::$mViews ) {
			self::$mViews = $cache->get( $key );
			wfDebugLog( "HitCounters", __METHOD__
				. ": got " . var_export( self::$mViews, true ) .
				" from cache." );
			if ( !self::$mViews || self::$mViews == 1 ) {
				$dbr = wfGetDB( DB_REPLICA );
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
	 */
	public static function numberOfPageViews(
		Parser $parser, PPFrame $frame, $args
	) {
		return self::getCount( $frame->title );
	}

	public static function getQueryInfo() {
		global $wgDBprefix;

		return [
			'tables' => [ 'page', 'hit_counter' ],
			'fields' => [
				'namespace' => 'page_namespace',
				'title' => 'page_title',
				'value' => 'page_counter' ],
			'conds' => [
				'page_is_redirect' => 0,
				'page_namespace' => MWNamespace::getContentNamespaces(),
			],
			'join_conds' => [
				'page' => [
					'INNER JOIN',
					$wgDBprefix . 'page.page_id = ' .
					$wgDBprefix . 'hit_counter.page_id' ]
			]
		];
	}
}
