<?php

namespace HitCounters;

use DatabaseUpdater;
use Installer;
use MWException;

/* hack to get at protected member */
class HCUpdater extends DatabaseUpdater {
	public static function getDBUpdates ( DatabaseUpdater $updater ) {
		/* This is an ugly abuse to rename a table. */
		$updater->modifyExtensionField( 'hitcounter',
			'hc_id',
			__DIR__ . '/rename_table.sql' );
		$updater->addExtensionTable( 'hit_counter_extension',
			__DIR__ . '/hit_counter_extension.sql', true );
		$updater->addExtensionTable( 'hit_counter',
			__DIR__ . '/page_counter.sql', true );
		$updater->dropExtensionField( 'page', 'page_counter',
			__DIR__ . '/drop_field.sql' );
	}

	public function clearExtensionUpdates() {
		$this->extensionUpdates = array();
	}

	public function getCoreUpdateList() {
		$updater = DatabaseUpdater::newForDb( $this->db, $this->shared, $this->maintenance );
		return $updater->getCoreUpdateList();
	}

	/**
	 * Maybe we could just not set $shared's default?
	 *
	 * @SuppressWarnings(BooleanArgumentFlag)
	 */
	public static function newForDB( &$dbconn, $shared = false, $maintenance = null ) {
		$type = $dbconn->getType();
		if ( in_array( $type, Installer::getDBTypes() ) ) {
			return new self( $dbconn, $shared, $maintenance );
		} else {
			throw new MWException( __METHOD__ . ' called for unsupported $wgDBtype' );
		}
	}

}
