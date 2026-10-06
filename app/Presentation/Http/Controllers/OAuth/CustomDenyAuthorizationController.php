<?php

namespace App\Presentation\Http\Controllers\OAuth;

use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\DenyAuthorizationController as PassportDenyController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class CustomDenyAuthorizationController extends PassportDenyController
{
    /**
     * Deny the authorization request.
     * Supports JSON responses for seamless AJAX denial without triggering iframe sandbox restrictions.
     */
    public function deny(Request $request, ResponseInterface $psrResponse): Response
    {
        $response = parent::deny($request, $psrResponse);

        if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $redirectUrl = $response->headers->get('Location');

            return response()->json([
                'status' => 'denied',
                'redirect_uri' => $redirectUrl,
            ]);
        }

        return $response;
    }
}
