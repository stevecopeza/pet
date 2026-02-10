<?php
/**
 * Plugin Name: PET – Plan. Execute. Track
 * Description: A domain-driven business operations platform built as a WordPress plugin for consulting-led, project-driven organisations.
 * Version: 1.0.0
 * Text Domain: pet
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'PET_VERSION', '1.0.0' );
define( 'PET_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Autoloader will go here
