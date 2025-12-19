<?php

namespace SLCA\SimpleLocalAvatars;

use wpCloud\StatelessMedia\Compatibility;

class SimpleLocalAvatars extends Compatibility {
  const META_KEY = 'simple_local_avatar';

  protected $id = 'simple-local-avatars';
  protected $title = 'Simple Local Avatars';
  protected $constant = 'WP_STATELESS_COMPATIBILITY_SLA';
  protected $description = 'Ensures compatibility with Simple Local Avatars plugin.';
  protected $plugin_file = 'simple-local-avatars/simple-local-avatars.php';

  /**
   * Initialize compatibility module
   *
   * @param $sm
   */
  public function module_init($sm) {
    add_action( 'updated_user_meta', array($this, 'updated_user_meta'), 10, 4 );

    // Only makes sense in CDN or Ephemeral modes
    if ( ud_get_stateless_media()->is_mode(['cdn', 'ephemeral', 'stateless']) ) {
      add_filter('get_user_metadata', array($this, 'get_user_metadata'), 10, 4);
    }
  }

  /**
   * Filter the result of specific user meta to redirect avatar images to GCS if in CDN or Stateless
   *
   * @param $null
   * @param $object_id
   * @param $meta_key
   * @param $_
   * @return mixed
   */
  public function get_user_metadata($null, $object_id, $meta_key, $_) {
    // Get out if not the meta we are interested in
    if ($meta_key !== self::META_KEY) return $null;

    // Remove THIS filter to avoid the infinite recursion
    remove_filter('get_user_metadata', array($this, 'get_user_metadata'), 10);

    // Get the actual meta
    $user_meta = get_user_meta($object_id, $meta_key);

    // Add THIS filter back for future calls
    add_filter('get_user_metadata', array($this, 'get_user_metadata'), 10, 4);

    // Get GCS link and local upload url
    $image_host = ud_get_stateless_media()->get_gs_host();
    $bucketLink = apply_filters('wp_stateless_bucket_link', $image_host);
    $upload     = wp_get_upload_dir();

    // Replace local urls with corresponding GCS urls
    if (!empty($user_meta[0]) && is_array($user_meta[0])) {
      foreach ($user_meta[0] as $key => &$value) {
        if (is_numeric($key)) {
          $value = trailingslashit($bucketLink) . apply_filters('wp_stateless_file_name', str_replace($upload['baseurl'], '', $value), true);
        }
      }
    }

    // Return filtered data back
    return empty($user_meta) ? null : $user_meta;
  }

  /**
   * Sync avatar files to GCS after meta update to make sure they are available on GCS
   *
   * @param $user_id
   */
  public function updated_user_meta($meta_id, $object_id, $meta_key, $_meta_value) {
    if ( $meta_key !== self::META_KEY || !is_array($_meta_value) ) {
      return;
    }

    $upload_dir = wp_upload_dir();
    $gsc_host = ud_get_stateless_media()->get_gs_host();
    $gsc_path = ud_get_stateless_media()->get_gs_path();

    $baseurl = ud_get_stateless_media()->is_mode('stateless') ? $gsc_host : $upload_dir['baseurl'];

    foreach ( $_meta_value as $key => $value ) {
      // Skip non-image value and full size image, which is already synced
      if ( strpos($value, $baseurl) !== 0 || $key === 'full' ) {
        continue;
      }

      $name = str_replace($baseurl, '', $value);
      $name = ltrim($name, '/');
      $absolute_path = ud_get_stateless_media()->is_mode('stateless') 
        ? str_replace($gsc_host, $gsc_path, $value)
        : str_replace($upload_dir['baseurl'], $upload_dir['basedir'], $value);

      $name = apply_filters('wp_stateless_file_name', $name, true);

      do_action('sm:sync::syncFile', $name, $absolute_path, false, [
        'use_root' => true,
        'name_with_root' => true,
        'source' => 'Simple Local Avatars',
        'source_version' => defined('SLA_VERSION') ? SLA_VERSION : '',
      ]);
    }
  }
}
