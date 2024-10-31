<?php
//error_reporting(E_ERROR); 

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SEMA_Importer {

	/**
	 * Product Exporter Tool
	 */
	public static function load_wp_importer() {
		// Load Importer API
		require_once ABSPATH . 'wp-admin/includes/import.php';

		if ( ! class_exists( 'WP_Importer' ) ) {
			$class_wp_importer = ABSPATH . 'wp-admin/includes/class-wp-importer.php';
			if ( file_exists( $class_wp_importer ) ) {
				require $class_wp_importer;
			}
		}
	}

	/**
	 * Product Importer Tool
	 */
	public static function product_importer() {
		if ( ! defined( 'WP_LOAD_IMPORTERS' ) ) {
			return;
		}

		self::load_wp_importer();

		// includes
		require_once 'class-sema-product-import.php';
		//require_once 'class-wf-csv-parser.php';

		// Dispatch
		$GLOBALS['SEMA_Product_Import'] = new SEMA_Product_Import();
		$GLOBALS['SEMA_Product_Import'] ->dispatch();
	}
}