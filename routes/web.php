<?php

use App\Http\Middleware\RequirePkceForPublicClients;
use App\Presentation\Http\Controllers\Admin\AuditLogController;
use App\Presentation\Http\Controllers\Admin\DashboardController;
use App\Presentation\Http\Controllers\Admin\DepartmentController;
use App\Presentation\Http\Controllers\Admin\UserManagementController;
use App\Presentation\Http\Controllers\Auth\AuthController;
use App\Presentation\Http\Controllers\Auth\MfaChallengeController;
use App\Presentation\Http\Controllers\Auth\SocialAuthController;
use App\Presentation\Http\Controllers\Developer\ClientController;
use App\Presentation\Http\Controllers\OAuth\IntrospectionController;
use App\Presentation\Http\Controllers\OAuth\JwksController;
use App\Presentation\Http\Controllers\OAuth\OidcDiscoveryController;
use App\Presentation\Http\Controllers\OAuth\OidcLogoutController;
use App\Presentation\Http\Controllers\OAuth\RevocationController;
use App\Presentation\Http\Controllers\OAuth\UserInfoController;
use App\Presentation\Http\Controllers\Profile\AuthorizedAppsController;
use App\Presentation\Http\Controllers\Profile\MfaController;
use App\Presentation\Http\Controllers\Profile\ProfileController;
use App\Presentation\Http\Controllers\Profile\SessionManagementController;
use App\Presentation\Http\Controllers\Profile\SocialConnectionController;
use Illuminate\Support\Facades\Route;

// -------------------------------------------------------------------------
// Root redirect
// -------------------------------------------------------------------------
Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

// -------------------------------------------------------------------------
// OIDC Discovery, JWKS, Revocation, Introspection & UserInfo
// Handled without web session middleware + with Dynamic OAuth CORS
// -------------------------------------------------------------------------
Route::middleware(['oauth.cors'])->withoutMiddleware(['web'])->group(function () {
    // OpenID Connect Discovery Document
    // RFC 8414 — https://datatracker.ietf.org/doc/html/rfc8414
    Route::get('/.well-known/openid-configuration', OidcDiscoveryController::class)
        ->name('oidc.discovery');

    // JSON Web Key Set — public key for JWT signature verification
    Route::get('/oauth/jwks', JwksController::class)
        ->name('oauth.jwks');

    // RFC 7009 Token Revocation
    Route::post('/oauth/revoke', [RevocationController::class, 'revoke'])
        ->middleware('throttle:oauth.token')
        ->name('oauth.revoke');

    // RFC 7662 Token Introspection
    Route::post('/oauth/introspect', [IntrospectionController::class, 'introspect'])
        ->middleware('throttle:oauth.introspect')
        ->name('oauth.introspect');

    // OpenID Connect UserInfo Endpoint
    // RFC 6749 / OIDC Core 1.0 Section 5.3
    Route::match(['get', 'post'], '/oauth/userinfo', UserInfoController::class)
        ->middleware('throttle:oauth.userinfo')
        ->name('oauth.userinfo');

    // OPTIONS preflight handler for any /oauth/* route
    Route::options('/oauth/{any}', fn () => response('', 204))
        ->where('any', '.*');
});

// -------------------------------------------------------------------------
// OIDC RP-Initiated Logout (RP Single Sign-Out)
// -------------------------------------------------------------------------
Route::middleware(['oauth.cors'])->group(function () {
    Route::match(['get', 'post'], '/oauth/logout', OidcLogoutController::class)
        ->name('oauth.logout');
});

// -------------------------------------------------------------------------
// Guest Authentication Routes
// -------------------------------------------------------------------------
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    // MFA TOTP challenge (accessible while session has mfa_pending_user_id)
    Route::get('/mfa/challenge', [MfaChallengeController::class, 'create'])->name('mfa.challenge');
    Route::post('/mfa/challenge', [MfaChallengeController::class, 'store'])
        ->middleware('throttle:mfa.challenge')
        ->name('mfa.challenge.store');

    // Social Login — Google & GitHub
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('social.redirect');
    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('social.callback');
});

// -------------------------------------------------------------------------
// OAuth2 Authorization with PKCE enforcement
// -------------------------------------------------------------------------
Route::middleware([RequirePkceForPublicClients::class])->group(function () {
    // Passport registers its own route for /oauth/authorize
    // This middleware wraps it to add PKCE enforcement before Passport handles it
});

// -------------------------------------------------------------------------
// Authenticated Routes (User Portal, Developer Portal, Admin Panel)
// -------------------------------------------------------------------------
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');

    // User Portal: Profile & Security
    Route::get('/profile', [ProfileController::class, 'index'])->name('profile.index');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // User Portal: Social Provider Connections (link / unlink when already authenticated)
    Route::get('/profile/social-connections/{provider}/connect', [SocialConnectionController::class, 'connect'])
        ->name('profile.social.connect');
    Route::get('/profile/social-connections/{provider}/callback', [SocialConnectionController::class, 'callback'])
        ->name('profile.social.callback');
    Route::delete('/profile/social-connections/{provider}', [SocialConnectionController::class, 'destroy'])
        ->name('profile.social.destroy');

    // User Portal: Two-Factor Authentication (MFA)
    Route::post('/profile/mfa/setup', [MfaController::class, 'setup'])->name('profile.mfa.setup');
    Route::post('/profile/mfa/confirm', [MfaController::class, 'confirm'])->name('profile.mfa.confirm');
    Route::delete('/profile/mfa', [MfaController::class, 'destroy'])->name('profile.mfa.destroy');

    // User Portal: Active Sessions
    Route::get('/profile/sessions', [SessionManagementController::class, 'index'])->name('profile.sessions');
    Route::delete('/profile/sessions/{id}', [SessionManagementController::class, 'destroy'])->name('profile.sessions.destroy');
    Route::post('/profile/sessions/revoke-others', [SessionManagementController::class, 'revokeOthers'])->name('profile.sessions.revoke-others');

    // User Portal: Authorized Apps
    Route::get('/profile/authorized-apps', [AuthorizedAppsController::class, 'index'])->name('profile.authorized-apps');
    Route::delete('/profile/authorized-apps/{clientId}', [AuthorizedAppsController::class, 'destroy'])->name('profile.authorized-apps.destroy');

    // Developer Portal: OAuth Clients Management
    Route::get('/developer/clients', [ClientController::class, 'index'])->name('developer.clients.index');
    Route::post('/developer/clients', [ClientController::class, 'store'])->name('developer.clients.store');
    Route::put('/developer/clients/{id}', [ClientController::class, 'update'])->name('developer.clients.update');
    Route::post('/developer/clients/{id}/secret', [ClientController::class, 'regenerateSecret'])->name('developer.clients.secret');
    Route::delete('/developer/clients/{id}', [ClientController::class, 'destroy'])->name('developer.clients.destroy');

    // Admin Panel: Users & Force Logout
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::patch('/admin/users/{id}/status', [DashboardController::class, 'updateUserStatus'])->name('admin.users.update-status');
    Route::post('/admin/users/{id}/force-logout', [DashboardController::class, 'forceLogoutUser'])->name('admin.users.force-logout');

    // Admin Panel: Audit Logs
    Route::get('/admin/audit-logs', [AuditLogController::class, 'index'])->name('admin.audit-logs.index');

    // Admin Panel: HRM & Departments
    Route::get('/admin/departments', [DepartmentController::class, 'index'])->name('admin.departments.index');
    Route::post('/admin/departments', [DepartmentController::class, 'store'])->name('admin.departments.store');
    Route::put('/admin/departments/{department}', [DepartmentController::class, 'update'])->name('admin.departments.update');
    Route::delete('/admin/departments/{department}', [DepartmentController::class, 'destroy'])->name('admin.departments.destroy');

    // Admin Panel: User HRM Profile & Positions
    Route::get('/admin/users/{id}', [UserManagementController::class, 'show'])->name('admin.users.show');
    Route::put('/admin/users/{id}', [UserManagementController::class, 'update'])->name('admin.users.update');
    Route::delete('/admin/users/{id}', [UserManagementController::class, 'destroy'])->name('admin.users.destroy');
    Route::post('/admin/users/{id}/restore', [UserManagementController::class, 'restore'])->name('admin.users.restore');
    Route::delete('/admin/users/{id}/force', [UserManagementController::class, 'forceDelete'])->name('admin.users.force-delete');
    Route::post('/admin/users/{id}/positions', [UserManagementController::class, 'assignPosition'])->name('admin.users.positions.assign');
    Route::delete('/admin/users/{id}/positions/{positionId}', [UserManagementController::class, 'terminatePosition'])->name('admin.users.positions.terminate');
    Route::post('/admin/users/{id}/reset-password', [UserManagementController::class, 'resetPassword'])->name('admin.users.reset-password');
});
