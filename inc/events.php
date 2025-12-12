<?php
/**
 * Event Logic
 *
 * This file provides:
 * - REST API endpoint for fetching events within a date range
 * - Inclusion of ACF fields in the REST API response for "events" post type
 * - Custom admin columns for the "events" post type
 * - Output of custom column values
 * - Sortable columns and custom ordering by meta value
 *
 * For more information, see: https://pluginrepublic.com/add-acf-fields-to-admin-columns/
 */

class Custom_REST_Events_Controller extends WP_REST_Posts_Controller
{
  public function __construct()
  {
    parent::__construct("events");
  }

  public function register_routes()
  {
    register_rest_route("custom/v1", "/events", [
      "methods" => "GET",
      "callback" => [$this, "get_items"],
      "permission_callback" => "__return_true",
    ]);
  }

  public function get_items($request)
  {
    add_filter("rest_events_query", [$this, "modify_query"], 10, 2);
    $response = parent::get_items($request);
    remove_filter("rest_events_query", [$this, "modify_query"]);
    return $response;
  }

  public function modify_query($args, $request)
  {
    $args["orderby"] = [
      "event_datum" => "ASC",
      "zeit_von" => "ASC",
    ];

    $from = str_replace("-", "", $request->get_param("from"));
    $to = str_replace("-", "", $request->get_param("to"));
    $meta = $args["meta_query"] ?? [];

    if ($from) {
      $meta[] = [
        "key" => "event_datum",
        "value" => $from,
        "compare" => ">=",
        "type" => "NUMERIC",
      ];
    }
    if ($to) {
      $meta[] = [
        "key" => "event_datum",
        "value" => $to,
        "compare" => "<=",
        "type" => "NUMERIC",
      ];
    }

    if ($from || $to) {
      $meta["relation"] = "AND";
      $args["meta_query"] = $meta;
    }

    return $args;
  }
}
add_action("rest_api_init", function () {
  $controller = new Custom_REST_Events_Controller();
  $controller->register_routes();
});

function add_acf_fields_to_events_rest_response($response, $post, $request)
{
  if (function_exists("get_fields")) {
    $acf = get_fields($post->ID);
    if ($acf) {
      $response->data["acf"] = $acf;
    }
  }
  return $response;
}
add_filter(
  "rest_prepare_events",
  "add_acf_fields_to_events_rest_response",
  10,
  3,
);

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
