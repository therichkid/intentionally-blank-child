<?php
/**
 * Event Logic
 *
 * This file provides:
 *   - REST API endpoint for fetching events within a date range
 *   - Optional filtering by main-event status
 *   - Inclusion of ACF fields in REST API responses
 *   - Custom admin columns and ordering for the "events" post type
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
      "args" => [
        "is_main_event" => [
          "type" => "boolean",
          "required" => false,
          "description" => "Filter by main-event status.",
        ],
      ],
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
    $from = str_replace("-", "", (string) $request->get_param("date_from"));
    $to = str_replace("-", "", (string) $request->get_param("date_to"));
    $is_main_event = $request->get_param("is_main_event");

    $meta = ["relation" => "AND"];
    if (!empty($args["meta_query"])) {
      $meta[] = $args["meta_query"];
    }
    $meta[] = [
      "relation" => "OR",
      "event_sort_date" => [
        "key" => "date_from",
        "compare" => "EXISTS",
        "type" => "NUMERIC",
      ],
      [
        "key" => "date_from",
        "compare" => "NOT EXISTS",
      ],
    ];
    $meta[] = [
      "relation" => "OR",
      "event_sort_time" => [
        "key" => "time_from",
        "compare" => "EXISTS",
        "type" => "CHAR",
      ],
      [
        "key" => "time_from",
        "compare" => "NOT EXISTS",
      ],
    ];

    if ($from) {
      $meta[] = [
        "key" => "date_from",
        "value" => $from,
        "compare" => ">=",
        "type" => "NUMERIC",
      ];
    }
    if ($to) {
      $meta[] = [
        "key" => "date_from",
        "value" => $to,
        "compare" => "<=",
        "type" => "NUMERIC",
      ];
    }
    if ($is_main_event !== null) {
      $meta[] = [
        "key" => "is_main_event",
        "value" => $is_main_event ? "1" : "0",
        "compare" => "=",
      ];
    }

    $args["meta_query"] = $meta;
    $args["orderby"] = [
      "event_sort_date" => "ASC",
      "event_sort_time" => "ASC",
    ];

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
    ["date_from" => __("Datum (von)"), "is_main_event" => __("Hauptevent")],
    array_slice($columns, 2),
  );
}
add_filter("manage_events_posts_columns", "add_custom_columns");

function output_custom_column_values($column, $post_id)
{
  switch ($column) {
    case "date_from":
      $date = get_post_meta($post_id, "date_from", true);
      echo date("d.m.Y", strtotime($date));
      break;
    case "is_main_event":
      echo get_post_meta($post_id, "is_main_event", true) ? "Ja" : "Nein";
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
  $columns["date_from"] = "date_from";
  return $columns;
}
add_filter(
  "manage_edit-events_sortable_columns",
  "make_custom_columns_sortable",
);

function sort_custom_column_entries_by_value($vars)
{
  if (isset($vars["orderby"]) && "date_from" == $vars["orderby"]) {
    $vars = array_merge($vars, [
      "meta_key" => "date_from",
      "orderby" => "meta_value",
    ]);
  }
  return $vars;
}
add_filter("request", "sort_custom_column_entries_by_value");

function set_default_admin_sort()
{
  global $pagenow;
  if (
    is_admin() &&
    $pagenow === "edit.php" &&
    isset($_GET["post_type"]) &&
    $_GET["post_type"] === "events" &&
    !isset($_GET["orderby"]) &&
    !isset($_GET["order"])
  ) {
    $url = add_query_arg(
      [
        "post_type" => "events",
        "orderby" => "date_from",
        "order" => "desc",
      ],
      admin_url("edit.php"),
    );
    wp_redirect($url);
    exit();
  }
}
add_action("admin_init", "set_default_admin_sort");
?>
