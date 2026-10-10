<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // 404 (bukan 403) agar keberadaan endpoint GM tidak terbongkar ke pemain biasa.
        if (! $user || ! $user->isOwner()) {
            abort(404);
        }

        return $next($request);
    }
}