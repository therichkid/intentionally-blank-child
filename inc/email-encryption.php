<?php
/**
 * Email Encryption Logic and REST API Integration
 *
 * This file provides:
 *   - The Crypt class for simple email encryption and decryption.
 *   - Automatic encryption of the 'email' meta field in all public post types
 *     when data is served via the WordPress REST API.
 */

const SHIFT_CHARS_BY = 10;

class Crypt
{
  public static function encrypt($input)
  {
    $encrypted = base64_encode($input);
    $shifted = self::shift($encrypted);

    return $shifted;
  }

  public static function decrypt($input)
  {
    $unshifted = self::unshift($input);
    $decrypted = base64_decode($unshifted);

    return $decrypted;
  }

  private static function shift($input)
  {
    return self::rotate($input, SHIFT_CHARS_BY);
  }

  private static function unshift($input)
  {
    return self::rotate($input, -SHIFT_CHARS_BY);
  }

  private static function rotate($input, $shift)
  {
    $output = [];
    $length = strlen($input);
    for ($i = 0; $i < $length; $i++) {
      $code = ord($input[$i]);
      if ($code >= 65 && $code <= 90) {
        $code += $shift;
        if ($code < 65) {
          $code += 26;
        } elseif ($code > 90) {
          $code -= 26;
        }
      } elseif ($code >= 97 && $code <= 122) {
        $code += $shift;
        if ($code < 97) {
          $code += 26;
        } elseif ($code > 122) {
          $code -= 26;
        }
      }
      $output[] = chr($code);
    }
    return implode("", $output);
  }
}

function encrypt_email_in_rest_response($response, $post, $request)
{
  $email = get_post_meta($post->ID, "email", true);
  if (
    !empty($email) &&
    (str_contains($email, "@") ||
      str_contains($email, "(at)") ||
      str_contains($email, "[at]"))
  ) {
    $response->data["email"] = Crypt::encrypt($email);
  }
  return $response;
}
add_action("init", function () {
  $post_types = get_post_types(["public" => true], "names");
  foreach ($post_types as $type) {
    add_filter("rest_prepare_{$type}", "encrypt_email_in_rest_response", 10, 3);
  }
});
?>
