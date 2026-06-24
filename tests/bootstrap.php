<?php
// Clear opcode cache so config file changes take effect
if (function_exists('opcache_reset')) {
    opcache_reset();
}

require __DIR__ . '/../vendor/autoload.php';
