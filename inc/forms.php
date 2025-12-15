<?php
/**
 * Form Parsing and REST API Integration
 *
 * This file provides:
 *   - Parsing of Contact Form 7 form elements into structured data
 *   - REST API endpoints for retrieving all forms and form details
 *   - Support for extracting labels, fields, and options from form markup
 *   - Handling of special field types and default values
 *   - Integration with the WordPress REST API for external access
 */

class FormElementParser
{
  private WPCF7_ContactForm $form;
  private array $tag_Map;

  function __construct(WPCF7_ContactForm $form)
  {
    $this->form = $form;
    $tags = $form->scan_form_tags();
    foreach ($tags as $tag) {
      $this->tagMap[$tag . name] = $tag;
    }
  }

  public function parse(): array
  {
    $markup = $this->form->prop("form");
    $raw_form_parts = $this->split_form_parts($markup);

    return $this->split_form_parts($markup);
  }

  private function split_form_parts(string $input): array
  {
    $input = preg_replace("/<!--.*?-->/s", "", $input);
    $input = str_replace(["\r", "\n"], "", $input);

    $pattern = '/
      (<label>.*?<\/label>)
      |(\[([a-zA-Z0-9_]+)[^\]]*\].*?\[\/\3\])
      |(\[[a-zA-Z0-9_]+[^\]]*\])
    /xis';

    $result = [];
    $offset = 0;

    preg_match_all(
      $pattern,
      $input,
      $matches,
      PREG_OFFSET_CAPTURE | PREG_SET_ORDER,
    );

    foreach ($matches as $match) {
      $start = $match[0][1];
      if ($start > $offset) {
        $result[] = substr($input, $offset, $start - $offset);
      }
      $result[] = $match[0][0];
      $offset = $start + strlen($match[0][0]);
    }
    if ($offset < strlen($input)) {
      $result[] = substr($input, $offset);
    }
    return array_values(array_filter($result, fn($v) => $v !== ""));
  }
}

function parse_form_elements($form_id)
{
  $contact_form = WPCF7_ContactForm::get_instance($form_id);
  if (!$contact_form) {
    return [];
  }

  $tags = $contact_form->scan_form_tags();
  $parts = preg_split(
    "/(\[[\w*]+[^\]]*\])/",
    $contact_form->prop("form"),
    -1,
    PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY,
  );

  $elements = [];
  $buffer = "";
  $tag_index = 0;

  foreach ($parts as $part) {
    $part = trim($part);

    if (str_starts_with($part, "[") && str_ends_with($part, "]")) {
      if ($buffer) {
        $clean_buf = preg_replace('/<\/(label|p|div)>\s*$/', "", $buffer);
        $label = null;

        if (preg_match('/<label[^>]*>(.*)$/is', $clean_buf, $matches)) {
          $pre_text = trim(str_replace($matches[0], "", $clean_buf));
          if ($pre_text) {
            $elements[] = ["type" => "text_block", "content" => $pre_text];
          }
          $label = trim(strip_tags($matches[1]));
        } elseif ($clean_buf) {
          $elements[] = ["type" => "text_block", "content" => $clean_buf];
        }
        $buffer = "";
      }

      if (isset($tags[$tag_index])) {
        $tag = $tags[$tag_index++];
        if (in_array($tag->type, ["hidden", "response", "submit"])) {
          continue;
        }

        $type = rtrim($tag->type, "*");
        $field = [
          "type" => "field",
          "tag_type" => $type,
          "name" => $tag->name,
          "label" => $label,
          "required" => $tag->is_required(),
          "markup_source" => $part,
        ];

        $field = array_merge($field, parse_tag_options($tag, $type, $part));
        $elements[] = $field;
      }
    } else {
      $buffer .= $part;
    }
  }

  if ($buffer) {
    $final_text = preg_replace('/<\/(label|p|div)>\s*$/', "", $buffer);
    if ($final_text) {
      $elements[] = ["type" => "text_block", "content" => $final_text];
    }
  }

  return $elements;
}

function parse_tag_options($tag, $type, $markup_source)
{
  $field = [];
  $options = $tag->options;

  if (
    in_array($type, [
      "text",
      "number",
      "textarea",
      "date",
      "email",
      "tel",
      "url",
      "file",
    ])
  ) {
    foreach ($options as $option) {
      if (strpos($option, "min:") === 0) {
        $field["min"] = substr($option, 4);
      }
      if (strpos($option, "max:") === 0) {
        $field["max"] = substr($option, 4);
      }
    }

    if ($markup_source && preg_match('/"([^"]*)"/', $markup_source, $m)) {
      $field["default_value"] = $m[1];
    }
  }

  if (in_array($type, ["select", "checkbox", "radio", "acceptance", "quiz"])) {
    $field["options"] = $tag->values;
    if ($type === "select" && in_array("include_blank", $options)) {
      array_unshift($field["options"], null);
    }

    foreach ($options as $option) {
      if (preg_match('/^default:(\d+)$/', $option, $m)) {
        $idx = (int) $m[1] - 1;
        if (isset($field["options"][$idx])) {
          $field["default_value"] = $field["options"][$idx];
        }
      }
    }

    if (in_array("multiple", $options)) {
      $field["multiple"] = true;
    }
  }

  return $field;
}

function get_all_forms()
{
  if (!class_exists("WPCF7_ContactForm")) {
    return new WP_Error(
      "cf7_not_installed",
      "Contact Form 7 ist nicht installiert.",
      ["status" => 500],
    );
  }

  $forms = WPCF7_ContactForm::find();

  $form_list = [];
  foreach ($forms as $form) {
    $form_list[] = [
      "id" => $form->id(),
      "title" => $form->title(),
    ];
  }

  return new WP_REST_Response($form_list, 200);
}

function get_form_details($request)
{
  $form_id = (int) $request["id"];

  if (!class_exists("WPCF7_ContactForm")) {
    return new WP_Error(
      "cf7_not_installed",
      "Contact Form 7 ist nicht installiert.",
      ["status" => 500],
    );
  }

  $form = WPCF7_ContactForm::get_instance($form_id);
  if (!$form) {
    return new WP_Error(
      "form_not_found",
      "Contact Form mit dieser ID nicht gefunden.",
      ["status" => 404],
    );
  }

  $form_elements = parse_form_elements($form_id);

  return new WP_REST_Response(
    [
      "id" => $form_id,
      "title" => $form->title(),
      "elements" => $form_elements,
    ],
    200,
  );
}

add_action("rest_api_init", function () {
  $namespace = "custom/v1";

  register_rest_route($namespace, "/forms", [
    "methods" => "GET",
    "callback" => "get_all_forms",
    "permission_callback" => "__return_true",
  ]);
  register_rest_route($namespace, "/forms/(?P<id>\d+)", [
    "methods" => "GET",
    "callback" => "get_form_details",
    "args" => [
      "id" => [
        "sanitize_callback" => "absint",
        "required" => true,
      ],
    ],
    "permission_callback" => "__return_true",
  ]);
});
?>
