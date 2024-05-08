<?php
/**
 * Event Logic
 *
 * This file provides:
 * - Custom admin columns for the "events" post type
 * - Output of custom column values
 * - Sortable columns and custom ordering by meta value
 *
 * For more information, see: https://pluginrepublic.com/add-acf-fields-to-admin-columns/
 */

function add_custom_columns($columns)
{
  return array_merge(
    array_slice($columns, 0, 2),
    ["event_datum" => __("Event-Datum"), "hauptevent" => __("Hauptevent")],
    array_slice($columns, 2),
  );
}
add_filter("manage_events_posts_columns", "add_custom_columns");

function output_custom_column_values($column, $post_id)
{
  switch ($column) {
    case "event_datum":
      $date = get_post_meta($post_id, "event_datum", true);
      echo date("d.m.Y", strtotime($date));
      break;
    case "hauptevent":
      echo get_post_meta($post_id, "hauptevent", true) ? "Ja" : "Nein";
      break;
  }
}
add_action(
  "manage_events_posts_custom_column",
  "output_custom_column_values",
  10,
  2,
);

function make_custom_columns_sortable($columns)
{
  $columns["event_datum"] = "event_datum";
  return $columns;
}
add_filter(
  "manage_edit-events_sortable_columns",
  "make_custom_columns_sortable",
);

function sort_custom_column_entries_by_value($vars)
{
  if (isset($vars["orderby"]) && "event_datum" == $vars["orderby"]) {
    $vars = array_merge($vars, [
      "meta_key" => "event_datum",
      "orderby" => "meta_value",
    ]);
  }
  return $vars;
}
add_filter("request", "sort_custom_column_entries_by_value");
?>
