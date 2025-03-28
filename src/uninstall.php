<?php
/**
 * Uninstall WP Cookie Blocker
 *
 * Fired when the plugin is uninstalled.
 */

// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete options created by this plugin
delete_option('wp_cookie_blocker_settings');
