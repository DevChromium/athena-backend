<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CacheControl
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Response $response */
        $response = $next($request);
        if($response->headers->get('Cache-Control') === 'no-cache, private') {
            $response->setCache(['public' => true, 'max_age' => 60, 's_maxage' => 60]);
        }

        return $response;
    }
}
