<?php

// Router for PHP's built-in server during development. It runs Laravel's own
// router from `public/`, so the server can be started with custom ini
// settings (such as larger uploads), which `artisan serve` does not pass on.
chdir(__DIR__.'/../public');

return require __DIR__.'/../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
