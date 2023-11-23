<?php

/**
 * Plugin Name: WP-Stateless - Simple Local Avatars Addon
 * Plugin URI: https://stateless.udx.io/addons/simple-local-avatars/
 * Description: Provides compatibility between the Simple Local Avatars and the WP-Stateless plugins.
 * Author: UDX
 * Version: 0.0.1
 * Text Domain: wpssla
 * Author URI: https://udx.io
 * License: MIT
 * 
 * Copyright 2023 UDX (email: info@udx.io)
 */

namespace WPSL\SimpleLocalAvatars;

add_action('plugins_loaded', function () {
  if (class_exists('wpCloud\StatelessMedia\Compatibility')) {
    require_once 'vendor/autoload.php';
    // Load 
    return new SimpleLocalAvatars();
  }

  add_filter('plugin_row_meta', function ($plugin_meta, $plugin_file, $_, $__) {
    if ($plugin_file !== join(DIRECTORY_SEPARATOR, [basename(__DIR__), basename(__FILE__)])) return $plugin_meta;
    $plugin_meta[] = sprintf('<span style="color:red;">%s</span>', __('This plugin requires WP-Stateless plugin version 4.0.0 or greater to be installed and active.'));
    return $plugin_meta;
  }, 10, 4);
});
