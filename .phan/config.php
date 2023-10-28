<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'includes',
		"../../extensions/AbuseFilter"
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'], [
		getenv( "MW_INSTALL_PATH" ) ?: "",
		"../../extensions/AbuseFilter"
	]
);

return $cfg;
