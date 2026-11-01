<?php

declare(strict_types=1);

/**
 * Entry point for hosts whose document root is the project root.
 *
 * The application's real front controller is public/index.php. This file only
 * forwards to it so that "/" works even when URL rewriting is unavailable.
 * When the document root is public/, this file is outside the web root.
 */

require __DIR__ . '/public/index.php';
