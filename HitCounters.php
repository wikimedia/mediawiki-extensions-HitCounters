<?php
/**
 * An extension for providing basic hit counter information for
 * Mediawiki after version 1.24
 *
 * PHP Version 5
 *
 * @category  Extension
 * @package   HitCounters
 * @author    Mark A. Hershberger <mah@nichework.com>
 * @copyright 2015 Mark A. Hershberger
 * @license   GPL-3.0-or-later
 * @version   GIT: 0.3.1
 * @link      https://www.mediawiki.org/wiki/Extension:HitCounters
 */

/**
 * The main file of the HitCounters extension
 *
 * This file is part of the MediaWiki extension HitCounters.
 * The HitCounters extension is free software: you can redistribute it and/or
 * modify it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * The HitCounters extension is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @file
 */

call_user_func(
	function () {
		if ( function_exists( 'wfLoadExtension' ) ) {
			wfLoadExtension( 'HitCounters' );
			wfWarn(
			   'Deprecated PHP entry point used for HitCounters extension. ' .
			   'Please use wfLoadExtension instead, ' .
			   'see https://www.mediawiki.org/wiki/Extension_registration ' .
			   'for more details.'
			);
			return;
		} else {
			die( 'This extension requires MediaWiki 1.25+' );
		}
	}
);
