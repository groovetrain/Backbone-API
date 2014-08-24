<?php
/*
Plugin Name: Backbone API
Plugin URI: http://craigkuhns.com/
Description: A plugin for developers. It provides a utility class that can be extended and instantiated in your own plugins that provides a restful api and a custom backbone sync object to work with the api exposed. It supports simple relationships between tables.
Version: 0.1.0
Author: Craig Kuhns
Author URI: http://craigkuhns.com
*/

add_action("activated_plugin", "load_backbone_api_first");
function load_backbone_api_first() {
	// ensure path to this file is via main wp plugin path
	$wp_path_to_this_file = preg_replace('/(.*)plugins\/(.*)$/', WP_PLUGIN_DIR."/$2", __FILE__);
	$this_plugin = plugin_basename(trim($wp_path_to_this_file));
	$active_plugins = get_option('active_plugins');
	$this_plugin_key = array_search($this_plugin, $active_plugins);
	if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
		array_splice($active_plugins, $this_plugin_key, 1);
		array_unshift($active_plugins, $this_plugin);
		update_option('active_plugins', $active_plugins);
	}
}

require_once 'WP-Router/wp-router.php';
require_once 'backbone_api_model.php';
require_once 'backbone_api_controller.php';
require_once 'backbone_api_restful_controller.php';