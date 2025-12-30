<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        // For API requests, don't redirect - let it throw 401
        if ($request->is('api/*') || $request->expectsJson()) {
            return null;
        }
        
        // For web requests, we don't have a login route so return null
        return null;
    }
}
