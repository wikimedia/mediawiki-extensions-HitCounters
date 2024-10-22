<?php

namespace HitCounters;

use DatabaseUpdater;

/* hack to get at protected member */
class HCUpdater extends DatabaseUpdater {
	public static function getDBUpdates( DatabaseUpdater $updater ): void {
		// Use $sqlDirBase for DBMS-independent patches and $base for
		// DBMS-dependent patches
		$base = $sqlDirBase = __DIR__ . '/../sql/';
		switch ( $updater->getDB()->getType() ) {
			case 'postgres':
				$base = __DIR__ . '/../sql/postgres/';
				break;
		}

		/* This is an ugly abuse to rename a table. */
		$updater->modifyExtensionField(
			'hitcounter', 'hc_id', $base . 'rename_table.sql'
		);
		$updater->addExtensionTable(
			'hit_counter_extension', $base . 'hit_counter_extension.sql'
		);
		$updater->addExtensionTable(
			'hit_counter', $base . 'page_counter.sql'
		);
		$updater->dropExtensionField(
			'page', 'page_counter', $sqlDirBase . 'drop_field.sql'
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
