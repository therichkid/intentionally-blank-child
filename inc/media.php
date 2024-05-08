<?php
/**
 * Media Logic
 *
 * This file provides:
 *   - Alter allowed and disallowed mime types for media uploads.
 */

function alter_mime_types($mimes)
{
  // Allow mime types
  $mimes["svg"] = "image/svg+xml";
  // Disallow mime types
  unset($mimes["bmp"]);
  return $mimes;
}
add_filter("upload_mimes", "alter_mime_types");
?>
