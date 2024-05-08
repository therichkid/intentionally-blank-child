<?php
/**
 * Environment Variables Logic
 *
 * This file provides:
 * - Loading environment variables from a .env file into the $_ENV superglobal and system environment.
 */

function load_env($path)
{
  if (!file_exists($path)) {
    return;
  }
  $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach ($lines as $line) {
    if (strpos(trim($line), "#") === 0) {
      continue;
    }
    [$name, $value] = array_map("trim", explode("=", $line, 2));
    if (!array_key_exists($name, $_ENV)) {
      $_ENV[$name] = $value;
      putenv("$name=$value");
    }
  }
}
?>
