<?php

$auth = require base_path('vendor/laravel/framework/src/Illuminate/Translation/lang/en/auth.php');

return array_replace($auth, [
    'invalid_credentials' => 'Invalid credentials',
    'login_successful' => 'Login successful',
    'logout_successful' => 'Logged out successfully',
    'module_working' => 'Auth module is working',
    'unauthenticated' => 'Unauthenticated.',
]);
