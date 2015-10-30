<?php

namespace HitCounters;

use Title;
use Parser;
use PPFrame;
use MWNamespace;
use MWException;

class HitCounters {
	protected static $mViews;

	/**
	 * @return int The view count for the page
	 */
	public static function getCount( Title $title ) {
		if ( $title === null ) {
			throw new MWException( "Asked for count without a title!" );
		}
		if ( $title->isSpecialPage() ) {
			return null;
		}

		/*
		 * Use the cache to avoid hitting the DB if available since
		 * page views are pretty common and this is a tiny bit of
		 * information.
		 */
		$cache = wfGetCache( CACHE_ANYTHING );
		$key = wfMemcKey( 'viewcount', $title->getDBkey() );
		$views = $cache->get( $key );
		wfDebugLog( "HitCounters", "Got viewcount=" . var_export( $views, true ) .
			" from cache" );

		if ( !$views ) {
			$dbr = wfGetDB( DB_SLAVE );
			$row = $dbr->select(
				array( 'hit_counter' ),
				array( 'hits' => 'page_counter' ),
				array( 'page_id' => $title->getArticleID() ),
				__METHOD__ );
			wfDebugLog( "HitCounters", "Got result=" . var_export( $row, true ) .
				" from DB and setting cache." );

			if ( $row !== false && $current = $row->current() ) {
				$views = $current->hits;
				wfDebugLog( "HitCounters", "Got result=" . var_export( $current, true ) .
					" from DB and setting cache." );
				if ( $views < 100 ) {
					// Only cache for a minute
					$cache->set( $key, $views, 60 );
				} else {
					/* update only once a day */
					$cache->set( $key, $views, 24 * 3600 );
				}
			}
		}

		return $views;
	}

	public static function views() {
		# Should check for MiserMode here
		$cache = wfGetCache( CACHE_ANYTHING );
		$key = wfMemcKey( 'sitestats', 'activeusers-updated' );
		// Re-calculate the count if the last tally is old...
		if ( !self::$mViews ) {
			self::$mViews = $cache->get( $key );
			wfDebugLog( "HitCounters", __METHOD__ . ": got " . var_export( self::$mViews, true ) .
				" from cache." );
			if ( !self::$mViews ) {
				$dbr = wfGetDB( DB_SLAVE );
				self::$mViews = $dbr->selectField( 'hit_counter', 'SUM(page_counter)', '',
					__METHOD__ );
				wfDebugLog( "HitCounters", __METHOD__ . ": got " . var_export( self::$mViews, true ) .
					" from select." );
				$cache->set( $key, self::$mViews, 24 * 3600 ); // don't update for 1 day
			}
		}
		return self::$mViews;
	}

	/**
	 * We can't choose our parameters since this is a hook and we
	 * don't really need to use the $parser and $cache parameters.
	 *
	 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
	 */
	public static function numberOfViews( Parser &$parser, PPFrame $frame, $args ) {
		return self::getCount( $frame->title );
	}

	public static function getQueryInfo() {
		global $wgDBprefix;

		return array(
			'tables' => array( 'page', 'hit_counter' ),
			'fields' => array(
				'namespace' => 'page_namespace',
				'title' => 'page_title',
				'value' => 'page_counter' ),
			'conds' => array(
				'page_is_redirect' => 0,
				'page_namespace' => MWNamespace::getContentNamespaces(),
			),
			'join_conds' => array(
				'page' => array( 'INNER JOIN', $wgDBprefix . 'page.page_id = ' . $wgDBprefix .
					'hit_counter.page_id' )
			)
		);
	}
}
