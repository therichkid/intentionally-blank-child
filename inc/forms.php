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
  private array $tag_map = [];

  function __construct(WPCF7_ContactForm $form)
  {
    $this->form = $form;
    $tags = $form->scan_form_tags();
    foreach ($tags as $tag) {
      $this->tag_map[$tag->name] = $tag;
    }
  }

  public function parse(): array
  {
    $markup = $this->form->prop("form");
    $raw_form_parts = $this->split_form_parts($markup);
    $parsed_elements = [];

    foreach ($raw_form_parts as $part) {
      $parsed_elements[] = $this->parse_form_part($part);
    }

    return $parsed_elements;
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

  private function parse_form_part(string $part): array
  {
    $element = [];
    $name = $this->get_name_from_form_part($part);

    if (!$name || !isset($this->tag_map[$name])) {
      if (preg_match("/\[submit[^\]]*\]/", $part)) {
        preg_match('/"([^"]*)"/', $part, $matches);
        $label = $matches[1] ?? "Senden";

        return [
          "type" => "submit",
          "label" => $label,
          "raw_content" => trim($part),
        ];
      }

      return [
        "type" => "text_block",
        "raw_content" => trim($part),
      ];
    }

    $tag = $this->tag_map[$name];

    $element = [
      "type" => $tag->basetype,
      "name" => $tag->name,
      "required" =>
        str_ends_with($tag->type, "*") ||
        ($tag->basetype === "acceptance" &&
          !in_array("optional", $tag->options)),
      "label" => $this->parse_label($part),
      "options" => $this->parse_options($tag, $part),
      "multiple" =>
        in_array("multiple", $tag->options) ||
        $tag->basetype === "quiz" ||
        ($tag->basetype === "checkbox" &&
          !in_array("exclusive", $tag->options)),
      "default_value" => $this->parse_default_value($tag, $part),
      "min" => $this->parse_min($tag),
      "max" => $this->parse_max($tag),
      "raw_content" => trim($part),
    ];

    return $element;
  }

  private function get_name_from_form_part(string $part): ?string
  {
    if (preg_match("/\[([^\]]+)\]/", $part, $matches)) {
      $parts = preg_split("/\s+/", trim($matches[1]));
      return $parts[1] ?? null;
    }
    return null;
  }

  private function parse_label(string $part): ?string
  {
    if (preg_match("/<label[^>]*>(.*?)<\/label>/is", $part, $matches)) {
      $label = preg_replace("/\[[^\]]*\]/", "", $matches[1]);
      return trim(strip_tags($label));
    }
    return null;
  }

  private function parse_options(WPCF7_FormTag $tag, string $part): array
  {
    if ($tag->basetype === "acceptance") {
      if (
        preg_match(
          '/\[([a-zA-Z0-9_]+)[^\]]*\](.*?)\[\/\1\]/is',
          $part,
          $matches,
        )
      ) {
        return [["label" => trim(strip_tags($matches[2])), "value" => "on"]];
      }
      return [];
    }

    $options = [];
    $raw_values = $tag->raw_values ?? $tag->values;

    foreach ($raw_values as $idx => $raw) {
      if (strpos($raw, "|") !== false) {
        [$label, $value] = explode("|", $raw, 2);
      } else {
        $label = $raw;
        $value = $raw;
      }

      if ($tag->basetype === "checkbox") {
        $value = "on";
      }

      $options[] = [
        "label" => $label,
        "value" => $value,
      ];
    }

    if (in_array("include_blank", $tag->options)) {
      array_unshift($options, [
        "label" => "Bitte wählen...",
        "value" => null,
      ]);
    }

    return $options;
  }

  private function parse_default_value(
    WPCF7_FormTag $tag,
    string $part,
  ): ?string {
    foreach ($tag->options as $option) {
      if (preg_match('/^default:(.*)$/', $option, $matches)) {
        if (in_array($tag->basetype, ["select", "checkbox", "radio"])) {
          $idx = (int) $matches[1] - 1;
          if (isset($tag->values[$idx])) {
            return $tag->values[$idx];
          }
        }
        return $matches[1];
      }
    }

    if (
      $tag->basetype !== "quiz" &&
      preg_match('/"([^"]*)"/', $part, $matches)
    ) {
      return $matches[1];
    }
    return null;
  }

  private function parse_min(WPCF7_FormTag $tag): ?int
  {
    foreach ($tag->options as $option) {
      if (preg_match('/^min:(\d+)$/', $option, $matches)) {
        return (int) $matches[1];
      }
      if (preg_match('/^minlength:(\d+)$/', $option, $matches)) {
        return (int) $matches[1];
      }
    }

    return null;
  }

  private function parse_max(WPCF7_FormTag $tag): ?int
  {
    foreach ($tag->options as $option) {
      if (preg_match('/^max:(\d+)$/', $option, $matches)) {
        return (int) $matches[1];
      }
      if (preg_match('/^maxlength:(\d+)$/', $option, $matches)) {
        return (int) $matches[1];
      }
    }

    return null;
  }
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

  $parser = new FormElementParser($form);
  $elements = $parser->parse();

  return new WP_REST_Response(
    [
      "id" => $form_id,
      "title" => $form->title(),
      "elements" => $elements,
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
