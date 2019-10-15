<?php
/**
 * WP Minions Runner
 *
 * IMPORTANT: This file must be placed in (or symlinked to) the root of the WordPress install!
 *
 * If Composer is present it will be used, Else a custom autoloader will
 * be used in it's place.
 */

function wp_minions_runner() {
	$plugin = \WpMinions\Plugin::get_instance();
	$plugin->enable();

	return $plugin->run();
}

if ( ! defined( 'PHPUNIT_RUNNER' ) ) {
	ignore_user_abort( true );

	if ( ! empty( $_POST ) || defined( 'DOING_AJAX' ) || defined( 'DOING_ASYNC' ) ) {
		die();
	}

	define( 'DOING_ASYNC', true );

	if ( ! defined( 'ABSPATH' ) ) {

		if (file_exists(__DIR__ . '/../../../wordpress/wp-load.php')) {
			require_once(__DIR__ . '/../../../wordpress/wp-load.php');
		} elseif (file_exists(__DIR__ . '/../../../wp-load.php')) {
			require_once __DIR__ . '/../../../wp-load.php';
		} else {
			error_log(
				'WP Minions Fatal Error - Cannot find wp-load.php'
			);
			exit;
		}
	}

	wp_minions_runner();
}
