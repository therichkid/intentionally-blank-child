<?php
/**
 * Plugin Logic
 *
 * This file provides:
 *   - Google Maps API key integration for ACF Google Map fields.
 *   - Disable plugin and theme auto-update email notifications.
 *   - Disable Contact Form 7 spam filter to allow POST requests.
 *   - Disable update notifications for Contact Form 7 plugin.
 */

function acf_add_google_map_api($api)
{
  $api["key"] = $_ENV["GOOGLE_MAPS_API_KEY"] ?? "";
  return $api;
}
add_filter("acf/fields/google_map/api", "acf_add_google_map_api");

add_filter("auto_plugin_update_send_email", "__return_false");
add_filter("auto_theme_update_send_email", "__return_false");
add_filter("wpcf7_spam", "__return_false");

function disable_cf7_updates($value)
{
  if (isset($value) && is_object($value)) {
    unset($value->response["contact-form-7/wp-contact-form-7.php"]);
  }
  return $value;
}
add_filter("site_transient_update_plugins", "disable_cf7_updates");
?>
