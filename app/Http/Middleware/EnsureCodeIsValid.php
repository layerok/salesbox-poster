<?php

namespace App\Http\Middleware;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class EnsureCodeIsValid
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        $code = $request->route()->parameter('code');
        $res = $this->authorize($code);
        if($res['error']) {
            return response(view('poster-app-error', $res));
        } else {
            return $next($request);
        }

    }

    public function authorize($code)
    {
        $auth = [
            'application_id' => config('poster.application_id'),
            'application_secret' => config('poster.application_secret'),
            'code' => $code,
        ];
        $auth['verify'] = md5(implode(':', $auth));

        $client = new Client([
            'http_errors' => false
        ]);

        $response = $client->post('https://joinposter.com/api/v2/auth/manage', [
            'form_params' => $auth
        ]);

        return json_decode($response->getBody(), true);
    }
}
