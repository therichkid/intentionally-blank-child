<?php
require_once __DIR__ . "/inc/env.php";
load_env(__DIR__ . "/.env");

require_once __DIR__ . "/inc/email-encryption.php";
require_once __DIR__ . "/inc/events.php";
require_once __DIR__ . "/inc/media.php";
require_once __DIR__ . "/inc/plugins.php";
require_once __DIR__ . "/inc/posts.php";

add_action("wp_enqueue_scripts", function () {
  wp_enqueue_style("parent-style", get_template_directory_uri() . "/style.css");
});
?>
