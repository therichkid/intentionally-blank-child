<?php
/**
 * Post Logic
 *
 * This file provides:
 *   - Support for post thumbnails (featured images).
 *   - A dropdown filter in the admin posts list to filter posts by author.
 *   - Send email notifications when a post is published.
 *
 */

add_theme_support("post-thumbnails");

function filter_posts_by_author($post_type)
{
  $selected = isset($_GET["user"]) && $_GET["user"] ? $_GET["user"] : "";
  wp_dropdown_users([
    "role__in" => ["administrator", "editor", "author", "contributor"],
    "name" => "author",
    "show_option_all" => "Alle Autoren",
    "selected" => $selected,
  ]);
}
add_action("restrict_manage_posts", "filter_posts_by_author");

function send_email_on_publish_post($post_id, $post, $old_status)
{
  if ($old_status === "publish") {
    return;
  }

  $author_id = $post->post_author;
  $author_name = get_the_author_meta("display_name", $author_id);
  $author_email = get_the_author_meta("user_email", $author_id);
  $post_title = $post->post_title;
  $post_content = $post->post_content;
  $post_slug = $post->post_name;

  $recipients = array_filter(
    array_map(
      "trim",
      explode(",", $_ENV["WP_EMAIL_NOTIFICATIONS_RECIPIENTS"] ?? ""),
    ),
  );
  $subject = sprintf("Neuer Betrag auf BayCIV veröffentlicht: %s", $post_title);
  $message = "Ein neuer Beitrag wurde von $author_name ($author_email) auf BayCIV veröffentlicht: $post_title

 $post_content

 Link zum Artikel: https://www.bayciv.de/news/$post_slug";

  wp_mail($recipients, $subject, $message);
}
add_action("publish_post", "send_email_on_publish_post", 10, 3);
?>
