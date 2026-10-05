# Enterprise Single Sign-On (SSO) Identity Provider

Hệ thống Quản lý Danh tính và Xác thực Tập trung (**Identity Provider - IdP**) xây dựng trên nền tảng **Laravel 12**, **Laravel Passport 12**, hỗ trợ đầy đủ các chuẩn công nghiệp **OpenID Connect (OIDC) Core 1.0**, **OAuth 2.0 (RFC 6749, RFC 7662, RFC 7009)** và **PKCE (Proof Key for Code Exchange)**.

---

## 📚 Tài Liệu Hướng Dẫn Kỹ Thuật

- **[howtouse.md](./howtouse.md)**: Sổ tay đặc tả chi tiết toàn bộ Router, tham số đầu vào (Headers, Query, Body), cấu trúc dữ liệu phản hồi (JSON claims, HRM profile, Department, Position, Roles) và hướng dẫn ứng dụng vệ tinh khai thác dữ liệu.
- **[howtoconnect.md](./howtoconnect.md)**: Hướng dẫn kết nối kỹ thuật cho từng loại Client (SPA React/Vue, Mobile Flutter/React Native, Traditional Backend Web, Microservice M2M) trên cả môi trường Dev & Production.

---

## 🚀 Hướng Dẫn Cài Đặt & Triển Khai (Deployment Guide)

### 1. Yêu Cầu Môi Trường
- **PHP:** >= 8.3 (yêu cầu các extensions: `OpenSSL`, `PDO`, `Mbstring`, `Tokenizer`, `XML`, `Ctype`, `JSON`, `BCMath`)
- **MySQL / MariaDB:** >= 8.0
- **Node.js:** >= 20.x & **NPM**
- **Composer:** >= 2.x

---

### 2. Các Bước Cài Đặt Chi Tiết

#### Bước 1: Cài đặt Dependencies
```bash
# Cài đặt PHP dependencies
composer install --no-dev --optimize-autoloader

# Cài đặt Node dependencies và build frontend
npm install
npm run build
```

#### Bước 2: Thiết lập Biến Môi Trường (.env)
```bash
cp .env.example .env
php artisan key:generate
```
Cấu hình các thông số cơ sở dữ liệu (`DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`) và URL của hệ thống (`APP_URL=https://sso.yourdomain.com`).

#### Bước 3: Chạy Database Migration & Seeding
```bash
php artisan migrate --force
```

#### Bước 4: Khởi Tạo Cặp Khóa Mã Hóa OAuth2 (BẮT BUỘC)

> ⚠️ **LƯU Ý CỰC KỲ QUAN TRỌNG KHI TRIỂN KHAI:**  
> Hệ thống SSO sử dụng mã hóa RSA (RS256) để ký Authorization Code và Access Token JWT. Bạn **bắt buộc phải chạy lệnh sau một lần duy nhất** khi thiết lập máy chủ mới:

```bash
php artisan passport:keys
```

- **Mục đích:** Tạo ra 2 file khóa mã hóa trong thư mục lưu trữ:
  - `storage/oauth-private.key`: Khóa bí mật dùng để ký token.
  - `storage/oauth-public.key`: Khóa công khai công bố qua endpoint `/oauth/jwks` để các ứng dụng vệ tinh tự kiểm tra chữ ký token offline.
- **Lưu ý bảo mật:**
  - Khóa này **chỉ tạo 1 lần duy nhất** cho toàn bộ máy chủ SSO. **Không** cần chạy lại khi thêm ứng dụng vệ tinh mới.
  - Trên môi trường Production container (Docker/Kubernetes), có thể inject nội dung khóa trực tiếp qua 2 biến môi trường `PASSPORT_PRIVATE_KEY` và `PASSPORT_PUBLIC_KEY` mà không cần lưu file trong thư mục `storage/`.
  - Không tùy tiện chạy `passport:keys --force` khi hệ thống đang vận hành vì sẽ làm mất hiệu lực toàn bộ token hiện tại của người dùng.

#### Bước 5: Cấu Hình Cache & Tối Ưu Hóa (Production)
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

#### Bước 6: Khởi Chạy Queue Worker (Xử lý Backchannel Logout)
Hệ thống sử dụng Queue nền để gửi webhook OIDC Back-Channel Logout đến các app vệ tinh khi có sự kiện đăng xuất tập trung hoặc Force Logout:
```bash
php artisan queue:work --queue=default --tries=3 --timeout=60
```
*(Khuyến nghị cấu hình chạy qua **Supervisor** hoặc **systemd** để tự động restart khi gặp sự cố).*

---

### 3. Kiểm Tra Trạng Thái Vận Hành (Health Check)

Sau khi triển khai xong, bạn có thể kiểm tra sức khỏe của dịch vụ SSO qua endpoint:

```bash
curl -i http://localhost:8000/health
```

**Phản hồi kỳ vọng (HTTP 200 OK):**
```json
{
  "status": "healthy",
  "timestamp": "2026-10-05T06:50:00+00:00",
  "environment": "production",
  "services": {
    "database": "ok",
    "cache": "ok",
    "oauth_keys": "ok"
  }
}
```

---

## 👥 Thêm Ứng Dụng Vệ Tinh Mới

Khi có thêm một ứng dụng mới cần kết nối vào SSO, **không cần chạy bất kỳ lệnh nào trên máy chủ**. Quản trị viên chỉ cần thao tác trên giao diện Web:

1. Đăng nhập trang quản trị SSO (`/login`).
2. Vào **Developer Portal** (`/developer/clients`) -> Bấm **"Đăng ký Ứng dụng mới"** -> Khai báo Tên ứng dụng, loại Client (`PUBLIC` hoặc `CONFIDENTIAL`) và các Redirect URIs.
3. Nhận `Client ID` (và `Client Secret` nếu có) để cấu hình vào ứng dụng vệ tinh.
4. Vào **Admin Panel** > **Phân quyền ứng dụng** (`/admin/application-access`) để cấp quyền cho Phòng ban hoặc Người dùng được phép đăng nhập ứng dụng đó (cơ chế Deny-by-default).
