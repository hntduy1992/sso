<?php

namespace App\Presentation\Http\Controllers\OAuth;

use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\ApproveAuthorizationController as PassportApproveController;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

class CustomApproveAuthorizationController extends PassportApproveController
{
    /**
     * Approve the authorization request.
     * Supports JSON responses for seamless AJAX approval without triggering iframe sandbox restrictions.
     */
    public function approve(Request $request, ResponseInterface $psrResponse): Response
    {
        $response = parent::approve($request, $psrResponse);

        if ($request->expectsJson() || $request->ajax() || $request->header('X-Requested-With') === 'XMLHttpRequest') {
            $redirectUrl = $response->headers->get('Location');

            return response()->json([
                'status' => 'approved',
                'redirect_uri' => $redirectUrl,
            ]);
        }

        return $response;
    }
}
