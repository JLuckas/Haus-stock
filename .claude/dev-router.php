<?php

// Router for PHP's built-in server when it is started from the project root
// (Laravel's own server.php expects to run from inside public/).
$public = dirname(__DIR__).'/public';
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

if ($uri !== '/' && is_file($public.$uri)) {
    return false;
}

chdir($public);

require $public.'/index.php';
