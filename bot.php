<?php

declare(strict_types=1);

// Backward-compatible entry point. The actual worker lives in worker.php so
// there is only one Telegram implementation to maintain and test.
require __DIR__ . '/worker.php';
