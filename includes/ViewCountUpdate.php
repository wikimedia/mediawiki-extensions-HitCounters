<?php
/**
 * Update for the 'page_counter' field
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * Update for the 'page_counter' field, when $wgDisableCounters is false.
 *
 * Depending on $wgHitcounterUpdateFreq, this will directly increment the
 * 'page_counter' field or use the 'hitcounter' table and then collect the data
 * from that table to update the 'page_counter' field in a batch operation.
 */
class ViewCountUpdate implements DeferrableUpdate {
	/** @var int Page ID to increment the view count */
	protected $pageId;

	/**
	 * Constructor
	 *
	 * @param int $pageId Page ID to increment the view count
	 */
	public function __construct( $pageId ) {
		$this->pageId = intval( $pageId );
	}

	/**
	 * Run the update
	 */
	public function doUpdate() {
		global $wgHitcounterUpdateFreq;
		$dbw = wfGetDB( DB_MASTER );

		wfDebugLog( "HitCounter", "update freq set to: $wgHitcounterUpdateFreq;" );
		if ( $wgHitcounterUpdateFreq <= 1 || $dbw->getType() == 'sqlite' ) {
			$pageId = $this->pageId;
			$method = __METHOD__;
			$dbw->onTransactionIdle( function () use ( $dbw, $pageId, $method ) {
				try {
					wfDebugLog( "HitCounter", "About to update $pageId" );
					$dbw->upsert( 'hit_counter',
						// Perform this INSERT if page_id not found
						[ 'page_id' => $pageId, 'page_counter' => 1 ],
						[ 'page_id' ],
						// Perform this SET if page_id found
						[ 'page_counter = page_counter + 1' ],
						$method
					);
				} catch ( DBError $e ) {
					wfDebugLog( "HitCounter", "Got an exception: " . $e->getMessage() );
					MWExceptionHandler::logException( $e );
				}
			} );
			return;
		}

		# Not important enough to warrant an error page in case of failure
		try {
			// Since `hit_counter_extension` is non-transactional, the
			// contention is minimal
			$dbw->insert( 'hit_counter_extension', [ 'hc_id' => $this->pageId ],
				__METHOD__ );
			$checkfreq = intval( $wgHitcounterUpdateFreq / 25 + 1 );
			if ( rand() % $checkfreq == 0 && $dbw->lastErrno() == 0 ) {
				$this->collect();
			}
		} catch ( DBError $e ) {
			error_log( "exception during insert update: " . $e->getMessage() );
			MWExceptionHandler::logException( $e );
		}
	}

	protected function collect() {
		global $wgHitcounterUpdateFreq;

		$dbw = wfGetDB( DB_MASTER );

		$dbType = $dbw->getType();
		$tabletype = $dbType == 'mysql' ? "ENGINE=HEAP " : '';
		$hitcounterTable = $dbw->tableName( 'hit_counter_extension' );
		$acchitsTable = $dbw->tableName( 'acchits' );
		$pageTable = $dbw->tableName( 'hit_counter' );
		$rown = $dbw->selectField( $hitcounterTable, 'COUNT(*)', [], __METHOD__ );
		if ( $rown < $wgHitcounterUpdateFreq ) {
			return;
		}

		$oldUserAbort = ignore_user_abort( true );

		$dbw->lockTables( [], [ $hitcounterTable ], __METHOD__, false );
		$dbw->query( "CREATE TEMPORARY TABLE $acchitsTable $tabletype AS " .
			"SELECT hc_id,COUNT(*) AS hc_n FROM $hitcounterTable " .
			'GROUP BY hc_id', __METHOD__ );
		$dbw->delete( $hitcounterTable, '*', __METHOD__ );
		$dbw->unlockTables( __METHOD__ );

		if ( $dbType == 'mysql' ) {
			$dbw->query( "UPDATE $pageTable,$acchitsTable " .
				'SET page_counter=page_counter + hc_n ' .
				'WHERE page_id = hc_id', __METHOD__ );
		} else {
			$dbw->query( "UPDATE $pageTable SET page_counter=page_counter + hc_n " .
				"FROM $acchitsTable WHERE page_id = hc_id", __METHOD__ );
		}
		$dbw->query( "DROP TABLE $acchitsTable", __METHOD__ );

		ignore_user_abort( $oldUserAbort );
	}
}
