<?php

namespace HitCounters;

use DatabaseUpdater;

/* hack to get at protected member */
class HCUpdater extends DatabaseUpdater {
	public static function getDBUpdates( DatabaseUpdater $updater ) {
		/* This is an ugly abuse to rename a table. */
		$updater->modifyExtensionField( 'hitcounter',
			'hc_id',
			__DIR__ . '/../sql/rename_table.sql' );
		$updater->addExtensionTable( 'hit_counter_extension',
			__DIR__ . '/../sql/hit_counter_extension.sql', true );
		$updater->addExtensionTable( 'hit_counter',
			__DIR__ . '/../sql/page_counter.sql', true );
		$updater->dropExtensionField( 'page', 'page_counter',
			__DIR__ . '/../sql/drop_field.sql' );
	}

	public function clearExtensionUpdates() {
		$this->extensionUpdates = [];
	}

	public function getCoreUpdateList() {
		$updater = DatabaseUpdater::newForDb( $this->db, $this->shared, $this->maintenance );
		return $updater->getCoreUpdateList();
	}
}
