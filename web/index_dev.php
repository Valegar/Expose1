<?php

use Symfony\Component\Debug\Debug;

// This check prevents access to debug front controllers that are deployed by accident to production servers.
// Feel free to remove this, extend it, or make something more sophisticated.
if (isset($_SERVER['HTTP_CLIENT_IP'])
    || isset($_SERVER['HTTP_X_FORWARDED_FOR'])
    || (isset($_SERVER['REMOTE_ADDR']) && !in_array($_SERVER['REMOTE_ADDR'], array('127.0.0.1', 'fe80::1', '::1')))
) {
    header('HTTP/1.0 403 Forbidden');
    throw new \Exception('You are not allowed to access this file. Check '.basename(__FILE__).' for more information.');
}

require_once __DIR__.'/../vendor/autoload.php';

define('WEB_DIR', __DIR__);

Debug::enable();

$dbSettings = require __DIR__.'/../config/db.php';
$app = require __DIR__.'/../src/app.php';
require __DIR__.'/../config/dev.php';
require __DIR__.'/../src/data.php';
require __DIR__.'/../src/controllers.php';
$app->run();
