import { ref, computed } from 'vue';

export interface UserInfo {
    sub: string;
    name?: string;
    email?: string;
    email_verified?: boolean;
    picture?: string;
    roles?: string[];
    [key: string]: any;
}

export interface AuthTokens {
    access_token: string;
    token_type: string;
    expires_in: number;
    refresh_token?: string;
    id_token?: string;
}

export interface SsoAuthConfig {
    idpBaseUrl: string;
    clientId: string;
    redirectUri: string;
    scopes?: string[];
}

// In-Memory State (Protected from XSS localStorage attacks)
const accessToken = ref<string | null>(null);
const idToken = ref<string | null>(null);
const user = ref<UserInfo | null>(null);
const isLoading = ref<boolean>(false);
const refreshTimer = ref<number | null>(null);

export function useAuth(config?: SsoAuthConfig) {
    const defaultConfig: SsoAuthConfig = config || {
        idpBaseUrl: window.location.origin,
        clientId: '',
        redirectUri: `${window.location.origin}/auth/callback`,
        scopes: ['openid', 'profile', 'email', 'offline_access', 'roles'],
    };

    const isAuthenticated = computed(() => accessToken.value !== null && user.value !== null);

    /**
     * Generate cryptographically secure PKCE Code Verifier (RFC 7636)
     */
    function generateCodeVerifier(): string {
        const array = new Uint8Array(48);
        window.crypto.getRandomValues(array);
        return Array.from(array, dec => ('0' + dec.toString(16)).slice(-2)).join('');
    }

    /**
     * Compute SHA-256 base64url PKCE Code Challenge from verifier
     */
    async function generateCodeChallenge(verifier: string): Promise<string> {
        const encoder = new TextEncoder();
        const data = encoder.encode(verifier);
        const digest = await window.crypto.subtle.digest('SHA-256', data);
        const bytes = new Uint8Array(digest);
        const str = String.fromCharCode(...bytes);
        return btoa(str)
            .replace(/\+/g, '-')
            .replace(/\//g, '_')
            .replace(/=+$/, '');
    }

    /**
     * Initiate PKCE Authorization Code flow: redirects user to SSO IdP login screen
     */
    async function login(customConfig?: Partial<SsoAuthConfig>) {
        const cfg = { ...defaultConfig, ...customConfig };
        const verifier = generateCodeVerifier();
        const challenge = await generateCodeChallenge(verifier);

        sessionStorage.setItem('sso_pkce_verifier', verifier);
        sessionStorage.setItem('sso_post_login_redirect', window.location.pathname + window.location.search);

        const params = new URLSearchParams({
            response_type: 'code',
            client_id: cfg.clientId,
            redirect_uri: cfg.redirectUri,
            scope: (cfg.scopes || ['openid', 'profile', 'email']).join(' '),
            code_challenge: challenge,
            code_challenge_method: 'S256',
        });

        window.location.href = `${cfg.idpBaseUrl}/oauth/authorize?${params.toString()}`;
    }

    /**
     * Handle redirect callback from IdP: exchanges authorization code for tokens
     */
    async function handleCallback(code: string, customConfig?: Partial<SsoAuthConfig>): Promise<boolean> {
        const cfg = { ...defaultConfig, ...customConfig };
        const verifier = sessionStorage.getItem('sso_pkce_verifier');

        if (!verifier) {
            console.error('[useAuth] Missing PKCE code_verifier in sessionStorage');
            return false;
        }

        isLoading.value = true;

        try {
            const response = await fetch(`${cfg.idpBaseUrl}/oauth/token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({
                    grant_type: 'authorization_code',
                    client_id: cfg.clientId,
                    redirect_uri: cfg.redirectUri,
                    code_verifier: verifier,
                    code: code,
                }),
            });

            if (!response.ok) {
                const errorData = await response.json();
                console.error('[useAuth] Token exchange failed:', errorData);
                return false;
            }

            const tokens: AuthTokens = await response.json();

            // Set state in memory
            accessToken.value = tokens.access_token;
            if (tokens.id_token) idToken.value = tokens.id_token;
            if (tokens.refresh_token) {
                sessionStorage.setItem('sso_refresh_token', tokens.refresh_token);
            }

            sessionStorage.removeItem('sso_pkce_verifier');

            // Fetch user info with newly acquired access token
            await fetchUserInfo(cfg.idpBaseUrl);

            // Schedule silent token refresh (refresh 60 seconds before expiration)
            scheduleSilentRefresh(tokens.expires_in, cfg);

            return true;
        } catch (err) {
            console.error('[useAuth] Error exchanging code for tokens:', err);
            return false;
        } finally {
            isLoading.value = false;
        }
    }

    /**
     * Fetch user profile claims from OIDC /oauth/userinfo endpoint
     */
    async function fetchUserInfo(idpUrl?: string) {
        if (!accessToken.value) return;

        const baseUrl = idpUrl || defaultConfig.idpBaseUrl;

        try {
            const response = await fetch(`${baseUrl}/oauth/userinfo`, {
                headers: {
                    'Authorization': `Bearer ${accessToken.value}`,
                    'Accept': 'application/json',
                },
            });

            if (response.ok) {
                user.value = await response.json();
            } else if (response.status === 401) {
                // Token invalid or revoked, attempt refresh
                await silentRefresh();
            }
        } catch (err) {
            console.error('[useAuth] Failed to fetch userinfo:', err);
        }
    }

    /**
     * Silent token refresh using Refresh Token Rotation
     */
    async function silentRefresh(customConfig?: Partial<SsoAuthConfig>): Promise<boolean> {
        const cfg = { ...defaultConfig, ...customConfig };
        const storedRefreshToken = sessionStorage.getItem('sso_refresh_token');

        if (!storedRefreshToken) {
            logout(cfg);
            return false;
        }

        try {
            const response = await fetch(`${cfg.idpBaseUrl}/oauth/token`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'application/json',
                },
                body: new URLSearchParams({
                    grant_type: 'refresh_token',
                    client_id: cfg.clientId,
                    refresh_token: storedRefreshToken,
                }),
            });

            if (response.ok) {
                const tokens: AuthTokens = await response.json();
                accessToken.value = tokens.access_token;
                if (tokens.refresh_token) {
                    sessionStorage.setItem('sso_refresh_token', tokens.refresh_token);
                }
                scheduleSilentRefresh(tokens.expires_in, cfg);
                return true;
            } else {
                // Refresh token revoked or compromised -> clear session
                sessionStorage.removeItem('sso_refresh_token');
                accessToken.value = null;
                user.value = null;
                return false;
            }
        } catch (err) {
            console.error('[useAuth] Silent refresh error:', err);
            return false;
        }
    }

    /**
     * Schedule silent refresh before access token expires
     */
    function scheduleSilentRefresh(expiresInSeconds: number, cfg: SsoAuthConfig) {
        if (refreshTimer.value) {
            window.clearTimeout(refreshTimer.value);
        }

        const refreshDelayMs = Math.max(10, (expiresInSeconds - 60)) * 1000;

        refreshTimer.value = window.setTimeout(() => {
            silentRefresh(cfg);
        }, refreshDelayMs);
    }

    /**
     * Single Sign-Out (OIDC RP-Initiated Logout)
     */
    function logout(customConfig?: Partial<SsoAuthConfig>) {
        const cfg = { ...defaultConfig, ...customConfig };

        if (refreshTimer.value) {
            window.clearTimeout(refreshTimer.value);
        }

        const hint = idToken.value;
        accessToken.value = null;
        idToken.value = null;
        user.value = null;
        sessionStorage.removeItem('sso_refresh_token');

        const params = new URLSearchParams({
            post_logout_redirect_uri: window.location.origin,
        });

        if (hint) {
            params.set('id_token_hint', hint);
        }

        window.location.href = `${cfg.idpBaseUrl}/oauth/logout?${params.toString()}`;
    }

    /**
     * Get Authorization header for HTTP client requests
     */
    function getAuthHeader(): Record<string, string> {
        return accessToken.value ? { Authorization: `Bearer ${accessToken.value}` } : {};
    }

    /**
     * Configure Axios interceptor for automated Bearer token attachment and 401 refresh
     */
    function setupAxiosInterceptors(axiosInstance: any) {
        axiosInstance.interceptors.request.use((reqConfig: any) => {
            if (accessToken.value) {
                reqConfig.headers = reqConfig.headers || {};
                reqConfig.headers.Authorization = `Bearer ${accessToken.value}`;
            }
            return reqConfig;
        });

        axiosInstance.interceptors.response.use(
            (response: any) => response,
            async (error: any) => {
                const originalRequest = error.config;
                if (error.response?.status === 401 && !originalRequest._retry) {
                    originalRequest._retry = true;
                    const refreshed = await silentRefresh();
                    if (refreshed) {
                        originalRequest.headers.Authorization = `Bearer ${accessToken.value}`;
                        return axiosInstance(originalRequest);
                    }
                }
                return Promise.reject(error);
            }
        );
    }

    return {
        user,
        accessToken,
        idToken,
        isAuthenticated,
        isLoading,
        login,
        logout,
        handleCallback,
        fetchUserInfo,
        silentRefresh,
        getAuthHeader,
        setupAxiosInterceptors,
    };
}
