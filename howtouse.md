# Sổ Tay Kỹ Thuật Tích Hợp & Đặc Tả API SSO (Satellite Integration & API Handbook)

> **Tài liệu tham chiếu chuẩn dành cho các ứng dụng vệ tinh (Satellite Applications)**  
> Cung cấp chi tiết bản đồ router, yêu cầu đầu vào (input headers/params), cấu trúc dữ liệu trả về (response payloads), và hướng dẫn chi tiết cách khai thác, phân tách, lưu trữ dữ liệu phản hồi phục vụ xác thực & phân quyền (RBAC/ABAC/HRM) trên ứng dụng vệ tinh.

---

## Mục Lục

1. [Kiến Trúc Tổng Quan & Bản Đồ Router SSO](#1-kiến-trúc-tổng-quan--bản-đồ-router-sso)
2. [Chi Tiết Từng Router: Input & Output Specification](#2-chi-tiết-từng-router-input--output-specification)
   - [2.1 Khởi tạo Đăng nhập & Ủy quyền: `GET /oauth/authorize`](#21-khởi-tạo-đăng-nhập--ủy-quyền-get-oauthauthorize)
   - [2.2 Trao đổi & Cấp phát Token: `POST /oauth/token`](#22-trao-đổi--cấp-phát-token-post-oauthtoken)
     - [Luồng Authorization Code (kèm PKCE)](#a-luồng-authorization-code-grant-spa-mobile-backend-web)
     - [Luồng Refresh Token (Làm mới token tự động)](#b-luồng-refresh-token-grant)
     - [Luồng Client Credentials (Giao tiếp M2M giữa các dịch vụ)](#c-luồng-client-credentials-grant-m2m-service-to-service)
   - [2.3 Lấy Thông Tin Người Dùng & Hồ Sơ HRM: `GET|POST /oauth/userinfo`](#23-lấy-thông-tin-người-dùng--hồ-sơ-hrm-getpost-oauthuserinfo)
   - [2.4 Lấy Public Key Xác thực Offline: `GET /oauth/jwks`](#24-lấy-public-key-xác-thực-offline-get-oauthjwks)
   - [2.5 Kiểm Tra Trạng Thái Token Thời Gian Thực: `POST /oauth/introspect`](#25-kiểm-tra-trạng-thái-token-thời-gian-thực-post-oauthintrospect)
   - [2.6 Thu Hồi Token Chủ Động: `POST /oauth/revoke`](#26-thu-hồi-token-chủ-động-post-oauthrevoke)
   - [2.7 Đăng Xuất Tập Trung OIDC: `GET|POST /oauth/logout`](#27-đăng-xuất-tập-trung-oidc-getpost-oauthlogout)
   - [2.8 Webhook Đăng Xuất Phía App Vệ Tinh: `POST {backchannel_logout_uri}`](#28-webhook-đăng-xuất-phía-app-vệ-tinh-post-backchannel_logout_uri)
   - [2.9 Khám Phá Cấu Hình Tự Động: `GET /.well-known/openid-configuration`](#29-khám-phá-cấu-hình-tự-động-get-well-knownopenid-configuration)
   - [2.10 Giám Sát Sức Khỏe SSO IdP: `GET /health` hoặc `GET /api/health`](#210-giám-sát-sức-khỏe-sso-idp-get-health-hoặc-get-apihealth)
3. [Hướng Dẫn Ứng Dụng Vệ Tinh Khai Thác & Sử Dụng Dữ Liệu Phản Hồi](#3-hướng-dẫn-ứng-dụng-vệ-tinh-khai-thác--sử-dụng-dữ-liệu-phản-hồi)
   - [3.1 Bóc Tách & Sử Dụng Access Token JWT](#31-bóc-tách--sử-dụng-access-token-jwt)
   - [3.2 Cơ Chế Xác Thực Chữ Ký Cục Bộ Bằng JWKS (Offline RS256 Verification)](#32-cơ-chế-xác-thực-chữ-ký-cục-bộ-bằng-jwks-offline-rs256-verification)
   - [3.3 Ánh Xạ Dữ Liệu UserInfo Vào Cơ Sở Dữ Liệu Ứng Dụng Vệ Tinh](#33-ánh-xạ-dữ-liệu-userinfo-vào-cơ-sở-dữ-liệu-ứng-dụng-vệ-tinh)
   - [3.4 Tự Động Làm Mới Token (Silent Auto-Refresh Interceptor)](#34-tự-động-làm-mới-token-silent-auto-refresh-interceptor)
   - [3.5 Xử Lý Đồng Bộ Đăng Xuất (Xử Lý Webhook Backchannel Logout)](#35-xử-lý-đồng-bộ-đăng-xuất-xử-lý-webhook-backchannel-logout)
4. [Bảng Tra Cứu Mã Lỗi & Cách Xử Lý Phía Client (Error Matrix)](#4-bảng-tra-cứu-mã-lỗi--cách-xử-lý-phía-client-error-matrix)

---

## 1. Kiến Trúc Tổng Quan & Bản Đồ Router SSO

Hệ thống Single Sign-On (SSO) hoạt động dựa trên chuẩn **OpenID Connect Core 1.0** và **OAuth 2.0 (RFC 6749, RFC 7662, RFC 7009)**. Mọi ứng dụng vệ tinh đều tương tác với SSO thông qua các router chuẩn hóa sau:

```
+-----------------------------------------------------------------------------------------+
|                                SSO IDENTITY PROVIDER                                    |
+-----------------------------------------------------------------------------------------+
       ^                           ^                           ^                      |
       | 1. Authorize              | 2. Exchange / Refresh     | 3. UserInfo          | 5. Backchannel
       |    GET /oauth/authorize   |    POST /oauth/token      |    GET /oauth/userinfo|    Logout
       |                           |                           |                      |    Webhook
+--------------+            +---------------+           +---------------+             v
| Satellite    |            | Satellite     |           | Satellite     |      +---------------+
| SPA / Mobile |            | Backend API   |           | Microservices |      | Satellite App |
| (Public PKCE)|            | (Confidential)|           | (JWT Verify)  |      | Webhook Recv  |
+--------------+            +---------------+           +---------------+      +---------------+
```

### Bảng Tổng Hợp Router SSO

| Tên Router | Phương thức | Đường dẫn Route | Cơ chế xác thực | Tốc độ giới hạn (Rate Limit) | Mục đích chính |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **Authorize** | `GET` | `/oauth/authorize` | Session Cookie (Web) | - | Bắt đầu quy trình đăng nhập, hiển thị form SSO & duyệt quyền |
| **Token Exchange** | `POST` | `/oauth/token` | Client Secret hoặc PKCE | 30 requests/phút per `client_id+IP` | Đổi code lấy token, làm mới refresh_token, cấp M2M token |
| **UserInfo** | `GET`, `POST` | `/oauth/userinfo` | `Bearer <access_token>` | 120 requests/phút per user/IP | Trả thông tin nhân sự (HRM), chức vụ, phòng ban, email, roles |
| **JWKS** | `GET` | `/oauth/jwks` | Không yêu cầu (Public) | Cache HTTP 1 giờ | Public Key RS256 để app vệ tinh giải mã JWT cục bộ (offline) |
| **Introspection**| `POST` | `/oauth/introspect`| Basic Auth hoặc Body Secret| 60 requests/phút per IP | Kiểm tra hiệu lực token thời gian thực (RFC 7662) |
| **Revocation** | `POST` | `/oauth/revoke` | Basic Auth hoặc Body Secret| 30 requests/phút per client | Thu hồi Access Token / Refresh Token (RFC 7009) |
| **RP Logout** | `GET`, `POST` | `/oauth/logout` | Session / `id_token_hint` | - | Đăng xuất tập trung toàn bộ phiên SSO |
| **Backchannel** | `POST` | `{client_logout_uri}`| Signed JWT (`logout_token`)| - | SSO chủ động thông báo cho app vệ tinh hủy session |
| **Discovery** | `GET` | `/.well-known/openid-configuration` | Public | - | Auto-discovery endpoint & metadata chuẩn OpenID |
| **Health Check**| `GET` | `/health` hoặc `/api/health` | Public | - | Kiểm tra kết nối DB, Cache, Key của máy chủ SSO |

---

## 2. Chi Tiết Từng Router: Input & Output Specification

---

### 2.1 Khởi tạo Đăng nhập & Ủy quyền: `GET /oauth/authorize`

- **Mục đích:** Ứng dụng vệ tinh chuyển hướng trình duyệt của người dùng đến endpoint này để bắt đầu phiên đăng nhập SSO.
- **Ràng buộc bảo mật:**
  - Với Client **`PUBLIC`** (SPA React/Vue, Mobile App Flutter/React Native): **Bắt buộc sử dụng PKCE (`code_challenge` + `code_challenge_method=S256`)**. Nếu thiếu, SSO trả về lỗi `400 Bad Request`.
  - Cơ chế **Application Access Control**: Tài khoản người dùng phải được cấp quyền truy cập ứng dụng (theo phòng ban hoặc cá nhân) trong Admin Panel. Nếu chưa được cấp quyền, SSO sẽ chặn với lỗi `403 Forbidden` (`Auth/AccessDenied`).
  - Hỗ trợ ứng dụng vệ tinh dùng InertiaJS: Hệ thống tự phát hiện header `X-Inertia` và gửi `X-Inertia-Location` để ép trình duyệt redirect toàn trang (full-page reload), ngăn chặn hiện tượng bị nhúng iframe trắng (`about:srcdoc`).

#### Yêu cầu Tham số Đầu vào (Query Parameters)

| Tham số | Kiểu dữ liệu | Bắt buộc | Mô tả & Quy tắc ràng buộc |
| :--- | :--- | :--- | :--- |
| `client_id` | String (UUID) | **Có** | ID của ứng dụng vệ tinh được cấp trong Developer Portal. |
| `redirect_uri` | String (URL) | **Có** | URL nhận callback sau khi xác thực. Phải khớp **chính xác 100%** với Redirect URI đã đăng ký. |
| `response_type`| String | **Có** | Giá trị cố định: `code`. |
| `scope` | String | Không | Danh sách các quyền cách nhau bởi dấu cách. Mặc định: `openid profile email`. Tùy chọn thêm: `roles offline_access`. |
| `state` | String | **Khuyến nghị** | Chuỗi ngẫu nhiên chống tấn công CSRF (tối thiểu 32 ký tự ngẫu nhiên). |
| `code_challenge` | String (Base64URL) | **Bắt buộc nếu là Public Client** | Chuỗi mã hóa Base64URL của hash SHA-256 từ `code_verifier`. |
| `code_challenge_method` | String | **Bắt buộc nếu có code_challenge** | Giá trị cố định: `S256` (SSO không chấp nhận `plain`). |

#### Ví dụ URL Chuyển hướng từ Client:

```http
GET https://sso.yourdomain.com/oauth/authorize?
  client_id=9de0487b-8919-4cb5-b44c-354da58df4c2
  &redirect_uri=https%3A%2F%2Fcrm.yourdomain.com%2Fauth%2Fcallback
  &response_type=code
  &scope=openid%20profile%20email%20roles%20offline_access
  &state=c30467a834bfae019b8893d9ef9213bc
  &code_challenge=E9Melhoa2OwvFrGMTJguCH5rtx64fZqiJMi0n39UmT0
  &code_challenge_method=S256
```

#### Dữ liệu Trả về (Redirect Callback về `redirect_uri`)

Sau khi đăng nhập và phê duyệt thành công, trình duyệt người dùng được redirect về `redirect_uri`:

##### 1. Trường hợp Thành công (HTTP 302 Redirect):
```http
HTTP/1.1 302 Found
Location: https://crm.yourdomain.com/auth/callback?code=def50200...&state=c30467a834bfae019b8893d9ef9213bc
```

- `code`: Mã ủy quyền một lần (Authorization Code), có hiệu lực ngắn (thường 10 phút, dùng 1 lần duy nhất).
- `state`: Chuỗi bảo vệ CSRF ban đầu do client gửi lên. Client **bắt buộc so khớp** giá trị này với giá trị đã lưu trong `sessionStorage` trước khi tiến hành bước tiếp theo.

##### 2. Trường hợp Thất bại / Từ chối (HTTP 302 Redirect hoặc HTTP 403 HTML):
- Người dùng từ chối cấp quyền:
  ```http
  Location: https://crm.yourdomain.com/auth/callback?error=access_denied&error_description=The+resource+owner+or+authorization+server+denied+the+request.&state=...
  ```
- Người dùng chưa được gán quyền truy cập ứng dụng (Deny-by-default): Trả về màn hình giao diện `403 Forbidden` trực tiếp trên trang SSO thông báo: *“Bạn không có quyền truy cập ứng dụng này. Vui lòng liên hệ Quản trị viên.”*

---

### 2.2 Trao đổi & Cấp phát Token: `POST /oauth/token`

- **Mục đích:** Đổi mã `code` lấy Access Token, hoặc làm mới token qua `refresh_token`, hoặc xin token giữa các microservice bằng `client_credentials`.
- **Content-Type:** `application/x-www-form-urlencoded` hoặc `application/json`.
- **Headers bắt buộc:**
  - `Accept: application/json`
  - Nếu là `CONFIDENTIAL` client: Có thể gửi `client_id` và `client_secret` trong Body hoặc gửi qua HTTP Basic Auth Header: `Authorization: Basic base64(client_id:client_secret)`.

---

#### A. Luồng Authorization Code Grant (SPA, Mobile, Backend Web)

##### Input Parameters:

| Tham số | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `grant_type` | String | **Có** | Cố định: `authorization_code`. |
| `client_id` | String (UUID) | **Có** | Client ID của ứng dụng. |
| `client_secret`| String | **Có** *(Chỉ với Confidential)*| Client Secret (ứng dụng `PUBLIC` không gửi trường này). |
| `redirect_uri` | String (URL) | **Có** | Khớp chính xác với URI đã truyền ở bước authorize. |
| `code` | String | **Có** | Authorization code nhận được từ callback. |
| `code_verifier`| String | **Bắt buộc nếu là Public Client** | Chuỗi sinh ngẫu nhiên ban đầu (43 - 128 ký tự) dùng để tạo ra `code_challenge`. |

##### Ví dụ Request Body (`POST /oauth/token`):
```http
POST /oauth/token HTTP/1.1
Host: sso.yourdomain.com
Content-Type: application/x-www-form-urlencoded
Accept: application/json

grant_type=authorization_code
&client_id=9de0487b-8919-4cb5-b44c-354da58df4c2
&redirect_uri=https%3A%2F%2Fcrm.yourdomain.com%2Fauth%2Fcallback
&code=def502008ac7...
&code_verifier=dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk
```

##### Dữ liệu Phản hồi Thành công (HTTP 200 OK):

```json
{
  "token_type": "Bearer",
  "expires_in": 1800,
  "access_token": "eyJhbGciOiJSUzI1NiIsImtpZCI6IjFhMmIzYzRkNWU2ZiIsInR5cCI6IkpXVCJ9.eyJhdWQiOiI5ZGUwNDg3Yi04OTE5LTRjYjUtYjQ0Yy0zNTRkYTU4ZGY0YzIiLCJqdGkiOiIxYjJjM2Q0ZSIsImlhdCI6MTcxMjM0MjA3OCwibmJmIjoxNzEyMzQyMDc4LCJleHAiOjE3MTIzNDM4NzgsInN1YiI6IjEyMyIsInNjb3BlcyI6WyJvcGVuaWQiLCJwcm9maWxlIiwiZW1haWwiLCJyb2xlcyIsIm9mZmxpbmVfYWNjZXNzIl0sInJvbGVzIjpbImFkbWluIl0sImFtciI6WyJwd2QiLCJvdHAiXSwibmFtZSI6Ik5ndXnhu4VuIFbEg24gQSIsImVtYWlsIjoidmFuYUBleGFtcGxlLmNvbSIsImVtYWlsX3ZlcmlmaWVkIjp0cnVlfQ...",
  "refresh_token": "def502001e3b..."
}
```

- `token_type`: Luôn là `Bearer`.
- `expires_in`: Thời gian sống của `access_token` tính bằng giây (`1800` giây = 30 phút).
- `access_token`: Chuỗi JSON Web Token (JWT) được ký bằng thuật toán RS256 của SSO IdP. Chứa đầy đủ thông tin danh tính, vai trò và quyền.
- `refresh_token`: Mã token dài hạn (14 ngày), được cấp khi client xin scope `offline_access`.

---

#### B. Luồng Refresh Token Grant

##### Input Parameters:

| Tham số | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `grant_type` | String | **Có** | Cố định: `refresh_token`. |
| `refresh_token`| String | **Có** | Giá trị `refresh_token` nhận được từ lần cấp phát trước. |
| `client_id` | String (UUID) | **Có** | Client ID của ứng dụng. |
| `client_secret`| String | Tùy chọn | Bắt buộc nếu là ứng dụng Confidential. |
| `scope` | String | Không | Tùy chọn thu hẹp phạm vi quyền (không được mở rộng hơn scope ban đầu). |

##### Dữ liệu Phản hồi Thành công (HTTP 200 OK):
Trả về cặp Access Token mới và **Refresh Token mới** (hệ thống áp dụng cơ chế **Refresh Token Rotation**, token cũ bị vô hiệu hóa ngay lập tức):

```json
{
  "token_type": "Bearer",
  "expires_in": 1800,
  "access_token": "eyJhbGciOiJSUzI1NiIs...",
  "refresh_token": "def502009ff8..."
}
```

---

#### C. Luồng Client Credentials Grant (M2M / Service-to-Service)

Dành riêng cho các tác vụ đồng bộ nền, cronjob hoặc gọi trực tiếp giữa các microservices backend không gắn với người dùng cụ thể.

##### Input Parameters:
- `grant_type`: `client_credentials` (Bắt buộc)
- `client_id`: UUID của client backend (Bắt buộc)
- `client_secret`: Mã bí mật của client backend (Bắt buộc)
- `scope`: Các scope dịch vụ cần truy xuất (Ví dụ: `roles`)

##### Dữ liệu Phản hồi (HTTP 200 OK):
```json
{
  "token_type": "Bearer",
  "expires_in": 1800,
  "access_token": "eyJhbGciOiJSUzI1NiIs..."
}
```
*(Lưu ý: Luồng Client Credentials không trả về `refresh_token`. Khi token hết hạn, service chỉ cần gọi lại endpoint này để lấy token mới).*

---

### 2.3 Lấy Thông Tin Người Dùng & Hồ Sơ HRM: `GET|POST /oauth/userinfo`

- **Mục đích:** Lấy toàn bộ thông tin chi tiết của người dùng: hồ sơ cá nhân, cơ cấu tổ chức phòng ban (HRM), chức danh, kiêm nhiệm và danh sách vai trò phân quyền.
- **Phương thức:** Hỗ trợ cả `GET` và `POST`.
- **Cơ chế xác thực:** Bắt buộc gửi Bearer Token qua Header:
  ```http
  Authorization: Bearer <access_token>
  Accept: application/json
  ```
- **Kiểm tra an toàn tự động phía SSO:**
  - Nếu tài khoản bị vô hiệu hóa (`status != 'active'`), SSO trả về mã lỗi `403 Forbidden` (`account_inactive`).
  - Nếu người dùng đã đổi mật khẩu **sau thời điểm** token được phát hành, token sẽ tự động bị revoke và trả về lỗi `401 Unauthorized`.

#### Dữ liệu Trả về (Response Body Claims Schema)

Dữ liệu trả về phụ thuộc trực tiếp vào các **Scopes** được cấp cho token:

| Scope yêu cầu | Thuộc tính trả về | Kiểu dữ liệu | Ý nghĩa nghiệp vụ |
| :--- | :--- | :--- | :--- |
| *(Mặc định)* | `sub` | String | Mã ID duy nhất định danh người dùng trong toàn hệ thống SSO. |
| `email` | `email` | String | Địa chỉ email chính thức. |
| `email` | `email_verified` | Boolean | Trạng thái email đã xác thực (`true`/`false`). |
| `profile` | `name` | String | Họ và tên đầy đủ. |
| `profile` | `picture` | String (URL) \| null | Đường dẫn ảnh đại diện (Avatar). |
| `profile` | `updated_at` | Integer \| null | Unix timestamp lần cập nhật tài khoản gần nhất. |
| `profile` | `phone_number` | String \| null | Số điện thoại cá nhân (HRM Profile). |
| `profile` | `gender` | String \| null | Giới tính (`male`, `female`, `other`). |
| `profile` | `date_of_birth`| String \| null | Ngày sinh định dạng `YYYY-MM-DD`. |
| `profile` | `address` | String \| null | Địa chỉ cư trú. |
| `profile` | `department` | Object \| null | **Phòng ban chính thức** mà người dùng đang công tác. |
| `profile` | `position` | Object \| null | **Chức danh chính thức** hiện tại của người dùng. |
| `profile` | `positions` | Array[Object] | **Danh sách các vị trí công tác & kiêm nhiệm** (Đa phòng ban). |
| `roles` | `roles` | Array[String] | Danh sách vai trò phân quyền (Spatie RBAC: `["admin", "staff", ...]`). |

#### Chi Tiết Cấu Trúc Khối Tổ Chức (Department & Position Structure)

##### Đối tượng `department`:
```typescript
interface DepartmentInfo {
  id: number;          // ID phòng ban
  name: string;        // Tên hiển thị: "Ban Giám đốc", "Tổ chuyên môn Toán"
  code: string;        // Mã định danh phòng ban: "BGD", "TCM_TOAN"
  type: string;        // "management_board" (Ban Giám đốc) | "specialized_team" (Tổ chuyên môn)
}
```

##### Đối tượng `position`:
```typescript
interface PositionInfo {
  id: number;          // ID chức danh
  name: string;        // "Giám đốc", "Phó Giám đốc", "Tổ trưởng", "Tổ phó", "Tổ viên"
  code: string;        // "DIRECTOR" | "DEPUTY_DIR" | "TEAM_LEAD" | "DEPUTY_TL" | "MEMBER"
  level: number;       // Cấp bậc thẩm quyền: 1 (thấp nhất) đến 5 (cao nhất)
}
```

##### Danh sách `positions` (Bao gồm kiêm nhiệm):
```typescript
interface PositionAssignment {
  department: { id: number; name: string; code: string } | null;
  position_type: { id: number; name: string; code: string } | null;
  is_primary: boolean;       // true: Vị trí công tác chính; false: Vị trí kiêm nhiệm
  started_at: string | null; // Ngày bắt đầu công tác: "2024-01-15"
}
```

#### Ví Dụ JSON Response Đầy Đủ (HTTP 200 OK):

```json
{
  "sub": "42",
  "name": "Trần Thị Mai",
  "picture": "https://sso.yourdomain.com/storage/avatars/user-42.png",
  "updated_at": 1712340000,
  "phone_number": "0912345678",
  "gender": "female",
  "date_of_birth": "1990-08-20",
  "address": "123 Đường Nguyễn Huệ, Quận 1, TP. Hồ Chí Minh",
  "department": {
    "id": 3,
    "name": "Tổ chuyên môn Toán",
    "code": "TCM_TOAN",
    "type": "specialized_team"
  },
  "position": {
    "id": 4,
    "name": "Tổ trưởng",
    "code": "TEAM_LEAD",
    "level": 3
  },
  "positions": [
    {
      "department": {
        "id": 3,
        "name": "Tổ chuyên môn Toán",
        "code": "TCM_TOAN"
      },
      "position_type": {
        "id": 4,
        "name": "Tổ trưởng",
        "code": "TEAM_LEAD"
      },
      "is_primary": true,
      "started_at": "2022-09-01"
    },
    {
      "department": {
        "id": 5,
        "name": "Hội đồng Khảo thí",
        "code": "HDKT"
      },
      "position_type": {
        "id": 2,
        "name": "Tổ phó",
        "code": "DEPUTY_TL"
      },
      "is_primary": false,
      "started_at": "2023-03-10"
    }
  ],
  "email": "mai.tran@yourdomain.com",
  "email_verified": true,
  "roles": [
    "teacher",
    "team_lead"
  ]
}
```

---

### 2.4 Lấy Public Key Xác thực Offline: `GET /oauth/jwks`

- **Mục đích:** Cung cấp khóa công khai (Public Key) theo định dạng RFC 7517 (JSON Web Key Set). Các ứng dụng vệ tinh tải khóa này về và cache lại để tự thẩm định chữ ký của Access Token cục bộ (offline) mà không cần gửi request về SSO IdP.
- **Xác thực:** Công khai (Không cần token hay mật khẩu).
- **Caching:** Máy chủ phản hồi header `Cache-Control: public, max-age=3600`. Khuyến nghị app vệ tinh lưu cache trong bộ nhớ tối thiểu 1 giờ.

#### Input:
Không có tham số.

#### Dữ liệu Phản hồi (HTTP 200 OK):

```json
{
  "keys": [
    {
      "kty": "RSA",
      "use": "sig",
      "alg": "RS256",
      "kid": "8f3a9e2c4b1d6f50",
      "n": "u1b7Y...base64url-encoded-modulus...",
      "e": "AQAB"
    }
  ]
}
```

- `kty`: Loại khóa (Key Type), luôn là `RSA`.
- `use`: Mục đích sử dụng, `sig` = Signature (ký số).
- `alg`: Thuật toán ký mã hóa: `RS256` (RSA Signature with SHA-256).
- `kid`: Key ID - Định danh duy nhất của khóa hiện tại (khớp với header `kid` trong JWT).
- `n`: Modulus của RSA key (Base64URL).
- `e`: Exponent của RSA key (thông thường là `AQAB` tương ứng với số mũ 65537).

---

### 2.5 Kiểm Tra Trạng Thái Token Thời Gian Thực: `POST /oauth/introspect`

- **Mục đích:** Theo chuẩn RFC 7662. Dành cho các backend service hoặc API Gateway cần thẩm định trạng thái tức thì của token (ví dụ: phát hiện ngay khi user bị Admin click "Force Logout", hoặc khi user vừa đổi mật khẩu, hoặc token vừa bị đưa vào danh sách đen).
- **Xác thực Client:** Confidential Client bắt buộc xác thực qua HTTP Basic Auth hoặc gửi `client_id` + `client_secret` trong body.
- **Content-Type:** `application/x-www-form-urlencoded` hoặc `application/json`.

#### Input Parameters:

| Tham số | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `token` | String | **Có** | Chuỗi Access Token hoặc Refresh Token cần kiểm tra. |
| `token_type_hint`| String | Không | Gợi ý loại token: `access_token` hoặc `refresh_token`. |
| `client_id` | String | **Có** | Client ID của ứng dụng gọi kiểm tra. |
| `client_secret`| String | **Có** | Client Secret tương ứng. |

#### Dữ liệu Phản hồi (HTTP 200 OK):

##### 1. Khi Token Hợp Lệ & Đang Hoạt Động (`active: true`):
```json
{
  "active": true,
  "scope": "openid profile email roles offline_access",
  "client_id": "9de0487b-8919-4cb5-b44c-354da58df4c2",
  "sub": "42",
  "username": "mai.tran@yourdomain.com",
  "email": "mai.tran@yourdomain.com",
  "token_type": "Bearer",
  "exp": 1712343878,
  "iat": 1712342078,
  "nbf": 1712342078,
  "roles": [
    "teacher",
    "team_lead"
  ]
}
```

##### 2. Khi Token Hết Hạn / Bị Thu Hồi / Không Tồn Tại (`active: false`):
```json
{
  "active": false
}
```

---

### 2.6 Thu Hồi Token Chủ Động: `POST /oauth/revoke`

- **Mục đích:** Theo chuẩn RFC 7009. Được gọi khi người dùng thực hiện Đăng xuất tại ứng dụng vệ tinh, nhằm vô hiệu hóa ngay Access Token hoặc Refresh Token trên cơ sở dữ liệu của SSO.
- **Headers:** `Content-Type: application/x-www-form-urlencoded`

#### Input Parameters:

| Tham số | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `token` | String | **Có** | Chuỗi Access Token hoặc Refresh Token muốn hủy bỏ. |
| `token_type_hint`| String | Không | `access_token` hoặc `refresh_token`. |
| `client_id` | String | **Có** | Client ID. |
| `client_secret`| String | **Có** | Bắt buộc đối với Confidential Client. |

#### Dữ liệu Phản hồi:
- **HTTP 200 OK:** Body là `{}` (Theo RFC 7009, server luôn trả về HTTP 200 dù token đã bị hủy trước đó hoặc không tồn tại để tránh rò rỉ thông tin token).

---

### 2.7 Đăng Xuất Tập Trung OIDC: `GET|POST /oauth/logout`

- **Mục đích:** OpenID Connect RP-Initiated Logout. Đăng xuất người dùng ra khỏi toàn bộ hệ thống SSO, vô hiệu hóa phiên web SSO và kích hoạt gửi thông báo Backchannel Logout tới tất cả các app vệ tinh khác mà user đang mở phiên.
- **Chống lỗi Open Redirect:** SSO sẽ xác thực domain của `post_logout_redirect_uri` với danh sách Redirect URIs đã đăng ký của Client. Nếu không hợp lệ, SSO sẽ giữ người dùng lại trang login SSO mặc định thay vì chuyển hướng tự do.

#### Input Parameters (Query hoặc Form Body):

| Tham số | Kiểu | Bắt buộc | Mô tả |
| :--- | :--- | :--- | :--- |
| `id_token_hint` | String (JWT) | Không | Chuỗi token danh tính trước đó của user (giúp SSO nhận diện tài khoản cần logout nếu phiên web đã mất). |
| `post_logout_redirect_uri`| String (URL) | Không | URL quay về của app vệ tinh sau khi SSO đã hoàn tất đăng xuất. |
| `state` | String | Không | Chuỗi state của app vệ tinh để duy trì ngữ cảnh sau khi quay lại. |

#### Dữ liệu Phản hồi:
- **HTTP 302 Redirect:** Chuyển hướng người dùng về `post_logout_redirect_uri?state=...` nếu URL hợp lệ, hoặc quay về `https://sso.yourdomain.com/login` kèm thông báo thành công.

---

### 2.8 Webhook Đăng Xuất Phía App Vệ Tinh: `POST {backchannel_logout_uri}`

- **Mục đích:** OIDC Back-Channel Logout 1.0. Khi một tài khoản bị đăng xuất (tại trang SSO, tại một app vệ tinh khác, hoặc bị Quản trị viên SSO ép "Force Logout"), máy chủ SSO sẽ gửi một HTTP POST trực tiếp đến URL webhook do app vệ tinh khai báo (`backchannel_logout_uri`).
- **Nguồn phát:** Máy chủ SSO IdP (Server-to-Server ngầm, không qua trình duyệt người dùng).
- **Headers:**
  ```http
  Content-Type: application/x-www-form-urlencoded
  ```

#### Payload SSO Gửi Đến Ứng Dụng Vệ Tinh:

```http
POST /api/sso/backchannel-logout HTTP/1.1
Host: crm.yourdomain.com
Content-Type: application/x-www-form-urlencoded

logout_token=eyJhbGciOiJSUzI1NiIsImtpZCI6IjFhMmIzYyIsInR5cCI6IkpXVCJ9...
```

#### Cấu Trúc Giải Mã của `logout_token` (JWT):
```json
{
  "iss": "https://sso.yourdomain.com",
  "sub": "42",
  "aud": "9de0487b-8919-4cb5-b44c-354da58df4c2",
  "iat": 1712345000,
  "jti": "d3b07384-d113-4e44-a690-33b5c7ef9f26",
  "events": {
    "http://schemas.openid.net/event/backchannel-logout": {}
  }
}
```

- `sub`: ID người dùng cần bị hủy phiên ngay lập tức trên app vệ tinh (`"42"`).
- `aud`: Khớp với Client ID của chính app vệ tinh.
- `events`: Chứa claim đánh dấu sự kiện đăng xuất bắt buộc theo chuẩn OIDC.

#### Dữ liệu App Vệ Tinh Phải Phản Hồi Về Cho SSO:
- **HTTP 200 OK:** Body trống hoặc JSON `{ "status": "ok" }`.
- Nếu trả về khác 2xx (hoặc timeout quá 5 giây), SSO sẽ tự động retry tối đa 3 lần trước khi đánh dấu thất bại trong Nhật ký kiểm toán (Audit Logs).

---

### 2.9 Khám Phá Cấu Hình Tự Động: `GET /.well-known/openid-configuration`

- **Mục đích:** RFC 8414. Cung cấp danh mục toàn bộ capabilities, endpoints và thuật toán mã hóa của SSO IdP để các thư viện OpenID Connect client (như `next-auth`, `passport-openidconnect`, `oidc-client-ts`, Spring Security) tự động nạp cấu hình.

#### Dữ liệu Phản hồi Mẫu (HTTP 200 OK):
```json
{
  "issuer": "https://sso.yourdomain.com",
  "authorization_endpoint": "https://sso.yourdomain.com/oauth/authorize",
  "token_endpoint": "https://sso.yourdomain.com/oauth/token",
  "revocation_endpoint": "https://sso.yourdomain.com/oauth/revoke",
  "introspection_endpoint": "https://sso.yourdomain.com/oauth/introspect",
  "userinfo_endpoint": "https://sso.yourdomain.com/oauth/userinfo",
  "jwks_uri": "https://sso.yourdomain.com/oauth/jwks",
  "scopes_supported": [
    "openid", "profile", "email", "offline_access", "roles"
  ],
  "response_types_supported": ["code"],
  "grant_types_supported": [
    "authorization_code", "refresh_token", "client_credentials"
  ],
  "subject_types_supported": ["public"],
  "id_token_signing_alg_values_supported": ["RS256"],
  "token_endpoint_auth_methods_supported": [
    "client_secret_basic", "client_secret_post", "none"
  ],
  "code_challenge_methods_supported": ["S256"],
  "claims_supported": [
    "sub", "iss", "aud", "exp", "iat", "auth_time",
    "name", "email", "email_verified", "picture", "roles", "amr"
  ],
  "backchannel_logout_supported": true,
  "backchannel_logout_session_supported": true
}
```

---

### 2.10 Giám Sát Sức Khỏe SSO IdP: `GET /health` hoặc `GET /api/health`

- **Mục đích:** Endpoint kiểm tra sức khỏe hệ sinh thái SSO dành cho Kubernetes Liveness/Readiness probes, Docker Compose Healthcheck hoặc các công cụ giám sát uptime (UptimeRobot, Datadog).

#### Dữ liệu Phản hồi:
- **HTTP 200 OK (Khỏe mạnh):**
```json
{
  "status": "healthy",
  "timestamp": "2026-10-05T04:55:00+00:00",
  "environment": "production",
  "services": {
    "database": "ok",
    "cache": "ok",
    "oauth_keys": "ok"
  }
}
```
- **HTTP 503 Service Unavailable (Khi Database hoặc Cache gặp sự cố):**
```json
{
  "status": "unhealthy",
  "timestamp": "2026-10-05T04:55:00+00:00",
  "environment": "production",
  "services": {
    "database": "error: Connection refused",
    "cache": "ok",
    "oauth_keys": "ok"
  }
}
```

---

## 3. Hướng Dẫn Ứng Dụng Vệ Tinh Khai Thác & Sử Dụng Dữ Liệu Phản Hồi

Sau khi hoàn thành các bước gọi API và nhận được dữ liệu phản hồi, dưới đây là quy chuẩn kỹ thuật để ứng dụng vệ tinh bóc tách và tích hợp vào logic nghiệp vụ của mình.

---

### 3.1 Bóc Tách & Sử Dụng Access Token JWT

Mỗi khi nhận được `access_token` từ endpoint `/oauth/token`, đây là một chuỗi JWT tiêu chuẩn gồm 3 phần ngăn cách bởi dấu chấm: `Header.Payload.Signature`.

Phần **Payload** đã được máy chủ SSO gắn sẵn các claims hữu ích:

```json
{
  "aud": "9de0487b-8919-4cb5-b44c-354da58df4c2",
  "jti": "a4d704...",
  "iat": 1712342078,
  "nbf": 1712342078,
  "exp": 1712343878,
  "sub": "42",
  "scopes": ["openid", "profile", "email", "roles"],
  "roles": ["teacher", "team_lead"],
  "amr": ["pwd", "otp"],
  "name": "Trần Thị Mai",
  "email": "mai.tran@yourdomain.com",
  "email_verified": true
}
```

#### Quy tắc nghiệp vụ cho App Vệ Tinh:
1. **Thời hạn `exp`:** Tuyệt đối không hardcode thời gian hết hạn của token ở client. Hãy đọc thuộc tính `expires_in` (giây) hoặc decode trường `exp` trong JWT để đặt lịch tự động refresh trước khi token hết hạn từ 1 đến 2 phút.
2. **Quyền truy cập `roles`:** Khối `roles` trong JWT cho phép client render giao diện có điều kiện (ví dụ: ẩn/hiện nút Admin, hiển thị menu dành cho Quản lý) mà không cần gọi thêm bất kỳ API nào khác.
3. **Cấp độ bảo mật xác thực `amr` (Authentication Method References):**
   - `["pwd"]`: Người dùng đăng nhập bằng mật khẩu thông thường.
   - `["pwd", "otp"]`: Người dùng đã hoàn thành bước xác thực đa yếu tố (TOTP 2FA). Ứng dụng vệ tinh có thể yêu cầu claim này bắt buộc phải có `otp` trước khi cho phép thực hiện các nghiệp vụ nhạy cảm (duyệt chi ngân sách, xuất báo cáo nhân sự).

---

### 3.2 Cơ Chế Xác Thực Chữ Ký Cục Bộ Bằng JWKS (Offline RS256 Verification)

Thay vì gửi mọi request của người dùng về máy chủ SSO để kiểm tra (làm tăng độ trễ mạng và gây nghẽn SSO), **các backend của ứng dụng vệ tinh PHẢI thực hiện xác thực chữ ký token cục bộ**:

```
[Request with Bearer Token] 
         │
         ▼
[Satellite Backend API] 
         │
         ├── 1. Lấy public key từ Local Memory Cache (nếu chưa có -> gọi /oauth/jwks 1 lần duy nhất)
         ├── 2. Verify chữ ký RS256 của Access Token bằng Public Key
         ├── 3. Kiểm tra exp > now() và aud == SATELLITE_CLIENT_ID
         │
         └── 4. HỢP LỆ: Khởi tạo Auth Context người dùng và xử lý nghiệp vụ ngay lập tức
```

#### Ví Dụ Triển Khai Backend Node.js / Express:

```typescript
import jwt from 'jsonwebtoken';
import jwksClient from 'jwks-rsa';

// Khởi tạo JWKS Client với tính năng Auto-Caching
const client = jwksClient({
  jwksUri: 'https://sso.yourdomain.com/oauth/jwks',
  cache: true,
  cacheMaxAge: 3600000, // 1 giờ
  rateLimit: true,
  jwksRequestsPerMinute: 10,
});

function getKey(header: jwt.JwtHeader, callback: jwt.SigningKeyCallback) {
  client.getSigningKey(header.kid, (err, key) => {
    if (err) return callback(err);
    const signingKey = key?.getPublicKey();
    callback(null, signingKey);
  });
}

// Middleware xác thực token cho mọi API trên ứng dụng vệ tinh
export function authenticateToken(req, res, next) {
  const authHeader = req.headers['authorization'];
  const token = authHeader && authHeader.split(' ')[1];

  if (!token) return res.status(401).json({ error: 'unauthorized' });

  jwt.verify(
    token,
    getKey,
    {
      audience: process.env.SSO_CLIENT_ID,
      issuer: process.env.SSO_BASE_URL,
      algorithms: ['RS256'],
    },
    (err, decoded: any) => {
      if (err) {
        return res.status(401).json({ error: 'invalid_token', message: err.message });
      }
      // Gắn thông tin người dùng vào request context
      req.user = {
        id: decoded.sub,
        email: decoded.email,
        name: decoded.name,
        roles: decoded.roles || [],
      };
      next();
    }
  );
}
```

#### Ví Dụ Triển Khai Backend PHP / Laravel:

```php
namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VerifySsoJwtMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        try {
            // Lấy JWKS Keys có cache 60 phút
            $jwks = Cache::remember('sso_jwks_keys', 3600, function () {
                $response = Http::get(config('services.sso.base_url') . '/oauth/jwks');
                return $response->json();
            });

            // Giải mã và kiểm tra chữ ký RS256
            $decoded = JWT::decode($token, JWK::parseKeySet($jwks));

            // Kiểm tra Audience (aud) phải đúng là Client ID của app mình
            if ($decoded->aud !== config('services.sso.client_id')) {
                return response()->json(['error' => 'Invalid token audience'], 403);
            }

            // Gắn thông tin người dùng vào Auth Context
            $request->attributes->set('sso_user', $decoded);

            return $next($request);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid or expired token', 'detail' => $e->getMessage()], 401);
        }
    }
}
```

---

### 3.3 Ánh Xạ Dữ Liệu UserInfo Vào Cơ Sở Dữ Liệu Ứng Dụng Vệ Tinh

Khi người dùng đăng nhập qua SSO, ứng dụng vệ tinh nên sử dụng chiến lược **Just-In-Time (JIT) Provisioning** (Tự động đồng bộ tài khoản người dùng vào bảng `users` nội bộ của app vệ tinh):

#### Sơ Đồ Ánh Xạ Dữ Liệu (Field Mapping):

| Trường SSO (`/oauth/userinfo`) | Kiểu dữ liệu | Cột trong DB App Vệ Tinh | Hướng dẫn xử lý |
| :--- | :--- | :--- | :--- |
| `sub` | String (Số/UUID) | `sso_id` (Unique Index) | **Khóa định danh chính.** Tuyệt đối không dùng `email` làm khóa chính vì người dùng có thể đổi email. |
| `email` | String | `email` | Dùng để hiển thị hoặc liên lạc gửi thư. |
| `name` | String | `name` / `full_name` | Cập nhật theo họ tên chuẩn trên SSO. |
| `picture` | String (URL) | `avatar_url` | Lưu link avatar. |
| `department.code` | String | `department_code` | Mã phòng ban chính (ví dụ: `"TCM_TOAN"`). |
| `department.type` | String | `department_type` | Loại ban bệ (`"management_board"` hoặc `"specialized_team"`). |
| `position.code` | String | `position_code` | Mã chức danh chính (`"TEAM_LEAD"`, `"DIRECTOR"`...). |
| `position.level` | Integer | `position_level` | Cấp bậc quản lý (1 đến 5) - dùng cho các điều kiện duyệt văn bản. |
| `positions` | JSON Array | `positions_metadata` | Lưu toàn bộ danh sách kiêm nhiệm để phục vụ phân quyền đa chức năng. |
| `roles` | Array of String | RBAC Roles | Ánh xạ vào các quyền hạn riêng của ứng dụng vệ tinh. |

#### Thuật Toán Xử Lý Logic Phân Quyền Theo Phòng Ban & Chức Vụ:

```typescript
// Ví dụ hàm kiểm tra thẩm quyền trên app vệ tinh
function checkUserAuthority(userClaims) {
  const isDirector = userClaims.position?.code === 'DIRECTOR';
  const isTeamLead = userClaims.position?.code === 'TEAM_LEAD';
  const isManagementBoard = userClaims.department?.type === 'management_board';

  // Kiểm tra quyền kiêm nhiệm trong positions
  const isExamCouncilMember = userClaims.positions?.some(
    pos => pos.department?.code === 'HDKT'
  );

  return {
    canApproveBudget: isDirector || isManagementBoard,
    canManageTeamMembers: isTeamLead || isDirector,
    canScoreExams: isExamCouncilMember
  };
}
```

---

### 3.4 Tự Động Làm Mới Token (Silent Auto-Refresh Interceptor)

Để người dùng không bị văng ra khỏi ứng dụng sau mỗi 30 phút, ứng dụng vệ tinh cần thiết lập cơ chế **HTTP Interceptor** tự động chặn mã lỗi `401 Unauthorized`, gọi endpoint `/oauth/token` với `grant_type=refresh_token`, lưu lại token mới và tự động chạy lại request ban đầu.

#### Code mẫu JavaScript Axios Interceptor:

```typescript
import axios from 'axios';

const apiClient = axios.create({
  baseURL: 'https://api.crm.yourdomain.com',
});

let isRefreshing = false;
let failedQueue: Array<{ resolve: (token: string) => void; reject: (err: any) => void }> = [];

const processQueue = (error: any, token: string | null = null) => {
  failedQueue.forEach(prom => {
    if (error) {
      prom.reject(error);
    } else {
      prom.resolve(token!);
    }
  });
  failedQueue = [];
};

apiClient.interceptors.response.use(
  response => response,
  async error => {
    const originalRequest = error.config;

    // Nếu gặp lỗi 401 và chưa từng thử retry
    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        // Đang có một tiến trình làm mới token, xếp request này vào hàng đợi
        return new Promise((resolve, reject) => {
          failedQueue.push({ resolve, reject });
        })
          .then(token => {
            originalRequest.headers['Authorization'] = `Bearer ${token}`;
            return apiClient(originalRequest);
          })
          .catch(err => Promise.reject(err));
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const storedRefreshToken = localStorage.getItem('sso_refresh_token');
        if (!storedRefreshToken) {
          throw new Error('No refresh token available');
        }

        // Gọi sang SSO Token Endpoint để lấy cặp token mới
        const res = await axios.post('https://sso.yourdomain.com/oauth/token', {
          grant_type: 'refresh_token',
          refresh_token: storedRefreshToken,
          client_id: 'YOUR_CLIENT_ID',
        }, {
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
        });

        const { access_token, refresh_token: newRefreshToken } = res.data;

        // Lưu trữ lại cặp token mới (Lưu ý: Refresh Token Rotation đã cấp refresh token mới!)
        localStorage.setItem('sso_access_token', access_token);
        localStorage.setItem('sso_refresh_token', newRefreshToken);

        apiClient.defaults.headers.common['Authorization'] = `Bearer ${access_token}`;
        originalRequest.headers['Authorization'] = `Bearer ${access_token}`;

        processQueue(null, access_token);
        return apiClient(originalRequest);
      } catch (refreshError) {
        processQueue(refreshError, null);
        // Refresh token cũng đã hết hạn hoặc bị thu hồi -> Chuyển về màn hình đăng nhập SSO
        localStorage.clear();
        window.location.href = 'https://sso.yourdomain.com/oauth/authorize?...';
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);
```

> ⚠️ **CẢNH BÁO QUAN TRỌNG VỀ REFRESH TOKEN ROTATION:**  
> Hệ thống SSO áp dụng cơ chế tự động xoay vòng Refresh Token (Token Rotation) và Family Revocation.  
> Mỗi khi bạn dùng một `refresh_token`, máy chủ SSO sẽ **ngay lập tức vô hiệu hóa nó** và cấp lại một `refresh_token` mới.  
> Nếu vì lỗi logic mà app vệ tinh gửi lại một `refresh_token` đã từng bị dùng hoặc đã bị thu hồi, hệ thống SSO sẽ coi đây là hành vi bị tấn công/rò rỉ khóa và sẽ **hủy toàn bộ chuỗi token (token family)** của tài khoản đó.

---

### 3.5 Xử Lý Đồng Bộ Đăng Xuất (Xử Lý Webhook Backchannel Logout)

Khi người dùng nhấn đăng xuất tại trang chủ SSO hoặc khi quản trị viên ấn **"Force Logout"** trên SSO Admin Panel, SSO sẽ gửi tín hiệu HTTP POST đến `backchannel_logout_uri` của ứng dụng vệ tinh.

#### Cách Ứng Dụng Vệ Tinh Xử Lý Webhook:

1. **Tiếp nhận POST request:** Đọc trường `logout_token` từ Body.
2. **Xác thực chữ ký JWT của `logout_token`:**
   - Dùng public key từ `https://sso.yourdomain.com/oauth/jwks` (thuật toán RS256).
   - Kiểm tra `iss` đúng bằng SSO Base URL.
   - Kiểm tra `aud` đúng bằng Client ID của ứng dụng mình.
   - Kiểm tra tồn tại claim `events` chứa `"http://schemas.openid.net/event/backchannel-logout"`.
   - Kiểm tra `logout_token` **không** được chứa claim `nonce`.
3. **Thu hồi phiên làm việc:**
   - Trích xuất `sub` (User ID).
   - Xóa bỏ toàn bộ Session trên Redis / Database của User tương ứng.
   - Thu hồi các API token nội bộ của User này nếu có.
4. **Phản hồi:** Trả về ngay lập tức HTTP status `200 OK`.

#### Code mẫu Controller tiếp nhận Webhook (Laravel):

```php
namespace App\Http\Controllers;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class SsoBackchannelLogoutController extends Controller
{
    public function handle(Request $request)
    {
        $logoutToken = $request->input('logout_token');
        if (!$logoutToken) {
            return response()->json(['error' => 'Missing logout_token'], 400);
        }

        try {
            $jwks = Cache::remember('sso_jwks_keys', 3600, function () {
                return Http::get(config('services.sso.base_url') . '/oauth/jwks')->json();
            });

            $decoded = JWT::decode($logoutToken, JWK::parseKeySet($jwks));

            // Kiểm tra các claim bắt buộc theo OIDC Backchannel Logout Spec
            if ($decoded->aud !== config('services.sso.client_id')) {
                return response()->json(['error' => 'Invalid audience'], 400);
            }

            if (!isset($decoded->events->{'http://schemas.openid.net/event/backchannel-logout'})) {
                return response()->json(['error' => 'Missing backchannel-logout event'], 400);
            }

            $userId = $decoded->sub;

            // Xóa toàn bộ session của user này trong DB session
            DB::table('sessions')->where('user_id', $userId)->delete();

            // Nếu app dùng cache hoặc token blacklist:
            Cache::put("user_logged_out:{$userId}", true, now()->addHours(24));

            return response()->json(['status' => 'Logout processed successfully'], 200);
        } catch (\Throwable $e) {
            return response()->json(['error' => 'Invalid logout token', 'message' => $e->getMessage()], 400);
        }
    }
}
```

---

## 4. Bảng Tra Cứu Mã Lỗi & Cách Xử Lý Phía Client (Error Matrix)

Khi gặp sự cố trong quá trình giao tiếp API với SSO, dưới đây là danh sách mã lỗi chuẩn mà SSO trả về và cách xử lý tương ứng:

| HTTP Status | Mã lỗi (`error`) | Nguyên nhân xảy ra | Cách xử lý phía App Vệ Tinh |
| :---: | :--- | :--- | :--- |
| **`400`** | `invalid_request` | Thiếu tham số bắt buộc (như `code_challenge`, `redirect_uri`, `token`). | Kiểm tra lại payload gửi lên, đảm bảo đủ các trường theo tài liệu. |
| **`400`** | `invalid_grant` | Mã `code` đã hết hạn, đã bị dùng 1 lần, hoặc sai `code_verifier`. | Xóa `state` và khởi động lại luồng đăng nhập mới từ `GET /oauth/authorize`. |
| **`401`** | `invalid_client` | Sai `client_id` hoặc sai `client_secret`. | Kiểm tra lại cấu hình biến môi trường (`SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`). |
| **`401`** | `invalid_token` | Token giả mạo, hết hạn, hoặc user vừa đổi mật khẩu sau khi token cấp. | Gọi Refresh Token hoặc yêu cầu người dùng đăng nhập lại. |
| **`403`** | `account_inactive` | Tài khoản người dùng đã bị khóa (`status = suspended`) trên SSO. | Hiển thị thông báo: *"Tài khoản của bạn đã bị tạm khóa. Vui lòng liên hệ Quản trị viên."* |
| **`403`** | `access_denied` | Người dùng bị từ chối cấp quyền truy cập ứng dụng (Deny-by-default). | Báo Quản trị viên vào Admin Panel (`/admin/application-access`) gán quyền cho user/phòng ban. |
| **`429`** | `Too Many Requests`| Vượt quá giới hạn Rate Limiting (Token: 30 req/phút, UserInfo: 120 req/phút). | Tạm dừng gửi request, kiểm tra header `Retry-After` để biết số giây cần chờ. |
| **`500`** | `server_error` | Sự cố nội bộ SSO (thiếu file khóa `oauth-private.key`, lỗi Database). | Kiểm tra endpoint `/health` để xem thành phần bị lỗi và báo đội ngũ DevOps SSO. |
