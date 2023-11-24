<?php
//phpcs:ignoreFile
// The throws here cause a problem for phpcs.

$mwp = getenv( "MW_INSTALL_PATH" ) ?: realpath( __DIR__ . "/../../.." );
if ( $mwp === false ) {
	 throw new Exception( "No MediaWiki root found!" );
}
$ven = realpath( __DIR__ . "/../vendor" ) ?: realpath( "$mwp/vendor" );
if ( $ven === false ) {
	 throw new Exception( "No vendor path found!" );
}
$ext = getenv( "MW_EXTENSION_PATH" ) ?: "$mwp/extensions";
$cfg = require "$ven/mediawiki/mediawiki-phan-config/src/config.php";

$extensions = [ "AbuseFilter" ];
$dirList = array_filter(
	array_map( fn ( $dir ): string => "$ext/$dir", $extensions ),
	fn ( $dir ): bool => is_dir( $dir )
);

$removeDir = array_filter(
	array_map( fn ( $dir ): string => basename( $dir ), $dirList ),
	fn ( $dir ): bool => is_dir( __DIR__ . "/stubs/$dir" )
);

$cfg['directory_list'][] = "includes";
$cfg['directory_list'] = array_merge( $cfg['directory_list'], $dirList );

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'] ?? [], [ $mwp ], $dirList
);

return $cfg;
