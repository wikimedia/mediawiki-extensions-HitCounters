<?php

namespace HitCounters;

use MediaWiki\Installer\DatabaseUpdater;

/* hack to get at protected member */
class HCUpdater extends DatabaseUpdater {
	public static function getDBUpdates( DatabaseUpdater $updater ): void {
		// Use $sqlDirBase for DBMS-independent patches and $base for
		// DBMS-dependent patches
		$sqlDirBase = dirname( __DIR__ ) . '/sql';
		$dbType = $updater->getDB()->getType();
		$base = "$sqlDirBase/$dbType";

		/* This is an ugly abuse to rename a table. */
		$updater->modifyExtensionField(
			'hitcounter', 'hc_id', "$base/rename_table.sql"
		);
		$updater->addExtensionTable(
			'hit_counter_extension', "$base/hit_counter_extension.sql"
		);
		$updater->addExtensionTable(
			'hit_counter', "$base/hit_counter.sql"
		);
		$updater->dropExtensionField(
			'page', 'page_counter', "$sqlDirBase/drop_field.sql"
		);
	}

	public function clearExtensionUpdates(): void {
		$this->extensionUpdates = [];
	}

	/**
	 * @return array[]
	 */
	public function getCoreUpdateList() {
		$updater = DatabaseUpdater::newForDb(
			$this->db, (bool)$this->shared, $this->maintenance
		);
		return $updater->getCoreUpdateList();
	}

	/**
	 * @inheritDoc
	 */
	protected function getInitialUpdateKeys() {
		return [];
	}
}
