<?php

$IP = getenv( "MW_INSTALL_PATH" );
if ( $IP === false || !is_file( "$IP/includes/AutoLoader.php" ) ) {
	echo "Set the environment variable MW_INSTALL_PATH!\n";
	exit;
}
$GLOBALS['IP'] = $IP;
$GLOBALS['wgScopeTest'] = 'MediaWiki Setup.php scope test';

if ( !function_exists( "wfDefineOnce" ) ) {
	/**
	 * Somehow, psalm/psalter runs this twice.
	 *
	 * @param string $const to set
	 * @param mixed $val to use
	 * @return void
	 */
	function wfDefineOnce( string $const, $val ): void {
		if ( !defined( $const ) ) {
			define( $const, $val );
		}
	}
}
if ( !function_exists( "wfRequireOnceInGlobalScope" ) ) {
	/**
	 * PHPUnit includes the bootstrap file inside a method body, while most MediaWiki startup files
	 * assume to be included in the global scope.
	 * This utility provides a way to include these files: it makes all globals available in the
	 * inclusion scope before including the file, then exports all new or changed globals.
	 *
	 * @param string $fileName the file to include
	 * @return void
	 */
	function wfRequireOnceInGlobalScope( string $fileName ): void {
		// phpcs:disable MediaWiki.Usage.ForbiddenFunctions.extract
		extract( $GLOBALS, EXTR_REFS | EXTR_SKIP );
		// phpcs:enable

		require_once $fileName;

		foreach ( get_defined_vars() as $varName => $value ) {
			$GLOBALS[$varName] = $value;
		}
	}
}

wfDefineOnce( 'MW_ENTRY_POINT', 'cli' );
wfDefineOnce( "MEDIAWIKI", true );

// We don't use a settings file here but some code still assumes that one exists
wfDefineOnce( 'MW_CONFIG_FILE', "$IP/LocalSettings.php" );

$GLOBALS['wgCommandLineMode'] = true;
$GLOBALS['wgAutoloadClasses'] = [];

wfRequireOnceInGlobalScope( "$IP/includes/AutoLoader.php" );
wfRequireOnceInGlobalScope( "$IP/tests/common/TestsAutoLoader.php" );
wfRequireOnceInGlobalScope( "$IP/includes/Defines.php" );
wfRequireOnceInGlobalScope( "$IP/includes/DefaultSettings.php" );
wfRequireOnceInGlobalScope( "$IP/includes/GlobalFunctions.php" );
