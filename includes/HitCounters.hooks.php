<?php
namespace HitCounters;

use DatabaseUpdater;
use RequestContext;
use Title;
use Parser;
use DeferredUpdates;
use CoreParserFunctions;
use ViewCountUpdate;
use SiteStatsUpdate;
use SiteStats;
use SkinTemplate;
use QuickTemplate;
use PPFrame;
use WikiPage;
use User;

/**
 * PHPMD will warn us about these things here but since they're hooks,
 * we really don't have much choice.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Hooks {
	public static function onLoadExtensionSchemaUpdates(
		DatabaseUpdater $updater
	) {
		HCUpdater::getDBUpdates( $updater );
	}

	public static function onSpecialStatsAddExtra(
		array &$extraStats, RequestContext $statsPage
	) {
		global $wgContLang;

		$totalViews = HitCounters::views();
		$extraStats['hitcounters-statistics-header-views']
			['hitcounters-statistics-views-total'] = $totalViews;
		$extraStats['hitcounters-statistics-header-views']
			['hitcounters-statistics-views-peredit'] =
			$wgContLang->formatNum( $totalViews
				? sprintf( '%.2f', $totalViews / SiteStats::edits() )
				: 0 );
		$extraStats['hitcounters-statistics-mostpopular'] =
			self::getMostViewedPages( $statsPage );
		return true;
	}

	protected static function getMostViewedPages( RequestContext $statsPage ) {
		$dbr = wfGetDB( DB_REPLICA );
		$param = HitCounters::getQueryInfo();
		$options['ORDER BY'] = [ 'page_counter DESC' ];
		$options['LIMIT'] = 10;
		$res = $dbr->select(
			$param['tables'], $param['fields'], [], __METHOD__,
			$options, $param['join_conds']
		);

		$ret = [];
		if ( $res->numRows() > 0 ) {
			foreach ( $res as $row ) {
				$title = Title::makeTitleSafe( $row->namespace, $row->title );

				if ( $title instanceof Title ) {
					$ret[ $title->getPrefixedText() ]['number'] = $row->value;
					$ret[ $title->getPrefixedText() ]['name'] =
						\Linker::link( $title );
				}
			}
			$res->free();
		}
		return $ret;
	}

	protected static function getMagicWords() {
		return [
			'numberofviews'     => [ 'HitCounters\HitCounters', 'numberOfViews' ],
			'numberofpageviews' => [ 'HitCounters\HitCounters', 'numberOfPageViews' ]
		];
	}

	public static function onMagicWordwgVariableIDs( array &$variableIDs ) {
		$variableIDs = array_merge( $variableIDs, array_keys( self::getMagicWords() ) );
	}

	public static function onParserFirstCallInit( Parser $parser ) {
		foreach ( self::getMagicWords() as $magicWord => $processingFunction ) {
			$parser->setFunctionHook( $magicWord, $processingFunction,
				Parser::SFH_OBJECT_ARGS );
		}
		return true;
	}

	public static function onParserGetVariableValueSwitch( Parser &$parser,
		array $cache, &$magicWordId, &$ret, PPFrame &$frame ) {
		global $wgDisableCounters;

		foreach ( self::getMagicWords() as $magicWord => $processingFunction ) {
			if ( $magicWord === $magicWordId ) {
				if ( !$wgDisableCounters ) {
					$ret = CoreParserFunctions::formatRaw(
						call_user_func( $processingFunction, $parser, $frame, null ),
						null,
						$parser->getFunctionLang()
					);
					return true;
				} else {
					wfDebugLog( 'HitCounters', 'Counters are disabled!' );
				}
			}
		}
		return true;
	}

	public static function onPageViewUpdates( WikiPage $wikipage, User $user ) {
		global $wgDisableCounters;

		// Don't update page view counters on views from bot users (bug 14044)
		if (
			!$wgDisableCounters &&
			!$user->isAllowed( 'bot' ) &&
			$wikipage->exists()
		) {
			DeferredUpdates::addUpdate(
				new ViewCountUpdate( $wikipage->getId() )
			);
			DeferredUpdates::addUpdate( new SiteStatsUpdate( 1, 0, 0 ) );
		}
	}

	public static function onSkinTemplateOutputPageBeforeExec(
		SkinTemplate &$skin,
		QuickTemplate &$tpl
	) {
		global $wgDisableCounters;

		/* Without this check two lines are added to the page. */
		static $called = false;
		if ( $called ) {
			return;
		}
		$called = true;

		if ( !$wgDisableCounters ) {
			$footer = $tpl->get( 'footerlinks' );
			if ( isset( $footer['info'] ) && is_array( $footer['info'] ) ) {
				// 'viewcount' goes after 'lastmod', we'll just assume
				// 'viewcount' is the 0th item
				array_splice( $footer['info'], 1, 0, 'viewcount' );
				$tpl->set( 'footerlinks', $footer );
			}

			$viewcount = HitCounters::getCount( $skin->getTitle() );
			if ( $viewcount ) {
				wfDebugLog(
					"HitCounters",
					"Got viewcount=$viewcount and putting in page"
				);
				$tpl->set( 'viewcount', $skin->msg( 'viewcount' )->
					numParams( $viewcount )->parse() );
			}
		}
	}
}
