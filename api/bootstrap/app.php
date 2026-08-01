<?php

declare(strict_types=1);

use WhatstheUp\Support\App;
use WhatstheUp\Support\Env;

$root = dirname(__DIR__);
require_once $root . '/vendor/autoload.php';
Env::load($root . '/.env');

date_default_timezone_set('UTC');
return App::create($root);
