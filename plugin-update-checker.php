<?php
/*
Plugin Name: My Plugin
Plugin URI: https://example.com/my-plugin
Description: This is my awesome plugin!
Version: 1.0
Author: My Name
Author URI: https://example.com/
License: GPL2
*/

// Include the Plugin Update Checker library
require_once( 'plugin-update-checker/plugin-update-checker.php' );

// Set the update checker URL to your GitHub repository's releases page
$my_plugin_update_checker = Puc_v4_Factory::buildUpdateChecker(
	'https://github.com/yourusername/yourpluginname/releases',
	__FILE__,
	'my-plugin'
);

// Optional: Set the update checker parameters
$my_plugin_update_checker->setBranch('stable'); // Set the branch (stable, beta, dev)
$my_plugin_update_checker->setAuthentication('username', 'password'); // Set basic authentication credentials

// Optional: Enable debug mode to see update checker debug information
// $my_plugin_update_checker->debugMode(true);

// Schedule the update checker to run daily
add_action( 'wp_ajax_my_plugin_check_for_updates', array( $my_plugin_update_checker, 'checkForUpdates' ) );
add_action( 'wp_ajax_nopriv_my_plugin_check_for_updates', array( $my_plugin_update_checker, 'checkForUpdates' ) );
if ( ! wp_next_scheduled( 'my_plugin_check_for_updates' ) ) {
	wp_schedule_event( time(), 'daily', 'my_plugin_check_for_updates' );
}
?>
