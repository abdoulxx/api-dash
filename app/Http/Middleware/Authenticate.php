<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * For API requests, return null so Sanctum responds with JSON 401 instead
     * of redirecting to a non-existent login route.
     */
    protected function redirectTo($request): ?string
    {
        return null;
    }
}


