<?php
namespace HitCounters;

use DatabaseUpdater;
use DeferredUpdates;
use IContextSource;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\MediaWikiServices;
use Parser;
use PPFrame;
use SiteStats;
use Skin;
use Title;
use User;
use WikiPage;

/**
 * PHPMD will warn us about these things here but since they're hooks,
 * we really don't have much choice.
 *
 * @SuppressWarnings(PHPMD.CamelCaseMethodName)
 * @SuppressWarnings(PHPMD.UnusedFormalParameter)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Hooks {
	/**
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates(
		DatabaseUpdater $updater
	) {
		HCUpdater::getDBUpdates( $updater );
	}

	/**
	 * @param array &$extraStats
	 * @param IContextSource $statsPage
	 * @return bool
	 */
	public static function onSpecialStatsAddExtra(
		array &$extraStats, IContextSource $statsPage
	) {
		$contLang = MediaWikiServices::getInstance()->getContentLanguage();

		$totalViews = HitCounters::views() ?? 0;
		$extraStats = [
			'hitcounters-statistics-header-views' => [
				'hitcounters-statistics-views-total' => $totalViews,
				'hitcounters-statistics-views-peredit' => $contLang->formatNum(
					$totalViews
					? sprintf( '%.2f', $totalViews / SiteStats::edits() )
					: 0
				) ],
			'hitcounters-statistics-mostpopular' => self::getMostViewedPages( $statsPage )
		];
		return true;
	}

	/**
	 * @param IContextSource $statsPage
	 * @return array
	 */
	protected static function getMostViewedPages( IContextSource $statsPage ) {
		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()
			 ->getMaintenanceConnectionRef( DB_REPLICA );
		$param = HitCounters::getQueryInfo();
		$options = [];
		$options['ORDER BY'] = [ 'page_counter DESC' ];
		$options['LIMIT'] = 10;
		$res = $dbr->select(
			$param['tables'], $param['fields'], [], __METHOD__,
			$options, $param['join_conds']
		);

		$ret = [];
		if ( $res->numRows() > 0 ) {
			$linkRenderer = MediaWikiServices::getInstance()->getLinkRenderer();

			foreach ( $res as $row ) {
				$title = Title::makeTitleSafe( $row->namespace, $row->title );

				if ( $title instanceof Title ) {
					$ret[ $title->getPrefixedText() ]['number'] = $row->value;
					$ret[ $title->getPrefixedText() ]['name'] =
						$linkRenderer->makeLink( $title );
				}
			}
			$res->free();
		}
		return $ret;
	}

	/**
	 * @return array[]
	 */
	protected static function getMagicWords() {
		return [
			'numberofviews'		=> [ 'HitCounters\HitCounters', 'numberOfViews' ],
			'numberofpageviews' => [ 'HitCounters\HitCounters', 'numberOfPageViews' ]
		];
	}

	/**
	 * @param array &$variableIDs
	 */
	public static function onMagicWordwgVariableIDs( array &$variableIDs ) {
		$variableIDs = array_merge( $variableIDs, array_keys( self::getMagicWords() ) );
	}

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		foreach ( self::getMagicWords() as $magicWord => $processingFunction ) {
			$parser->setFunctionHook( $magicWord, $processingFunction,
				Parser::SFH_OBJECT_ARGS );
		}
		return true;
	}

	/**
	 * @param Parser $parser
	 * @param array &$cache
	 * @param string $magicWordId
	 * @param string|null &$ret
	 * @param PPFrame $frame
	 * @return bool
	 */
	public static function onParserGetVariableValueSwitch(
		Parser $parser, array &$cache, $magicWordId, &$ret, PPFrame $frame
	): bool {
		$conf = MediaWikiServices::getInstance()->getMainConfig();

		foreach ( self::getMagicWords() as $magicWord => $processingFunction ) {
			if ( $magicWord === $magicWordId ) {
				if ( !$conf->get( "DisableCounters" ) ) {
					$ret = $cache[$magicWordId] = $parser->getTargetLanguage()->formatNum(
						call_user_func( $processingFunction, $parser, $frame, null )
					);
					return true;
				} else {
					wfDebugLog( 'HitCounters', 'Counters are disabled!' );
				}
			}
		}
		return true;
	}

	/**
	 * @param WikiPage $wikipage
	 * @param User $user
	 */
	public static function onPageViewUpdates( WikiPage $wikipage, User $user ) {
		$conf = MediaWikiServices::getInstance()->getMainConfig();

		// Don't update page view counters on views from bot users (bug 14044)
		if (
			!$conf->get( "DisableCounters" ) &&
			!$user->isAllowed( 'bot' ) &&
			!$user->isAllowed( 'hitcounter-exempt' ) &&
			$wikipage->exists()
		) {
			DeferredUpdates::addUpdate( new ViewCountUpdate( $wikipage->getId() ) );
		}
	}

	/**
	 * Hook: SkinAddFooterLinks
	 * @param Skin $skin
	 * @param string $key the current key for the current group (row) of footer links.
	 *	 e.g. `info` or `places`.
	 * @param array &$footerItems an empty array that can be populated with new links.
	 *	 keys should be strings and will be used for generating the ID of the footer item
	 *	 and value should be an HTML string.
	 */
	public static function onSkinAddFooterLinks(
		Skin $skin,
		string $key,
		array &$footerItems
	) {
		if ( $key === 'info' ) {
			$conf = MediaWikiServices::getInstance()->getMainConfig();

			if ( !$conf->get( "DisableCounters" ) ) {

				$viewcount = HitCounters::getCount( $skin->getTitle() );

				if ( $viewcount ) {
					wfDebugLog(
						"HitCounters",
						"Got viewcount=$viewcount and putting in page"
					);
					$viewcountMsg = $skin->msg( 'viewcount' )->
								  numParams( $viewcount )->parse();

					// Set up the footer
					$footerItems['viewcount'] = $viewcountMsg;
				}
			}
		}
	}

	/**
	 * Tells AbuseFilter about our variables
	 * @param array &$builderValues
	 * @return void
	 */
	public static function onAbuseFilterBuilder( array &$builderValues ) {
		$builderValues['vars']['page_views'] = 'page-views';
		$builderValues['vars']['moved_from_views'] = 'movedfrom-views';
		$builderValues['vars']['moved_to_views'] = 'movedto-views';
	}

	/**
	 * Old, deprecated syntax
	 * @param array &$deprecatedVars
	 * @return void
	 */
	public static function onAbuseFilterDeprecatedVariables( array &$deprecatedVars ) {
		$deprecatedVars['article_views'] = 'page_views';
	}

	/**
	 * Lazy-loads the article_views variable
	 * @param VariableHolder $vars
	 * @param Title $title
	 * @param string $prefix
	 * @return void
	 */
	public static function onAbuseFilterGenerateTitleVars(
		VariableHolder $vars,
		Title $title,
		$prefix
	) {
		$vars->setLazyLoadVar( $prefix . '_VIEWS', 'page-views', [ 'title' => $title ] );
	}

	/**
	 * Computes the article_views variables
	 * @param string $method
	 * @param VariableHolder $vars
	 * @param array $parameters
	 * @param null &$result
	 * @return bool
	 */
	public static function onAbuseFilterComputeVariable(
		string $method, VariableHolder $vars, array $parameters, &$result
	): bool {
		// Both methods are needed because they're saved in the DB and are necessary for old entries
		if ( $method === 'article-views' || $method === 'page-views' ) {
			$result = HitCounters::getCount( $parameters['title'] );
			return false;
		} else {
			return true;
		}
	}
}
