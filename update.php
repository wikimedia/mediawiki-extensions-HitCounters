#!/usr/bin/env php
<?php
/**
 * Run updates for just this extension.
 *
 * @file
 * @todo document
 * @ingroup Maintenance
 */

namespace HitCounters;

use Maintenance;

# Stolen from WebStart.php, assuming you're running this in root
$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = realpath( '.' ) ?: dirname( __DIR__ );
}
require_once "$IP/maintenance/Maintenance.php";

/**
 * Maintenance script to run database schema updates.
 *
 * @ingroup Maintenance
 */
class UpdateHitCounter extends Maintenance {

	public function __construct() {
		parent::__construct();

		$this->requireExtension( 'HitCounters' );
	}

	public function execute() {
		# Attempt to connect to the database as a privileged user
		# This will vomit up an error if there are permissions problems
		$dbconn = wfGetDB( DB_MASTER );

		$shared = $this->hasOption( 'doshared' );
		$updater = HCUpdater::newForDb( $dbconn, $shared, $this );
		$updater->clearExtensionUpdates();
		HCUpdater::getDBUpdates( $updater );
		$updater->doUpdates( [ 'extensions' ] );
	}
}

$maintClass = 'HitCounters\\UpdateHitCounter';
require_once RUN_MAINTENANCE_IF_MAIN;
