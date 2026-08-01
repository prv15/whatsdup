<?php

declare(strict_types=1);

use WhatstheUp\Support\Request;

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->run(Request::capture());
