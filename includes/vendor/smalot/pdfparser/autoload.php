<?php

/**
 * Simple autoloader for Smalot PdfParser (vendored).
 */
spl_autoload_register( function ( $class ) {
	$prefix = 'Smalot\\PdfParser\\';
	$prefix_len = strlen( $prefix );
	if ( strncmp( $prefix, $class, $prefix_len ) !== 0 ) {
		return;
	}

	$relative = substr( $class, $prefix_len );
	$path = __DIR__ . '/src/' . str_replace( '\\', '/', $relative ) . '.php';
	if ( file_exists( $path ) ) {
		require_once $path;
	}
} );
