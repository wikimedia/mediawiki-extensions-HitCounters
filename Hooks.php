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
	public static function onSpecialPage_initList( array &$specialPages ) {
		$specialPages['PopularPages'] = 'HitCounters\SpecialPopularPages';
	}

	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		HCUpdater::getDBUpdates( $updater );
	}

	public static function onSpecialStatsAddExtra( array &$extraStats, RequestContext $statsPage ) {
		$totalViews = HitCounters::views();
		$extraStats['statistics-header-views']['statistics-views-total'] = $totalViews;
		$extraStats['statistics-header-views']['statistics-views-peredit'] =
			$totalViews / SiteStats::edits();
		$extraStats['statistics-mostpopular'] = self::getMostViewedPages( $statsPage );
		return true;
	}

	protected static function getMostViewedPages( RequestContext $statsPage ) {
		$dbr = wfGetDB( DB_SLAVE );
		$param = HitCounters::getQueryInfo();
		$options['ORDER BY'] = array( 'page_counter DESC' );
		$options['LIMIT'] = 10;
		$res = $dbr->select( $param['tables'], $param['fields'], array(), __METHOD__,
			$options, $param['join_conds'] );

		$ret = array();
		if ( $res->numRows() > 0 ) {
			foreach ( $res as $row ) {
				$title = Title::makeTitleSafe( $row->namespace, $row->title );

				if ( $title instanceof Title ) {
					$ret[ $title->getPrefixedText() ]['number'] = $row->value;
					$ret[ $title->getPrefixedText() ]['name'] =
						$statsPage->msg( 'hitcounter-page-label',
							$title->getText() )->title( $statsPage->getTitle() );
				}
			}
			$res->free();
		}
		return $ret;
	}

	public static function onMagicWordwgVariableIDs( array &$variableIDs ) {
		$variableIDs[] = 'numberofviews';
	}

	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'numberofviews', 'HitCounters:numberOfViews',
			Parser::SFH_OBJECT_ARGS );
		return true;
	}

	public static function onParserGetVariableValueSwitch( Parser &$parser,
		array $cache, &$magicWordId, &$ret, PPFrame &$frame ) {
		global $wgDisableCounters;

		if ( !$wgDisableCounters && $magicWordId === 'numberofviews' ) {
			$ret = CoreParserFunctions::formatRaw(
				HitCounters::numberOfViews( $parser, $frame, null ), null );
		} elseif ( $wgDisableCounters && $magicWordId === 'numberofviews' ) {
			wfDebugLog( 'HitCounters', 'Counters are disabled!' );
		}
		return true;
	}

	public static function onPageViewUpdates( WikiPage $wikipage, User $user ) {
		global $wgDisableCounters;

		// Don't update page view counters on views from bot users (bug 14044)
		if ( !$wgDisableCounters && !$user->isAllowed( 'bot' ) && $wikipage->exists() ) {
			DeferredUpdates::addUpdate( new ViewCountUpdate( $wikipage->getId() ) );
			DeferredUpdates::addUpdate( new SiteStatsUpdate( 1, 0, 0 ) );
		}
	}

	public static function onSkinTemplateOutputPageBeforeExec( SkinTemplate &$skin,
															   QuickTemplate &$tpl) {
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
				// 'viewcount' goes after 'lastmod', we'll just assume 'viewcount' is the 0th item
				array_splice( $footer['info'], 1, 0, 'viewcount' );
				$tpl->set( 'footerlinks', $footer );
			}

			$viewcount = HitCounters::getCount( $skin->getTitle());
			if ( $viewcount ) {
				$tpl->set( 'viewcount', $skin->msg( 'viewcount' )->numParams( $viewcount )->parse() );
			}
		}
	}
}
