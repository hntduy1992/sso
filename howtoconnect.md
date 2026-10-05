# Hướng Dẫn Tích Hợp Ứng Dụng Vệ Tinh (SSO Satellite Integration Guide)
## Môi Trường Development (Dev) & Môi Trường Publish (Production)

> **Tài liệu hướng dẫn kết nối các ứng dụng vệ tinh (Satellite Applications)** vào hệ thống Single Sign-On (SSO) Identity Provider (Laravel 12, Passport 12, OpenID Connect 1.0, PKCE).

---

## Mục Lục
1. [Tổng Quan Kiến Trúc & Bảng So Sánh Môi Trường](#1-tổng-quan-kiến-trúc--bảng-so-sánh-môi-trường)
2. [Quy Trình Chuẩn Bị & Đăng Ký Client](#2-quy-trình-chuẩn-bị--đăng-ký-client)
   - [2.1 Đăng ký OAuth Client (Developer Portal)](#21-đăng-ký-oauth-client-developer-portal)
   - [2.2 Cấp Quyền Truy Cập Ứng Dụng (Application Access Control - Bắt Buộc)](#22-cấp-quyền-truy-cập-ứng-dụng-application-access-control---bắt-buộc)
3. [Cấu Hình Môi Trường Dev (Local Development)](#3-cấu-hình-môi-trường-dev-local-development)
4. [Cấu Hình Môi Trường Publish (Production / Staging)](#4-cấu-hình-môi-trường-publish-production--staging)
5. [Hướng Dẫn Tích Hợp Kỹ Thuật Theo Loại Client](#5-hướng-dẫn-tích-hợp-kỹ-thuật-theo-loại-client)
   - [Client Type 1: Single Page Application - SPA (React / Vue 3 / Angular)](#client-type-1-single-page-application---spa-react--vue-3--angular)
   - [Client Type 2: Traditional Web / Backend SSR (Laravel / NodeJS / Django / Java)](#client-type-2-traditional-web--backend-ssr-laravel--nodejs--django--java)
   - [Client Type 3: Mobile Application (Flutter / React Native / iOS / Android)](#client-type-3-mobile-application-flutter--react-native--ios--android)
   - [Client Type 4: Machine-to-Machine / Microservice (Client Credentials)](#client-type-4-machine-to-machine--microservice-client-credentials)
6. [Cơ Chế Xác Thực Cục Bộ Bằng JWKS (Stateless Local Verification)](#6-cơ-chế-xác-thực-cục-bộ-bằng-jwks-stateless-local-verification)
7. [Cơ Chế Đăng Xuất Tập Trung & Backchannel Logout](#7-cơ-chế-đăng-xuất-tập-trung--backchannel-logout)
8. [Các Lỗi Thường Gặp & Cách Khắc Phục (Troubleshooting)](#8-các-lỗi-thường-gặp--cách-khắc-phục-troubleshooting)

---

## 1. Tổng Quan Kiến Trúc & Bảng So Sánh Môi Trường

Hệ thống SSO hoạt động như một **OpenID Connect (OIDC) & OAuth 2.0 Identity Provider**. Các ứng dụng vệ tinh kết nối đến SSO để thực hiện xác thực tập trung, phân quyền dựa trên chức vụ / phòng ban, và chia sẻ phiên đăng nhập an toàn.

### Bảng Ma Trận Thông Số Giữa 2 Môi Trường

| Thông số / Endpoint | Môi trường Development (Dev) | Môi trường Publish (Production) | Ghi chú kỹ thuật |
| :--- | :--- | :--- | :--- |
| **SSO Base URL** | `http://localhost:8000` *(hoặc IP máy chủ dev)* | `https://sso.yourdomain.com` | Publish **bắt buộc** HTTPS |
| **OIDC Discovery** | `http://localhost:8000/.well-known/openid-configuration` | `https://sso.yourdomain.com/.well-known/openid-configuration` | Tự động khám phá endpoints & capabilities |
| **Authorize Endpoint** | `http://localhost:8000/oauth/authorize` | `https://sso.yourdomain.com/oauth/authorize` | Khởi tạo luồng đăng nhập giao diện web |
| **Token Endpoint** | `http://localhost:8000/oauth/token` | `https://sso.yourdomain.com/oauth/token` | Đổi code lấy token, refresh token, M2M |
| **UserInfo Endpoint** | `http://localhost:8000/oauth/userinfo` | `https://sso.yourdomain.com/oauth/userinfo` | Lấy claims: `sub`, `name`, `email`, `roles` |
| **JWKS Endpoint** | `http://localhost:8000/oauth/jwks` | `https://sso.yourdomain.com/oauth/jwks` | Public Key (RS256) xác thực JWT offline |
| **Token Revoke** | `http://localhost:8000/oauth/revoke` | `https://sso.yourdomain.com/oauth/revoke` | RFC 7009 thu hồi access/refresh token |
| **RP Logout** | `http://localhost:8000/oauth/logout` | `https://sso.yourdomain.com/oauth/logout` | Đăng xuất SSO tập trung |
| **Health Check** | `http://localhost:8000/health` | `https://sso.yourdomain.com/health` | Kiểm tra trạng thái DB, Cache, SSO Key |
| **Định dạng Redirect URI**| `http://localhost:3000/...`, `http://localhost:5173/...` | `https://app.yourdomain.com/...` | Phải ghi rõ port ở Dev, **không dùng wildcard** |
| **Yêu cầu SSL/TLS** | Không bắt buộc (HTTP cho phép) | **Bắt buộc TLS 1.2+** | Cookie `Secure`, OIDC spec |
| **Access Token TTL** | 30 phút | 30 phút | Thuật toán ký: RS256 |
| **Refresh Token TTL**| 14 ngày (Hỗ trợ xoay vòng) | 14 ngày (Hỗ trợ xoay vòng) | Tự động vô hiệu hóa token cũ khi refresh |

---

## 2. Quy Trình Chuẩn Bị & Đăng Ký Client

Để một ứng dụng vệ tinh có thể kết nối với SSO, bạn **phải thực hiện đủ 2 bước**:

### 2.1 Đăng ký OAuth Client (Developer Portal)

1. Đăng nhập vào giao diện SSO Portal:
   - **Dev:** `http://localhost:8000/login`
   - **Publish:** `https://sso.yourdomain.com/login`
2. Truy cập menu **Developer Portal** (`/developer/clients`).
3. Bấm **"Đăng ký Ứng dụng mới"** và điền thông tin:
   - **Tên ứng dụng:** Ví dụ: *CRM Vệ Tinh*, *ERP Frontend*, *Mobile Sales App*.
   - **Loại ứng dụng (Client Type):**
     - `PUBLIC`: Dành cho ứng dụng chạy trên trình duyệt (Vue/React SPA) hoặc ứng dụng di động (Flutter, React Native). Các client này **không** có `client_secret` và **bắt buộc dùng PKCE S256**.
     - `CONFIDENTIAL`: Dành cho ứng dụng có backend riêng (Laravel, NodeJS, Django, Java Spring, microservice). Được cấp `client_secret` bảo mật.
   - **Redirect URIs (Mỗi URI một dòng):**
     - **Rất quan trọng:** Cơ chế CORS của SSO sử dụng danh sách này để tự động cấp phép (whitelisting) cho trình duyệt gọi API.
     - Phải khớp chính xác giao thức (`http://` hoặc `https://`), tên miền/IP và số hiệu cổng (port).
     - *Ví dụ Dev:*
       ```text
       http://localhost:5173/auth/callback
       http://localhost:3000/auth/callback
       ```
     - *Ví dụ Publish:*
       ```text
       https://crm.yourdomain.com/auth/callback
       ```
   - **Backchannel Logout URI (Tùy chọn):** URL webhook của app vệ tinh để SSO gửi thông báo khi user bị đăng xuất tập trung (xem mục 7).
4. Nhấn **"Đăng ký"**:
   - Lưu lại `Client ID` (UUID).
   - Nếu là ứng dụng `CONFIDENTIAL`, hệ thống sẽ hiển thị `Client Secret` **chỉ một lần duy nhất**. Hãy sao chép và lưu vào biến môi trường an toàn.

---

### 2.2 Cấp Quyền Truy Cập Ứng Dụng (Application Access Control - Bắt Buộc)

> ⚠️ **LƯU Ý CỰC KỲ QUAN TRỌNG:**
> Hệ thống SSO này áp dụng cơ chế bảo mật **Deny-by-default** (Mặc định từ chối). Nếu một ứng dụng được tạo ra nhưng chưa được cấp quyền trong Admin Panel, chỉ có tài khoản `admin` mới đăng nhập được, còn tất cả người dùng thông thường khi đăng nhập sẽ nhận thông báo lỗi **403 - Access Denied** (`Bạn không có quyền truy cập ứng dụng này`).

#### Hướng dẫn cấp quyền:
1. Đăng nhập bằng tài khoản Quản trị viên (`admin@sso.local` hoặc admin tương đương).
2. Vào **Admin Panel** > **Phân quyền ứng dụng** (`/admin/application-access`).
3. Chọn ứng dụng vệ tinh vừa tạo.
4. Chọn một trong hai hình thức cấp quyền (hoặc kết hợp cả hai):
   - **Cấp quyền theo Phòng ban (Department Grant):** Tất cả nhân sự thuộc phòng ban được chọn (và đang có hợp đồng/vị trí active) sẽ tự động được phép đăng nhập.
   - **Cấp quyền theo Người dùng cụ thể (User Grant):** Chỉ đích danh người dùng được phép đăng nhập.

---

## 3. Cấu Hình Môi Trường Dev (Local Development)

### 3.1 Khởi động SSO Server phía Local
Nếu bạn tự chạy SSO trên máy phát triển:
```bash
# Di chuyển vào thư mục SSO
cd /path/to/sso

# Cài đặt thư viện (nếu mới clone)
composer install
npm install

# Tạo file cấu hình và migrate CSDL mẫu
cp .env.example .env
php artisan key:generate
php artisan passport:keys
php artisan migrate --seed

# Khởi chạy server SSO
php artisan serve --port=8000
npm run dev
```

Tài khoản mẫu có sẵn sau khi seed:
- **Super Admin:** `admin@sso.local` / `Admin@123456`
- **Developer:** `dev@sso.local` / `Dev@123456`
- **User bình thường:** `user@sso.local` / `User@123456`

### 3.2 File `.env` mẫu cho Ứng Dụng Vệ Tinh (Dev)

#### A. Dành cho SPA (Vite / React / Vue):
```env
# .env.development
VITE_SSO_BASE_URL=http://localhost:8000
VITE_SSO_CLIENT_ID=9e2d3f45-1234-4567-89ab-cdef01234567
VITE_SSO_REDIRECT_URI=http://localhost:5173/auth/callback
VITE_SSO_SCOPES="openid profile email roles offline_access"
```

#### B. Dành cho Backend Web App (Laravel / PHP / NodeJS):
```env
# .env cho app con
SSO_BASE_URL=http://localhost:8000
SSO_CLIENT_ID=9e2d3f45-1234-4567-89ab-cdef01234567
SSO_CLIENT_SECRET=AbCdEf123456...your_secret_here...
SSO_REDIRECT_URI=http://localhost:8080/auth/callback
SSO_JWKS_URL=http://localhost:8000/oauth/jwks
```

---

## 4. Cấu Hình Môi Trường Publish (Production / Staging)

### 4.1 Yêu Cầu Hạ Tầng SSO Production
1. **Bắt buộc HTTPS:** Mọi request qua `https://sso.yourdomain.com`. Hủy bỏ hoàn toàn các cấu hình `http://`.
2. **Reverse Proxy & TrustProxies:** Đảm bảo NGINX/Cloudflare chuyển tiếp đúng header `X-Forwarded-Proto: https`, `X-Forwarded-For`, `X-Forwarded-Host`.
3. **CORS:** Đảm bảo mọi domain vệ tinh chạy trên trình duyệt phải được khai báo chính xác trong `Redirect URIs` của Client tương ứng trên SSO.
4. **Passport Keys Persistence:** Khóa bảo mật `oauth-private.key` và `oauth-public.key` phải được bảo vệ nghiêm ngặt (chmod 600) hoặc inject qua biến môi trường `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY`.

### 4.2 File `.env` mẫu cho Ứng Dụng Vệ Tinh (Publish)

#### A. Dành cho SPA (Publish):
```env
# .env.production
VITE_SSO_BASE_URL=https://sso.yourdomain.com
VITE_SSO_CLIENT_ID=a1b2c3d4-xxxx-xxxx-xxxx-xxxxxxxxxxxx
VITE_SSO_REDIRECT_URI=https://crm.yourdomain.com/auth/callback
VITE_SSO_SCOPES="openid profile email roles offline_access"
```

#### B. Dành cho Backend Web App (Publish):
```env
# .env.production cho app con
SSO_BASE_URL=https://sso.yourdomain.com
SSO_CLIENT_ID=a1b2c3d4-xxxx-xxxx-xxxx-xxxxxxxxxxxx
SSO_CLIENT_SECRET=YOUR_SUPER_SECURE_PRODUCTION_SECRET
SSO_REDIRECT_URI=https://erp.yourdomain.com/auth/callback
SSO_JWKS_URL=https://sso.yourdomain.com/oauth/jwks
```

---

## 5. Hướng Dẫn Tích Hợp Kỹ Thuật Theo Loại Client

### Client Type 1: Single Page Application - SPA (React / Vue 3 / Angular)

- **Chuẩn:** OAuth 2.0 Authorization Code Flow + **PKCE (Proof Key for Code Exchange)**.
- **Loại Client:** `PUBLIC`.
- **Tuyệt đối không lưu client_secret trên code frontend.**

#### Luồng hoạt động (PKCE Flow):
```
[User Browser]             [Satellite SPA]               [SSO IdP Server]
      |                           |                             |
      |-- 1. Click "Login" ------>|                             |
      |                           |-- 2. Tạo verifier & S256 -->|
      |<-- 3. Redirect /authorize ------------------------------|
      |-- 4. Đăng nhập & Duyệt quyền trên SSO IdP ------------->|
      |<-- 5. Redirect về /auth/callback?code=XYZ --------------|
      |-- 6. Gửi code & code_verifier ------------------------->|
      |<-- 7. Trả Access Token + Refresh Token -----------------|
      |-- 8. Gọi /oauth/userinfo với Bearer Token ------------->|
      |<-- 9. Trả thông tin User -------------------------------|
```

#### Code mẫu JavaScript/TypeScript (Tiện ích PKCE & Xử lý đăng nhập):

```typescript
// ssoService.ts
const SSO_URL = import.meta.env.VITE_SSO_BASE_URL;
const CLIENT_ID = import.meta.env.VITE_SSO_CLIENT_ID;
const REDIRECT_URI = import.meta.env.VITE_SSO_REDIRECT_URI;
const SCOPES = 'openid profile email roles offline_access';

function base64UrlEncode(buffer: ArrayBuffer): string {
  const bytes = new Uint8Array(buffer);
  let binary = '';
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]);
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
}

function generateRandomString(length = 64): string {
  const array = new Uint8Array(length);
  window.crypto.getRandomValues(array);
  return base64UrlEncode(array.buffer).substring(0, length);
}

async function generateCodeChallenge(verifier: string): Promise<string> {
  const encoder = new TextEncoder();
  const data = encoder.encode(verifier);
  const digest = await window.crypto.subtle.digest('SHA-256', data);
  return base64UrlEncode(digest);
}

// 1. Chuyển hướng sang trang đăng nhập SSO
export async function redirectToLogin() {
  const verifier = generateRandomString(64);
  const state = generateRandomString(32);

  sessionStorage.setItem('sso_verifier', verifier);
  sessionStorage.setItem('sso_state', state);

  const challenge = await generateCodeChallenge(verifier);

  const params = new URLSearchParams({
    client_id: CLIENT_ID,
    redirect_uri: REDIRECT_URI,
    response_type: 'code',
    scope: SCOPES,
    state: state,
    code_challenge: challenge,
    code_challenge_method: 'S256',
  });

  window.location.href = `${SSO_URL}/oauth/authorize?${params.toString()}`;
}

// 2. Xử lý Callback tại trang /auth/callback
export async function handleAuthCallback(): Promise<any> {
  const params = new URLSearchParams(window.location.search);
  const code = params.get('code');
  const state = params.get('state');

  const savedState = sessionStorage.getItem('sso_state');
  const verifier = sessionStorage.getItem('sso_verifier');

  if (!state || state !== savedState) {
    throw new Error('CSRF State mismatch: Request không hợp lệ!');
  }
  if (!code || !verifier) {
    throw new Error('Dữ liệu xác thực không đầy đủ.');
  }

  // Đổi code lấy token
  const tokenResponse = await fetch(`${SSO_URL}/oauth/token`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      grant_type: 'authorization_code',
      client_id: CLIENT_ID,
      redirect_uri: REDIRECT_URI,
      code_verifier: verifier,
      code: code,
    }),
  });

  if (!tokenResponse.ok) {
    const errorData = await tokenResponse.json();
    throw new Error(errorData.error_description || 'Đổi token thất bại');
  }

  const tokenData = await tokenResponse.json();
  sessionStorage.removeItem('sso_verifier');
  sessionStorage.removeItem('sso_state');

  // Lưu Access Token trong bộ nhớ / Session và Refresh Token
  localStorage.setItem('sso_refresh_token', tokenData.refresh_token);

  // Lấy UserInfo
  const user = await fetchUserInfo(tokenData.access_token);
  return { tokens: tokenData, user };
}

// 3. Lấy thông tin người dùng
export async function fetchUserInfo(accessToken: string) {
  const response = await fetch(`${SSO_URL}/oauth/userinfo`, {
    headers: {
      Authorization: `Bearer ${accessToken}`,
      Accept: 'application/json',
    },
  });
  return await response.json();
}

// 4. Đăng xuất Single Sign-Out
export function ssoLogout() {
  localStorage.removeItem('sso_refresh_token');
  const logoutUrl = new URL(`${SSO_URL}/oauth/logout`);
  logoutUrl.searchParams.set('post_logout_redirect_uri', window.location.origin);
  window.location.href = logoutUrl.toString();
}
```

---

### Client Type 2: Traditional Web / Backend SSR (Laravel / NodeJS / Django / Java)

- **Chuẩn:** OAuth 2.0 Authorization Code Flow truyền thống.
- **Loại Client:** `CONFIDENTIAL` (Sử dụng `client_secret` được bảo vệ phía server).

#### Triển khai trên Laravel App Vệ Tinh:

1. **Cấu hình `config/services.php`:**
```php
'sso' => [
    'base_url' => env('SSO_BASE_URL', 'https://sso.yourdomain.com'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'redirect' => env('SSO_REDIRECT_URI'),
    'jwks_url' => env('SSO_JWKS_URL'),
],
```

2. **Controller xử lý Login & Callback (`SsoAuthController.php`):**
```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class SsoAuthController extends Controller
{
    public function redirect(Request $request)
    {
        $state = Str::random(40);
        $request->session()->put('sso_state', $state);

        $query = http_build_query([
            'client_id' => config('services.sso.client_id'),
            'redirect_uri' => config('services.sso.redirect'),
            'response_type' => 'code',
            'scope' => 'openid profile email roles offline_access',
            'state' => $state,
        ]);

        return redirect(config('services.sso.base_url') . '/oauth/authorize?' . $query);
    }

    public function callback(Request $request)
    {
        $savedState = $request->session()->pull('sso_state');
        if (! $savedState || $savedState !== $request->query('state')) {
            abort(403, 'CSRF verification failed.');
        }

        // Đổi authorization code lấy access token
        $response = Http::asForm()->post(config('services.sso.base_url') . '/oauth/token', [
            'grant_type' => 'authorization_code',
            'client_id' => config('services.sso.client_id'),
            'client_secret' => config('services.sso.client_secret'),
            'redirect_uri' => config('services.sso.redirect'),
            'code' => $request->query('code'),
        ]);

        if ($response->failed()) {
            abort(401, 'Không thể lấy token từ SSO: ' . $response->body());
        }

        $tokens = $response->json();
        $accessToken = $tokens['access_token'];

        // Lấy thông tin user
        $userResponse = Http::withToken($accessToken)
            ->get(config('services.sso.base_url') . '/oauth/userinfo');

        $ssoUser = $userResponse->json();

        // Đồng bộ user vào database app con và đăng nhập session
        $localUser = User::updateOrCreate(
            ['email' => $ssoUser['email']],
            [
                'name' => $ssoUser['name'],
                'sso_sub' => $ssoUser['sub'],
            ]
        );

        Auth::login($localUser);
        $request->session()->put('sso_access_token', $accessToken);
        $request->session()->put('sso_refresh_token', $tokens['refresh_token'] ?? null);

        return redirect()->intended('/dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $logoutUrl = config('services.sso.base_url') . '/oauth/logout?' . http_build_query([
            'post_logout_redirect_uri' => url('/'),
        ]);

        return redirect($logoutUrl);
    }
}
```

---

### Client Type 3: Mobile Application (Flutter / React Native / iOS / Android)

- **Chuẩn:** Authorization Code Flow + PKCE qua In-App Browser (ASWebAuthenticationSession trên iOS, Chrome Custom Tabs trên Android).
- **Loại Client:** `PUBLIC`.
- **Redirect URI Scheme:** Sử dụng Deep Link / Custom URL Scheme, ví dụ: `com.company.crm://oauth-callback`.

#### Cấu hình ví dụ với Flutter (`flutter_appauth`):
```dart
import 'package:flutter_appauth/flutter_appauth.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

class AuthService {
  // Thay đổi URL theo môi trường (Dev vs Publish)
  static const String ssoBaseUrl = 'https://sso.yourdomain.com';
  static const String clientId = '9e2d3f45-xxxx-xxxx-xxxx-xxxxxxxxxxxx';
  static const String redirectUrl = 'com.company.crm://oauth-callback';

  final FlutterAppAuth _appAuth = const FlutterAppAuth();
  final FlutterSecureStorage _storage = const FlutterSecureStorage();

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

      if (result != null && result.accessToken != null) {
        await _storage.write(key: 'access_token', value: result.accessToken);
        await _storage.write(key: 'refresh_token', value: result.refreshToken);
        return true;
      }
      return false;
    } catch (e) {
      print('Mobile Login Error: $e');
      return false;
    }
  }
}
```

---

### Client Type 4: Machine-to-Machine / Microservice (Client Credentials)

- **Chuẩn:** `client_credentials` grant.
- **Loại Client:** `CONFIDENTIAL`.
- **Mục đích:** Giao tiếp giữa 2 backend services mà không có người dùng cuối can thiệp.

#### Lấy Token M2M (cURL / HTTP):
```bash
curl -X POST https://sso.yourdomain.com/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "client_credentials",
    "client_id": "YOUR_CLIENT_ID",
    "client_secret": "YOUR_CLIENT_SECRET",
    "scope": "roles"
  }'
```

#### Phản hồi (HTTP 200):
```json
{
  "token_type": "Bearer",
  "expires_in": 1800,
  "access_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

*Khuyến nghị:* Service vệ tinh nên cache token trong Redis / Memory trong khoảng `expires_in - 60 giây` để không phải gọi lại SSO trước mỗi request.

---

## 6. Cơ Chế Xác Thực Cục Bộ Bằng JWKS (Stateless Local Verification)

Để đảm bảo hiệu năng cao nhất (thời gian phản hồi dưới 1 mili-giây và giảm tải 100% cho SSO IdP), các ứng dụng vệ tinh có backend API **không cần gọi HTTP sang SSO để kiểm tra token mỗi khi có request**.

Thay vào đó, ứng dụng vệ tinh tải Public Key từ `/oauth/jwks` của SSO một lần (cache 24 giờ) và tự động xác minh chữ ký RSA SHA-256 (RS256) cục bộ.

### Middleware mẫu bằng PHP (`VerifySatelliteJwtToken.php`):
*(Bạn có thể tham khảo hoặc copy file có sẵn trong project: `app/Infrastructure/Satellite/VerifySatelliteJwtToken.php`)*

```php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Lcobucci\JWT\Configuration;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\Clock\SystemClock;

class VerifySatelliteJwtToken
{
    public function handle(Request $request, Closure $next)
    {
        $tokenString = $request->bearerToken();
        if (! $tokenString) {
            return response()->json(['error' => 'unauthenticated', 'message' => 'Missing Bearer Token'], 401);
        }

        try {
            $publicKey = $this->getIdpPublicKey();
            $config = Configuration::forAsymmetricSigner(
                new Sha256(),
                InMemory::plainText(''),
                InMemory::plainText($publicKey)
            );

            $token = $config->parser()->parse($tokenString);

            // 1. Kiểm tra chữ ký RS256
            if (! $config->validator()->validate($token, new SignedWith(new Sha256(), InMemory::plainText($publicKey)))) {
                return response()->json(['error' => 'invalid_token', 'message' => 'Chữ ký JWT không hợp lệ'], 401);
            }

            // 2. Kiểm tra thời hạn hết hạn
            $clock = new SystemClock(new \DateTimeZone('UTC'));
            if (! $config->validator()->validate($token, new LooseValidAt($clock, new \DateInterval('PT60S')))) {
                return response()->json(['error' => 'token_expired', 'message' => 'Token đã hết hạn'], 401);
            }

            // Gán claims vào request để controller sử dụng
            $claims = $token->claims()->all();
            $request->attributes->set('jwt_user_id', $claims['sub'] ?? null);
            $request->attributes->set('jwt_roles', $claims['roles'] ?? []);
            $request->attributes->set('jwt_scopes', $claims['scopes'] ?? []);

        } catch (\Throwable $e) {
            return response()->json(['error' => 'invalid_token', 'message' => $e->getMessage()], 401);
        }

        return $next($request);
    }

    private function getIdpPublicKey(): string
    {
        return Cache::remember('sso_jwks_public_key', now()->addHours(24), function () {
            $jwksUrl = config('services.sso.jwks_url', 'https://sso.yourdomain.com/oauth/jwks');
            $response = Http::timeout(5)->get($jwksUrl);
            
            if ($response->successful()) {
                $keys = $response->json('keys');
                if (!empty($keys[0]['n']) && !empty($keys[0]['e'])) {
                    // Nếu là định dạng JWK Modulus & Exponent
                    // Hoặc lấy trực tiếp từ certificate x5c nếu có
                    if (!empty($keys[0]['x5c'][0])) {
                        return "-----BEGIN CERTIFICATE-----\n" .
                            chunk_split($keys[0]['x5c'][0], 64, "\n") .
                            "-----END CERTIFICATE-----\n";
                    }
                }
            }
            throw new \RuntimeException('Không thể lấy Public Key từ SSO IdP.');
        });
    }
}
```

---

## 7. Cơ Chế Đăng Xuất Tập Trung & Backchannel Logout

Hệ thống hỗ trợ 2 cơ chế đăng xuất:

### 7.1 Frontchannel RP-Initiated Logout (Người dùng tự đăng xuất)
Khi người dùng ấn "Đăng xuất" trên ứng dụng vệ tinh:
1. Ứng dụng vệ tinh xóa session / token cục bộ.
2. Điều hướng người dùng tới:
   ```text
   GET https://sso.yourdomain.com/oauth/logout?post_logout_redirect_uri=https://app.yourdomain.com
   ```
3. SSO hủy phiên đăng nhập SSO và chuyển hướng người dùng trở lại `post_logout_redirect_uri`.

### 7.2 Backchannel Logout (SSO chủ động thông báo khi User bị Force Logout)
Khi Quản trị viên SSO thực hiện **"Force Logout"** một tài khoản từ Admin Panel:
1. SSO gửi request `POST` trực tiếp tới `Backchannel Logout URI` đã đăng ký của ứng dụng vệ tinh.
2. Request chứa tham số: `logout_token` (được ký bằng RS256 chứa claim `sub` là ID của user).
3. Ứng dụng vệ tinh xử lý thu hồi session:

```php
// Route::post('/api/sso/backchannel-logout', ...) trong app con:
public function handleBackchannelLogout(Request $request)
{
    $logoutToken = $request->input('logout_token');
    if (! $logoutToken) {
        return response()->json(['error' => 'invalid_request'], 400);
    }

    $parts = explode('.', $logoutToken);
    if (count($parts) === 3) {
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        $userId = $payload['sub'] ?? null;

        if ($userId) {
            // Hủy bỏ tất cả phiên session của user này trong app con
            DB::table('sessions')->where('user_id', $userId)->delete();
            Cache::tags(["user_session:{$userId}"])->flush();
        }
    }

    return response()->json(['status' => 'success']);
}
```

---

## 8. Các Lỗi Thường Gặp & Cách Khắc Phục (Troubleshooting)

### 1. Lỗi `cors_origin_not_allowed` (403 Forbidden)
- **Hiện tượng:** Trình duyệt báo đỏ khi gọi `OPTIONS` hoặc `POST /oauth/token` từ frontend SPA.
- **Nguyên nhân:** SSO sử dụng middleware `OAuthCorsMiddleware` để kiểm tra nguồn gốc (Origin). Nếu host hoặc port của bạn không nằm trong danh sách `Redirect URIs` của Client đã đăng ký, request sẽ bị từ chối.
- **Cách xử lý:** Vào **Developer Portal** (`/developer/clients`) > Chỉnh sửa client > Thêm chính xác domain và cổng của bạn vào danh sách Redirect URIs (ví dụ: `http://localhost:5173/auth/callback` thay vì chỉ để `http://localhost`).

---

### 2. Lỗi `403 - Bạn không có quyền truy cập ứng dụng này` (Access Denied)
- **Hiện tượng:** Sau khi nhập user/pass thành công trên SSO, màn hình hiển thị trang báo lỗi không có quyền.
- **Nguyên nhân:** SSO cấu hình cơ chế bảo mật **Deny-by-default**. Người dùng chưa được gán quyền cá nhân hoặc phòng ban của người dùng chưa được cấp quyền truy cập Client này.
- **Cách xử lý:** Đăng nhập tài khoản Admin SSO > Vào mục **Phân quyền ứng dụng** (`/admin/application-access`) > Gán quyền cho User hoặc Phòng ban tương ứng.

---

### 3. Lỗi `invalid_client` (401 Unauthorized)
- **Hiện tượng:** Gọi `/oauth/token` trả về `{"error": "invalid_client"}`.
- **Nguyên nhân:**
  - Client ID hoặc Client Secret không chính xác.
  - Client đã bị thu hồi (`revoked = true`).
  - Gửi `client_secret` trong request của ứng dụng kiểu `PUBLIC` (Client Public không được có secret).

---

### 4. Lỗi `invalid_grant` (400 Bad Request)
- **Hiện tượng:** Đổi code lấy token thất bại.
- **Nguyên nhân:**
  - `code` đã được sử dụng một lần trước đó (Authorization code chỉ dùng được duy nhất 1 lần).
  - `code` đã quá thời hạn (hết hạn sau vài phút).
  - Chuỗi `code_verifier` gửi lên không tạo ra được `code_challenge` ban đầu.
  - Refresh Token đã bị thu hồi hoặc đã bị xoay vòng (Refresh Token Rotation).

---

### 5. Lỗi `Token signature verification failed` khi xác thực cục bộ qua JWKS
- **Hiện tượng:** App con giải mã token báo lỗi sai chữ ký.
- **Nguyên nhân:** 
  - Khóa công khai lưu trong cache của app con đã cũ (sau khi SSO chạy lại lệnh `php artisan passport:keys`).
  - App con đang trỏ nhầm `JWKS_URL` giữa môi trường Dev và Publish.
- **Cách xử lý:** Xóa cache public key trên app con (`Cache::forget('sso_jwks_public_key')`) để app con tải lại JWKS mới nhất từ SSO.

---

### 6. Lỗi `429 Too Many Requests`
- **Nguyên nhân:** Vượt quá giới hạn Rate Limiting an toàn của hệ thống SSO:
  - Tra đổi Token (`/oauth/token`): Tối đa **30 requests/phút** theo `[client_id + IP]`.
  - Thông tin người dùng (`/oauth/userinfo`): Tối đa **120 requests/phút** theo `[user_id + IP]`.
  - Introspection (`/oauth/introspect`): Tối đa **60 requests/phút**.
- **Cách xử lý:** App vệ tinh cần cache access token hoặc user info trong phiên làm việc, không gọi lại token endpoint liên tục trong vòng lặp.

---

*Tài liệu được biên soạn và bảo trì bởi Bộ phận Kỹ thuật Hệ thống SSO.*  
*Nếu cần hỗ trợ kỹ thuật thêm, vui lòng liên hệ Ban Quản Trị Hệ Thống.*
