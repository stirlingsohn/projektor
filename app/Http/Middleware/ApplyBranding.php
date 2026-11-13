<?php

namespace App\Http\Middleware;

use App\Support\Branding;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyBranding
{
    public function handle(Request $request, Closure $next): Response
    {
        Branding::apply();

        return $next($request);
    }
}
