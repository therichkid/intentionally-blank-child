<?php
/**
 * Plugin Logic
 *
 * This file provides:
 *   - Google Maps API key integration for ACF Google Map fields.
 *   - Disable plugin and theme auto-update email notifications.
 *   - Disable Contact Form 7 spam filter to allow POST requests.
 *   - Disable update notifications for Contact Form 7 plugin.
 *   - Set default admin sort order for Contact Form 7 submissions to newest first.
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

function cf7_disable_updates($value)
{
  if (isset($value) && is_object($value)) {
    unset($value->response["contact-form-7/wp-contact-form-7.php"]);
  }
  return $value;
}
add_filter("site_transient_update_plugins", "cf7_disable_updates");

function cf7_set_default_admin_sort($query)
{
  if (!is_admin()) {
    return;
  }

  if ($query->get("post_type") === "wpcf7_contact_form") {
    $query->set("orderby", "date");
    $query->set("order", "DESC");
  }
}
add_action("pre_get_posts", "cf7_set_default_admin_sort");
?>
