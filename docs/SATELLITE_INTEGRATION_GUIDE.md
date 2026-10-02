# Hướng Dẫn Tích Hợp Hệ Thống Vệ Tinh (SSO Satellite Integration Guide)

Tài liệu này cung cấp hướng dẫn chi tiết, mã nguồn mẫu copy-pasteable và phương pháp kiến trúc tốt nhất để tích hợp 4 loại ứng dụng vệ tinh vào hệ thống SSO Identity Provider (Laravel 12 + Passport 12 + OIDC).

---

## Mục Lục
1. [Tổng Quan Kiến Trúc & OIDC Discovery](#1-tổng-quan-kiến-trúc--oidc-discovery)
2. [Client 1: SPA Vue 3 / Vite (Authorization Code + PKCE)](#2-client-1-spa-vue-3--vite-authorization-code--pkce)
3. [Client 2: Mobile App Flutter (PKCE + Secure Storage)](#3-client-2-mobile-app-flutter-pkce--secure-storage)
4. [Client 3: Laravel Web App Con (JWKS Local Verify + Backchannel Logout)](#4-client-3-laravel-web-app-con-jwks-local-verify--backchannel-logout)
5. [Client 4: Backend Service / Microservice (Client Credentials M2M)](#5-client-4-backend-service--microservice-client-credentials-m2m)
6. [Bảng Tra Cứu Scopes, Endpoints & Lỗi Thường Gặp](#6-bảng-tra-cứu-scopes-endpoints--lỗi-thường-gặp)

---

## 1. Tổng Quan Kiến Trúc & OIDC Discovery

IdP hỗ trợ chuẩn **OpenID Connect Discovery 1.0 (RFC 8414)**. Ứng dụng vệ tinh có thể tự động khám phá cấu hình IdP tại:

```http
GET https://sso.yourdomain.com/.well-known/openid-configuration
```

### Các Endpoint Cốt Lõi:
| Mục đích | Method | Endpoint | Mô tả |
| :--- | :--- | :--- | :--- |
| **Authorization** | `GET` | `/oauth/authorize` | Khởi tạo đăng nhập (PKCE cho SPA/Mobile, Auth Code cho Web) |
| **Token Exchange** | `POST` | `/oauth/token` | Đổi code lấy token, refresh token, hoặc M2M |
| **UserInfo** | `GET/POST`| `/oauth/userinfo` | Lấy claims người dùng (`sub`, `name`, `email`, `roles`) |
| **JWKS** | `GET` | `/oauth/jwks` | Lấy public key (RS256) xác minh chữ ký JWT cục bộ |
| **Introspection** | `POST` | `/oauth/introspect` | Kiểm tra trạng thái token (RFC 7662) |
| **Revocation** | `POST` | `/oauth/revoke` | Thu hồi access/refresh token (RFC 7009) |
| **RP Logout** | `GET/POST`| `/oauth/logout` | Đăng xuất tập trung (OIDC RP-Initiated Logout 1.0) |

---

## 2. Client 1: SPA Vue 3 / Vite (Authorization Code + PKCE)

### Đặc điểm kiến trúc:
- **Client Type:** Public (không lưu trữ client_secret trên trình duyệt).
- **Grant Type:** `authorization_code` với **PKCE S256** (bắt buộc).
- **Lưu trữ Token:** Lưu Access Token trong memory (Pinia/ref), Refresh Token trong Secure Storage hoặc HTTP-only Cookie / encrypted localStorage.
- **Refresh Token Rotation:** Tự động xoay vòng refresh token khi silent refresh.
- **Axios Interceptor:** Tự động gắn Bearer Token và xử lý mã lỗi `401 Unauthorized` bằng cách gửi refresh request.

### Mã nguồn tích hợp Composable (`useAuth.ts`)
Bạn có thể copy file `resources/js/composables/useAuth.ts` từ IdP hoặc triển khai như sau vào dự án Vue 3 của bạn:

```typescript
// src/composables/useAuth.ts
import { ref, computed } from 'vue';
import axios from 'axios';

const SSO_BASE_URL = 'https://sso.yourdomain.com';
const CLIENT_ID = '9d4c1b92-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
const REDIRECT_URI = window.location.origin + '/auth/callback';
const SCOPES = 'openid profile email roles offline_access';

const accessToken = ref<string | null>(null);
const refreshToken = ref<string | null>(localStorage.getItem('sso_refresh_token'));
const user = ref<Record<string, any> | null>(null);

function base64UrlEncode(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

async function generateCodeChallenge(verifier: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(verifier);
  const digest = await window.crypto.subtle.digest('SHA-256', data);
  return base64UrlEncode(digest);
}

function generateRandomString(length = 64): string {
  const array = new Uint8Array(length);
  window.crypto.getRandomValues(array);
  return base64UrlEncode(array.buffer).substring(0, length);
}

export function useAuth() {
  const isAuthenticated = computed(() => !!accessToken.value);

  // 1. Chuyển hướng người dùng sang IdP với PKCE
  const login = async () => {
    const codeVerifier = generateRandomString(64);
    const state = generateRandomString(32);
    sessionStorage.setItem('sso_code_verifier', codeVerifier);
    sessionStorage.setItem('sso_auth_state', state);

    const codeChallenge = await generateCodeChallenge(codeVerifier);

    const params = new URLSearchParams({
      client_id: CLIENT_ID,
      redirect_uri: REDIRECT_URI,
      response_type: 'code',
      scope: SCOPES,
      state,
      code_challenge: codeChallenge,
      code_challenge_method: 'S256',
    });

    window.location.href = `${SSO_BASE_URL}/oauth/authorize?${params.toString()}`;
  };

  // 2. Xử lý Callback tại trang /auth/callback
  const handleCallback = async (code: string, returnedState: string) => {
    const savedState = sessionStorage.getItem('sso_auth_state');
    const codeVerifier = sessionStorage.getItem('sso_code_verifier');

    if (!savedState || savedState !== returnedState) {
      throw new Error('CSRF State mismatch: Request không hợp lệ!');
    }
    if (!codeVerifier) {
      throw new Error('Code verifier bị thiếu!');
    }

    const response = await axios.post(`${SSO_BASE_URL}/oauth/token`, {
      grant_type: 'authorization_code',
      client_id: CLIENT_ID,
      redirect_uri: REDIRECT_URI,
      code_verifier: codeVerifier,
      code,
    });

    sessionStorage.removeItem('sso_code_verifier');
    sessionStorage.removeItem('sso_auth_state');

    setTokens(response.data.access_token, response.data.refresh_token);
    await fetchUserInfo();
  };

  // 3. Silent Refresh Token với cơ chế xoay vòng
  const refresh = async (): Promise<boolean> => {
    if (!refreshToken.value) return false;

    try {
      const response = await axios.post(`${SSO_BASE_URL}/oauth/token`, {
        grant_type: 'refresh_token',
        client_id: CLIENT_ID,
        refresh_token: refreshToken.value,
        scope: SCOPES,
      });

      setTokens(response.data.access_token, response.data.refresh_token);
      return true;
    } catch (err) {
      logoutLocal();
      return false;
    }
  };

  // 4. Lấy thông tin UserInfo
  const fetchUserInfo = async () => {
    if (!accessToken.value) return null;
    const response = await axios.get(`${SSO_BASE_URL}/oauth/userinfo`, {
      headers: { Authorization: `Bearer ${accessToken.value}` },
    });
    user.value = response.data;
    return user.value;
  };

  // 5. Đăng xuất Single Sign-Out
  const logout = () => {
    logoutLocal();
    const logoutUrl = new URL(`${SSO_BASE_URL}/oauth/logout`);
    logoutUrl.searchParams.set('post_logout_redirect_uri', window.location.origin);
    window.location.href = logoutUrl.toString();
  };

  const logoutLocal = () => {
    accessToken.value = null;
    refreshToken.value = null;
    user.value = null;
    localStorage.removeItem('sso_refresh_token');
  };

  const setTokens = (newAccess: string, newRefresh?: string) => {
    accessToken.value = newAccess;
    if (newRefresh) {
      refreshToken.value = newRefresh;
      localStorage.setItem('sso_refresh_token', newRefresh);
    }
  };

  return {
    isAuthenticated,
    user,
    accessToken,
    login,
    handleCallback,
    refresh,
    fetchUserInfo,
    logout,
  };
}
```

### Thiết lập Axios Interceptor cho API Gọi Vệ Tinh
```typescript
// src/api/httpClient.ts
import axios from 'axios';
import { useAuth } from '@/composables/useAuth';

export const apiClient = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
});

apiClient.interceptors.request.use((config) => {
  const { accessToken } = useAuth();
  if (accessToken.value) {
    config.headers.Authorization = `Bearer ${accessToken.value}`;
  }
  return config;
});

apiClient.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;
    if (error.response?.status === 401 && !originalRequest._retry) {
      originalRequest._retry = true;
      const { refresh } = useAuth();
      const success = await refresh();
      if (success) {
        return apiClient(originalRequest);
      }
    }
    return Promise.reject(error);
  }
);
```

---

## 3. Client 2: Mobile App Flutter (PKCE + Secure Storage)

### Thư viện khuyên dùng:
- `flutter_appauth: ^6.0.7` (Tuân thủ chuẩn OAuth 2.0 PKCE trên Android Custom Tabs & iOS ASWebAuthenticationSession).
- `flutter_secure_storage: ^9.2.2` (Lưu trữ Token an toàn trong Keychain / Keystore).

### Cấu hình `pubspec.yaml`:
```yaml
dependencies:
  flutter:
    sdk: flutter
  flutter_appauth: ^6.0.7
  flutter_secure_storage: ^9.2.2
  http: ^1.2.0
```

### Cấu hình Deep Link Android (`android/app/build.gradle`):
```groovy
defaultConfig {
    manifestPlaceholders += [
        'appAuthRedirectScheme': 'com.yourcompany.ssoapp'
    ]
}
```

### Cấu hình Deep Link iOS (`ios/Runner/Info.plist`):
```xml
<key>CFBundleURLTypes</key>
<array>
    <dict>
        <key>CFBundleTypeRole</key>
        <string>Editor</string>
        <key>CFBundleURLSchemes</key>
        <array>
            <string>com.yourcompany.ssoapp</string>
        </array>
    </dict>
</array>
```

### Dịch vụ Xác thực Flutter (`auth_service.dart`):
```dart
import 'package:flutter_appauth/flutter_appauth.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class AuthService {
  static const String ssoBaseUrl = 'https://sso.yourdomain.com';
  static const String clientId = '9d4c1b92-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
  static const String redirectUrl = 'com.yourcompany.ssoapp://auth-callback';

  final FlutterAppAuth _appAuth = const FlutterAppAuth();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

  // Đăng nhập PKCE
  Future<bool> login() async {
    try {
      final AuthorizationTokenResponse? result = await _appAuth.authorizeAndExchangeCode(
        AuthorizationTokenRequest(
          clientId,
          redirectUrl,
          serviceConfiguration: const AuthorizationServiceConfiguration(
            authorizationEndpoint: '$ssoBaseUrl/oauth/authorize',
            tokenEndpoint: '$ssoBaseUrl/oauth/token',
          ),
          scopes: ['openid', 'profile', 'email', 'roles', 'offline_access'],
        ),
      );

      if (result != null) {
        await _storage.write(key: 'access_token', value: result.accessToken);
        await _storage.write(key: 'refresh_token', value: result.refreshToken);
        await _storage.write(key: 'id_token', value: result.idToken);
        return true;
      }
      return false;
    } catch (e) {
      print('Login error: $e');
      return false;
    }
  }

  // Silent Refresh Token
  Future<String?> refreshToken() async {
    final String? currentRefreshToken = await _storage.read(key: 'refresh_token');
    if (currentRefreshToken == null) return null;

    try {
      final TokenResponse? response = await _appAuth.token(
        TokenRequest(
          clientId,
          redirectUrl,
          serviceConfiguration: const AuthorizationServiceConfiguration(
            authorizationEndpoint: '$ssoBaseUrl/oauth/authorize',
            tokenEndpoint: '$ssoBaseUrl/oauth/token',
          ),
          refreshToken: currentRefreshToken,
          scopes: ['openid', 'profile', 'email', 'roles', 'offline_access'],
        ),
      );

      if (response != null) {
        await _storage.write(key: 'access_token', value: response.accessToken);
        if (response.refreshToken != null) {
          await _storage.write(key: 'refresh_token', value: response.refreshToken);
        }
        return response.accessToken;
      }
    } catch (e) {
      await logout();
    }
    return null;
  }

  // Lấy UserInfo
  Future<Map<String, dynamic>?> getUserInfo() async {
    final String? token = await _storage.read(key: 'access_token');
    if (token == null) return null;

    final response = await http.get(
      Uri.parse('$ssoBaseUrl/oauth/userinfo'),
      headers: {'Authorization': 'Bearer $token'},
    );

    if (response.statusCode == 200) {
      return jsonDecode(response.body);
    } else if (response.statusCode == 401) {
      final newToken = await refreshToken();
      if (newToken != null) return getUserInfo();
    }
    return null;
  }

  // Đăng xuất
  Future<void> logout() async {
    await _storage.deleteAll();
  }
}
```

---

## 4. Client 3: Laravel Web App Con (JWKS Local Verify + Backchannel Logout)

### Đặc điểm kiến trúc:
- **Client Type:** Confidential (`client_secret` được bảo mật phía server).
- **Stateless Verification:** Thay vì gọi HTTP sang SSO IdP trong mỗi request, app con tải public key JWKS (caching 24 giờ), kiểm tra chữ ký RS256 và claims (`exp`, `sub`, `scopes`) hoàn toàn cục bộ (thời gian xử lý < 1ms).
- **Single Sign-Out:** Nhận POST webhook Backchannel Logout từ IdP để hủy bỏ session cục bộ.

### Middleware Xác Thực JWT Bằng JWKS (`VerifySatelliteJwtToken.php`):
*(Đã có sẵn tại `app/Infrastructure/Satellite/VerifySatelliteJwtToken.php` trong thư mục dự án)*

```php
// config/services.php
'sso' => [
    'base_url' => env('SSO_BASE_URL', 'https://sso.yourdomain.com'),
    'jwks_url' => env('SSO_JWKS_URL', 'https://sso.yourdomain.com/oauth/jwks'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect' => env('SSO_REDIRECT_URI', 'https://app-con.yourdomain.com/auth/callback'),
],
```

### Đăng ký Middleware trong App Con (`bootstrap/app.php`):
```php
$middleware->alias([
    'satellite.auth' => \App\Infrastructure\Satellite\VerifySatelliteJwtToken::class,
    'scope' => \App\Infrastructure\Satellite\CheckTokenScope::class,
]);
```

### Sử dụng bảo vệ Routes trong App Con:
```php
Route::middleware(['satellite.auth'])->group(function () {
    Route::get('/api/user', function (Request $request) {
        return response()->json([
            'user_id' => $request->attributes->get('oauth_user_id'),
            'client_id' => $request->attributes->get('oauth_client_id'),
            'scopes' => $request->attributes->get('oauth_scopes'),
        ]);
    });

    // Endpoint yêu cầu quyền hạn cụ thể
    Route::get('/api/reports', function () {
        return response()->json(['data' => 'Secret Financial Reports']);
    })->middleware('scope:roles,reports:read');
});
```

### Xử lý Backchannel Logout Webhook từ IdP:
Khi người dùng đăng xuất trên IdP hoặc Admin bấm "Force Logout", IdP gửi request POST tới `backchannel_logout_uri`:

```php
// routes/api.php trong App Con
Route::post('/sso/backchannel-logout', function (Request $request) {
    $logoutToken = $request->input('logout_token');
    if (! $logoutToken) {
        return response()->json(['error' => 'invalid_request'], 400);
    }

    // Decode logout_token không verify hoặc verify bằng public key
    $parts = explode('.', $logoutToken);
    if (count($parts) !== 3) {
        return response()->json(['error' => 'invalid_token'], 400);
    }

    $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
    $userId = $payload['sub'] ?? null;

    if ($userId) {
        // Thu hồi toàn bộ session/token của user này trong app con
        DB::table('sessions')->where('user_id', $userId)->delete();
        Cache::tags(["user:{$userId}"])->flush();
    }

    return response()->json(['status' => 'success'], 200);
});
```

---

## 5. Client 4: Backend Service / Microservice (Client Credentials M2M)

### Đặc điểm:
- **Client Type:** Machine-to-Machine (M2M / Confidential).
- **Grant Type:** `client_credentials`.
- Không có sự tương tác của người dùng cuối (không có browser/session).
- Token thường có thời gian hết hạn ngắn (ví dụ: 1 giờ), service chủ động cache access token cho đến khi sắp hết hạn (`expires_in - 60s`).

### 1. Tạo Client M2M trên IdP Developer Portal:
- Chọn loại: **Confidential (Machine-to-Machine)**.
- Gán scopes cho phép: e.g. `roles`, `internal:sync`.
- Lưu lại `client_id` và `client_secret`.

### 2. Lấy Access Token (cURL / HTTP):
```bash
curl -X POST https://sso.yourdomain.com/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "9d4c1b92-xxxx-xxxx-xxxx-xxxxxxxxxxxx",
    "client_secret": "your_secure_client_secret",
    "scope": "roles internal:sync"
  }'
```

**Phản hồi thành công (HTTP 200):**
```json
{
  "token_type": "Bearer",
  "expires_in": 1800,
  "access_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

### 3. Giao tiếp Service-to-Service trong PHP (Guzzle / Http Client):
```php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SsoM2MClient
{
    private string $idpUrl;
    private string $clientId;
    private string $clientSecret;

    public function __construct()
    {
        $this->idpUrl = config('services.sso.base_url');
        $this->clientId = config('services.sso.client_id');
        $this->clientSecret = config('services.sso.client_secret');
    }

    public function getAccessToken(): string
    {
        $cacheKey = "sso_m2m_token_{$this->clientId}";

        return Cache::remember($cacheKey, now()->addMinutes(25), function () {
            $response = Http::asJson()->post("{$this->idpUrl}/oauth/token", [
                'grant_type' => 'client_credentials',
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'scope' => 'roles',
            ]);

            if ($response->failed()) {
                throw new \RuntimeException('Failed to authenticate with SSO IdP: ' . $response->body());
            }

            return $response->json('access_token');
        });
    }

    public function callProtectedService(string $url, array $payload = []): array
    {
        $token = $this->getAccessToken();

        $response = Http::withToken($token)
            ->timeout(10)
            ->post($url, $payload);

        return $response->json();
    }
}
```

---

## 6. Bảng Tra Cứu Scopes, Endpoints & Lỗi Thường Gặp

### Scopes Hỗ Trợ:
| Scope | Mô tả | Claim trả về |
| :--- | :--- | :--- |
| `openid` | Định danh chuẩn OpenID Connect (bắt buộc cho OIDC) | `sub` |
| `profile` | Họ tên, ảnh đại diện, thời gian cập nhật | `name`, `picture`, `updated_at` |
| `email` | Địa chỉ email và trạng thái xác minh email | `email`, `email_verified` |
| `roles` | Danh sách vai trò người dùng trong hệ sinh thái | `roles` (array) |
| `offline_access` | Cấp refresh token cho client | `refresh_token` |

### Mã Lỗi OAuth 2.0 Thường Gặp:
| Lỗi | Nguyên nhân | Cách khắc phục |
| :--- | :--- | :--- |
| `invalid_client` | Sai client_id hoặc client_secret, hoặc client đã bị thu hồi (`revoked = 1`) | Kiểm tra lại Developer Portal và cập nhật thông tin client |
| `invalid_grant` | Code đã hết hạn / đã dùng rồi, hoặc refresh token không hợp lệ / đã xoay vòng | Khởi tạo lại luồng Auth Code mới |
| `unauthorized_client` | Client không được phép sử dụng grant_type yêu cầu | Cấu hình lại `grant_types` của client trong Developer Portal |
| `cors_origin_not_allowed` | Domain gọi API không trùng khớp với bất kỳ `redirect_uris` nào của client | Thêm domain gọi API (kèm port nếu có) vào Redirect URIs của client |
| `account_inactive` | Tài khoản người dùng bị khóa hoặc tạm ngưng trên IdP | Liên hệ quản trị viên để mở khóa |
