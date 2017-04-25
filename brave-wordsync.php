<?php

/**
 * @link              http://bravedigital.com
 * @since             0.1.0
 * @package           BraveWpsync
 *
 * @wordpress-plugin
 * Plugin Name:       WordSync
 * Plugin URI:        http://bravedigital.com/wordsync
 * Description:       Migrate posts, pages, settings between Wordpress installs.
 * Version:           0.1.1
 * Author:            Brave Digital
 * Author URI:        http://bravedigital.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wordsync
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if (!is_admin()) //Only load the plugin when in the admin area.
{
	return;
}


/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-wordsync-activator.php
 */
function activate_wordsync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wordsync-activator.php';
	BraveWordsync_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-wordsync-deactivator.php
 */
function deactivate_wordsync() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-wordsync-deactivator.php';
	BraveWordsync_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wordsync' );
register_deactivation_hook( __FILE__, 'deactivate_wordsync' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-wordsync.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    0.1.0
 */
$plugin = new BraveWordsync();
$plugin->run();
