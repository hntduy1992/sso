<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xác nhận ủy quyền SSO - {{ $client->name }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800&display=swap" rel="stylesheet" />
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #030712;
            color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            position: relative;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
        }

        .ambient-glow-1 {
            position: fixed;
            top: -100px;
            left: 50%;
            transform: translateX(-50%);
            width: 600px;
            height: 400px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(147, 51, 234, 0.15) 50%, transparent 70%);
            filter: blur(60px);
            pointer-events: none;
            z-index: 0;
        }

        .ambient-glow-2 {
            position: fixed;
            bottom: -50px;
            right: 15%;
            width: 450px;
            height: 350px;
            background: radial-gradient(circle, rgba(6, 182, 212, 0.15) 0%, rgba(59, 130, 246, 0.1) 50%, transparent 70%);
            filter: blur(50px);
            pointer-events: none;
            z-index: 0;
        }

        .auth-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 480px;
            background-color: rgba(17, 24, 39, 0.85);
            border: 1px solid rgba(55, 65, 81, 0.7);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 40px rgba(99, 102, 241, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            text-align: center;
        }

        .brand-connection {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .brand-node {
            width: 56px;
            height: 56px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            position: relative;
        }

        .sso-node {
            background: linear-gradient(135deg, #4f46e5 0%, #9333ea 100%);
            color: #ffffff;
            box-shadow: 0 10px 15px -3px rgba(79, 70, 229, 0.4);
            border: 2px solid rgba(255, 255, 255, 0.15);
        }

        .client-node {
            background-color: #1f2937;
            color: #10b981;
            border: 2px solid rgba(16, 185, 129, 0.3);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }

        .node-label {
            position: absolute;
            bottom: -20px;
            font-size: 10px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            white-space: nowrap;
        }

        .sso-label { color: #818cf8; }
        .client-label { color: #34d399; max-width: 90px; overflow: hidden; text-overflow: ellipsis; }

        .connection-flow {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            margin-bottom: 0.5rem;
        }

        .flow-line {
            width: 24px;
            height: 2px;
            background: linear-gradient(90deg, #4f46e5, #10b981);
        }

        .flow-icon {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background-color: #1f2937;
            border: 1px solid #374151;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #818cf8;
        }

        h1.title {
            font-size: 1.375rem;
            font-weight: 700;
            color: #ffffff;
            letter-spacing: -0.025em;
            margin-top: 1.25rem;
        }

        p.subtitle {
            font-size: 0.875rem;
            color: #9ca3af;
            margin-top: 0.5rem;
            line-height: 1.4;
        }

        p.subtitle strong {
            color: #a5b4fc;
            font-weight: 600;
        }

        .user-pill {
            margin-top: 1.25rem;
            background-color: rgba(31, 41, 55, 0.6);
            border: 1px solid rgba(55, 65, 81, 0.8);
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            text-align: left;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: #ffffff;
            font-size: 1.125rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .user-name {
            font-size: 0.875rem;
            font-weight: 600;
            color: #ffffff;
        }

        .user-email {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .badge-online {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #34d399;
            font-size: 0.6875rem;
            font-weight: 600;
            flex-shrink: 0;
        }

        .dot-green {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background-color: #10b981;
        }

        .scopes-section {
            margin-top: 1.25rem;
            text-align: left;
        }

        .scopes-header {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #9ca3af;
            margin-bottom: 0.625rem;
        }

        .scope-list {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .scope-item {
            background-color: rgba(31, 41, 55, 0.4);
            border: 1px solid rgba(55, 65, 81, 0.6);
            border-radius: 0.75rem;
            padding: 0.625rem 0.75rem;
            display: flex;
            align-items: flex-start;
            gap: 0.625rem;
        }

        .scope-icon-box {
            width: 24px;
            height: 24px;
            border-radius: 0.5rem;
            background-color: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .scope-title {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #f3f4f6;
            text-transform: capitalize;
        }

        .scope-desc {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.125rem;
            line-height: 1.35;
        }

        .disclaimer {
            font-size: 0.6875rem;
            color: #6b7280;
            line-height: 1.4;
            margin-top: 1rem;
        }

        .actions-group {
            margin-top: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }

        .btn-approve {
            width: 100%;
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%);
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            padding: 0.8125rem 1.25rem;
            border-radius: 0.75rem;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
            transition: all 0.2s ease;
        }

        .btn-approve:hover:not(:disabled) {
            opacity: 0.95;
            transform: translateY(-1px);
            box-shadow: 0 12px 24px -5px rgba(79, 70, 229, 0.5);
        }

        .btn-approve:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-deny {
            width: 100%;
            background-color: rgba(31, 41, 55, 0.5);
            border: 1px solid rgba(55, 65, 81, 0.7);
            color: #9ca3af;
            font-size: 0.75rem;
            font-weight: 500;
            padding: 0.625rem 1rem;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .btn-deny:hover:not(:disabled) {
            background-color: #374151;
            color: #ffffff;
        }

        .btn-deny:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        svg {
            display: inline-block;
            vertical-align: middle;
            flex-shrink: 0;
            max-width: 100%;
            max-height: 100%;
        }
    </style>
</head>
<body>
    <div class="ambient-glow-1"></div>
    <div class="ambient-glow-2"></div>

    <div class="auth-card">
        <!-- Brand Connection Graphic -->
        <div class="brand-connection">
            <div class="brand-node sso-node">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                </svg>
                <span class="node-label sso-label">SSO Hub</span>
            </div>

            <div class="connection-flow">
                <div class="flow-line"></div>
                <div class="flow-icon">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <div class="flow-line"></div>
            </div>

            <div class="brand-node client-node">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                </svg>
                <span class="node-label client-label">{{ $client->name }}</span>
            </div>
        </div>

        <h1 class="title">Ủy quyền truy cập ứng dụng</h1>
        <p class="subtitle">
            Ứng dụng <strong>{{ $client->name }}</strong> muốn kết nối và xác thực qua tài khoản SSO của bạn.
        </p>

        <div class="user-pill">
            <div class="user-info">
                <div class="user-avatar">
                    {{ strtoupper(substr($user->name ?? 'U', 0, 1)) }}
                </div>
                <div>
                    <div class="user-name">{{ $user->name }}</div>
                    <div class="user-email">{{ $user->email }}</div>
                </div>
            </div>
            <div class="badge-online">
                <span class="dot-green"></span>
                <span>Đang đăng nhập</span>
            </div>
        </div>

        <div class="scopes-section">
            <div class="scopes-header">Quyền hạn ứng dụng yêu cầu</div>
            <div class="scope-list">
                @if(isset($scopes) && count($scopes) > 0)
                    @foreach($scopes as $scope)
                        <div class="scope-item">
                            <div class="scope-icon-box">
                                @if($scope->id === 'email')
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                @else
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                    </svg>
                                @endif
                            </div>
                            <div>
                                <div class="scope-title">{{ $scope->id }}</div>
                                <div class="scope-desc">{{ $scope->description ?: 'Cho phép truy cập quyền ' . $scope->id }}</div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="scope-item">
                        <div class="scope-icon-box">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>
                        <div>
                            <div class="scope-title">Định danh & Hồ sơ cá nhân</div>
                            <div class="scope-desc">Xác định danh tính, họ tên và mã định danh người dùng.</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        <p class="disclaimer">
            Dữ liệu được bảo vệ an toàn. Bạn có thể thu hồi quyền này bất kỳ lúc nào tại Cài đặt tài khoản SSO.
        </p>

        <!-- Actions -->
        <div class="actions-group">
            <!-- Form Approve -->
            <form id="approve-form" method="post" action="{{ Route::has('passport.authorizations.approve') ? route('passport.authorizations.approve') : url('/oauth/authorize') }}">
                @csrf
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <button type="submit" id="btn-approve" class="btn-approve">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span id="btn-approve-text">Ủy quyền & Tiếp tục</span>
                </button>
            </form>

            <!-- Form Deny -->
            <form id="deny-form" method="post" action="{{ Route::has('passport.authorizations.deny') ? route('passport.authorizations.deny') : url('/oauth/authorize') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="state" value="{{ $request->state }}">
                <input type="hidden" name="client_id" value="{{ $client->id }}">
                <input type="hidden" name="auth_token" value="{{ $authToken }}">

                <button type="submit" id="btn-deny" class="btn-deny">
                    Từ chối yêu cầu
                </button>
            </form>

            
        </div>
    </div>

        <!-- Script xử lý cấp quyền an toàn (hỗ trợ AJAX cho môi trường iframe/sandbox và trình duyệt thông thường) -->
    <script>
        (function() {
            var approveForm = document.getElementById('approve-form');
            var denyForm = document.getElementById('deny-form');
            var btnApprove = document.getElementById('btn-approve');
            var btnApproveText = document.getElementById('btn-approve-text');
            var btnDeny = document.getElementById('btn-deny');

            function handleOAuthAction(form, isApprove) {
                if (btnApprove) btnApprove.disabled = true;
                if (btnDeny) btnDeny.disabled = true;
                if (isApprove && btnApproveText) {
                    btnApproveText.textContent = 'Đang xác thực & chuyển hướng...';
                }

                var formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin'
                })
                .then(function(res) {
                    return res.json().catch(function() {
                        return { redirect_uri: res.headers.get('Location') || '' };
                    });
                })
                .then(function(data) {
                    var targetUrl = data.redirect_uri;
                    if (targetUrl) {
                        try {
                            if (window.top && window.top !== window.self) {
                                window.top.location.href = targetUrl;
                                return;
                            }
                        } catch (e) {
                            // Cross-origin top navigation restricted
                        }

                        try {
                            window.location.href = targetUrl;
                        } catch (e) {
                            // Fallback link
                        }

                        // Guaranteed fallback link for strict sandboxed frames
                        var finishBox = document.getElementById('oauth-finish-link');
                        if (!finishBox) {
                            finishBox = document.createElement('div');
                            finishBox.id = 'oauth-finish-link';
                            finishBox.style.marginTop = '1rem';
                            finishBox.style.textAlign = 'center';
                            finishBox.innerHTML = '<a href="' + targetUrl + '" target="_top" style="display:inline-block;padding:0.75rem 1.25rem;background:#10b981;color:#fff;border-radius:0.75rem;font-weight:700;text-decoration:none;font-size:0.875rem;box-shadow:0 4px 12px rgba(16,185,129,0.3);">👉 Bấm vào đây để tiếp tục về Ứng dụng</a>';
                            if (form.parentNode) {
                                form.parentNode.appendChild(finishBox);
                            }
                        }
                    } else {
                        // If no JSON redirect returned, fallback to native submission
                        form.submit();
                    }
                })
                .catch(function(err) {
                    console.warn('[SSO] AJAX submit fallback to native form:', err);
                    form.submit();
                });
            }

            if (approveForm) {
                approveForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleOAuthAction(approveForm, true);
                });
            }

            if (denyForm) {
                denyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    handleOAuthAction(denyForm, false);
                });
            }
        })();
    </script>
</body>
</html>
