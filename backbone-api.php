<?php
/*
Plugin Name: Backbone API
Plugin URI: http://craigkuhns.com/
Description: A plugin for developers. It provides a utility class that can be extended and instantiated in your own plugins that provides a restful api and a custom backbone sync object to work with the api exposed. It supports simple relationships between tables.
Version: 0.1.0
Author: Craig Kuhns
Author URI: http://craigkuhns.com
*/

require_once 'WP-Router/wp-router.php';
require_once 'backbone_api_model.php';
require_once 'backbone_api_controller.php';
require_once 'backbone_api_restful_controller.php';