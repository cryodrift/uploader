<?php

//declare(strict_types=1);

/**
 * @env USER_STORAGEDIRS="G_ROOTDIR.cryodrift/users/"
 */

use cryodrift\fw\Core;

if (!isset($ctx)) {
    $ctx = Core::newContext(new \cryodrift\fw\Config());
}

$cfg = $ctx->config();

if (Core::env('USER_USEAUTH')) {
    \cryodrift\user\Auth::addConfigs($ctx, [
      'uploader',
    ]);
}

\cryodrift\fw\Router::addConfigs($ctx, [
  'uploader' => \cryodrift\uploader\Api::class,
], \cryodrift\fw\Router::TYP_WEB);

$cfg[\cryodrift\uploader\Api::class] = [
  'storagedir' => Core::env('USER_STORAGEDIRS'),
  'getvar_defaults' => [
    'override' => false,
  ]
];

\cryodrift\fw\FileHandler::addConfigs($ctx, [
  'uploader.js' => 'uploader/uploader.js',
]);
