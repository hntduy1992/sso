<script setup lang="ts">
import { ref, computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SocialProviderCard from '@/Pages/Profile/components/SocialProviderCard.vue';

interface ProfileData {
    id: number;
    name: string;
    email: string;
    avatar_url: string | null;
    role: string;
    status: string;
    password_changed_at: string | null;
    two_factor_enabled: boolean;
    has_password: boolean;
}

interface UserProfileData {
    full_name: string | null;
    date_of_birth: string | null;
    gender: string | null;
    phone_number: string | null;
    contact_email: string | null;
    address: string | null;
    bio: string | null;
    avatar_url: string | null;
}

interface LinkedProvider {
    provider: string;
    connected: boolean;
    linked_at: string | null;
}

const props = defineProps<{
    profile: ProfileData;
    userProfile: UserProfileData;
    linkedProviders: LinkedProvider[];
}>();

// ── Personal Profile Form ────────────────────────────────────────────────────
const personalForm = useForm({
    full_name: props.userProfile.full_name || props.profile.name,
    date_of_birth: props.userProfile.date_of_birth || '',
    gender: props.userProfile.gender || '',
    phone_number: props.userProfile.phone_number || '',
    contact_email: props.userProfile.contact_email || '',
    address: props.userProfile.address || '',
    bio: props.userProfile.bio || '',
});

const submitPersonal = () => {
    personalForm.patch('/profile', { preserveScroll: true });
};

// ── Avatar Upload ────────────────────────────────────────────────────────────
const avatarPreview = ref<string | null>(props.userProfile.avatar_url);
const avatarFile = ref<File | null>(null);
const avatarInput = ref<HTMLInputElement | null>(null);
const avatarUploading = ref(false);

const onAvatarChange = (event: Event) => {
    const file = (event.target as HTMLInputElement).files?.[0];
    if (!file) { return; }
    avatarFile.value = file;
    avatarPreview.value = URL.createObjectURL(file);
};

const submitAvatar = () => {
    if (!avatarFile.value) { return; }
    avatarUploading.value = true;
    const form = useForm({ avatar: avatarFile.value });
    form.post('/profile/avatar', {
        preserveScroll: true,
        onFinish: () => { avatarUploading.value = false; },
    });
};

// ── Social Connections ───────────────────────────────────────────────────────
const showUnlinkModal = ref(false);
const unlinkProvider = ref('');

const connectedCount = computed(
    () => props.linkedProviders.filter((p) => p.connected).length,
);

/**
 * A provider can be unlinked if the user has a password OR there's at least
 * one other connected provider remaining after the unlink.
 */
const canUnlink = (provider: string): boolean => {
    if (props.profile.has_password) { return true; }
    const otherConnected = props.linkedProviders.filter(
        (p) => p.provider !== provider && p.connected,
    ).length;
    return otherConnected > 0;
};

const requestUnlink = (provider: string) => {
    unlinkProvider.value = provider;
    showUnlinkModal.value = true;
};

const confirmUnlink = () => {
    router.delete(`/profile/social-connections/${unlinkProvider.value}`, {
        preserveScroll: true,
        onSuccess: () => { showUnlinkModal.value = false; },
    });
};

// ── Account Info Form (backward compat - avatar URL field) ───────────────────
const profileForm = useForm({
    name: props.profile.name,
    avatar_url: props.profile.avatar_url || '',
});

const submitProfile = () => {
    profileForm.patch('/profile', { preserveScroll: true });
};

// Password Form
const passwordForm = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const submitPassword = () => {
    passwordForm.put('/profile/password', {
        preserveScroll: true,
        onSuccess: () => {
            passwordForm.reset();
        },
    });
};

// MFA Modal & State
const showMfaSetupModal = ref(false);
const mfaLoading = ref(false);
const mfaSecret = ref('');
const mfaQrCodeSvg = ref('');
const mfaCode = ref('');
const mfaConfirmError = ref('');
const recoveryCodes = ref<string[]>([]);
const showRecoveryCodesModal = ref(false);

const showDisableMfaModal = ref(false);
const disablePassword = ref('');
const disableError = ref('');

const startMfaSetup = async () => {
    mfaLoading.value = true;
    mfaConfirmError.value = '';
    try {
        const response = await fetch('/profile/mfa/setup', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
        });
        const data = await response.json();
        mfaSecret.value = data.secret;
        mfaQrCodeSvg.value = data.qr_code_svg;
        showMfaSetupModal.value = true;
    } catch {
        alert('Không thể khởi tạo mã thiết lập 2FA. Vui lòng thử lại.');
    } finally {
        mfaLoading.value = false;
    }
};

const confirmMfa = async () => {
    if (mfaCode.value.length !== 6) {
        mfaConfirmError.value = 'Mã xác thực phải gồm 6 chữ số.';
        return;
    }

    mfaLoading.value = true;
    mfaConfirmError.value = '';

    try {
        const response = await fetch('/profile/mfa/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': getXsrfToken(),
            },
            body: JSON.stringify({ code: mfaCode.value }),
        });

        const data = await response.json();

        if (response.ok && data.success) {
            recoveryCodes.value = data.recovery_codes;
            showMfaSetupModal.value = false;
            showRecoveryCodesModal.value = true;
            router.reload({ only: ['profile'] });
        } else {
            mfaConfirmError.value = data.message || (data.errors?.code ? data.errors.code[0] : 'Mã không hợp lệ');
        }
    } catch {
        mfaConfirmError.value = 'Lỗi kết nối. Vui lòng thử lại.';
    } finally {
        mfaLoading.value = false;
    }
};

const submitDisableMfa = () => {
    router.delete('/profile/mfa', {
        data: { password: disablePassword.value },
        preserveScroll: true,
        onSuccess: () => {
            showDisableMfaModal.value = false;
            disablePassword.value = '';
        },
        onError: (errors) => {
            disableError.value = errors.password || 'Mật khẩu không chính xác';
        },
    });
};

const getXsrfToken = (): string => {
    const match = document.cookie.match(new RegExp('(^|;\\s*)XSRF-TOKEN=([^;]*)'));
    return match ? decodeURIComponent(match[2]) : '';
};

const copySecret = () => {
    navigator.clipboard.writeText(mfaSecret.value);
    alert('Đã sao chép khóa bí mật vào clipboard!');
};
</script>

<template>
    <AppLayout title="Hồ sơ & Bảo mật">
        <div class="max-w-4xl mx-auto space-y-8">
            <!-- Header title -->
            <div>
                <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    Hồ sơ Cá nhân & Bảo mật Danh tính
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    Quản lý thông tin định danh, mật khẩu trung tâm và xác thực hai yếu tố (2FA / TOTP).
                </p>
            </div>

            <!-- ═══ SECTION: Hồ sơ cá nhân ══════════════════════════════════ -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8 backdrop-blur-xl shadow-xl">
                <div class="flex items-center gap-3 pb-6 border-b border-slate-800/80 mb-6">
                    <div class="p-2 rounded-xl bg-violet-500/10 border border-violet-500/20 text-violet-400">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0M9 12h.01M15 12h.01" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-slate-100">Hồ sơ Cá nhân</h2>
                        <p class="text-xs text-slate-400">Thông tin nhân sự — không dùng để đăng nhập.</p>
                    </div>
                </div>

                <!-- Avatar section -->
                <div class="flex flex-col sm:flex-row items-center sm:items-start gap-6 mb-8 p-4 rounded-xl bg-slate-950/40 border border-slate-800">
                    <div class="shrink-0">
                        <div class="w-20 h-20 rounded-2xl border-2 border-slate-700 overflow-hidden bg-slate-800 flex items-center justify-center">
                            <img
                                v-if="avatarPreview"
                                :src="avatarPreview"
                                alt="Avatar"
                                class="w-full h-full object-cover"
                            />
                            <svg v-else class="w-10 h-10 text-slate-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 text-center sm:text-left">
                        <p class="text-sm text-slate-300 font-medium mb-1">Ảnh đại diện</p>
                        <p class="text-xs text-slate-500 mb-3">JPEG, PNG hoặc WebP · Tối đa 2MB</p>
                        <div class="flex flex-wrap gap-2 justify-center sm:justify-start">
                            <button
                                type="button"
                                @click="avatarInput?.click()"
                                class="px-3 py-1.5 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-medium transition"
                            >
                                Chọn ảnh mới
                            </button>
                            <button
                                v-if="avatarFile"
                                type="button"
                                :disabled="avatarUploading"
                                @click="submitAvatar"
                                class="px-3 py-1.5 rounded-lg bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition disabled:opacity-50"
                            >
                                {{ avatarUploading ? 'Đang tải...' : 'Lưu ảnh' }}
                            </button>
                        </div>
                        <input
                            ref="avatarInput"
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            class="hidden"
                            @change="onAvatarChange"
                        />
                    </div>
                </div>

                <!-- Personal fields -->
                <form @submit.prevent="submitPersonal" class="space-y-5">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Họ và tên đầy đủ <span class="text-rose-400">*</span></label>
                            <input
                                id="full_name"
                                v-model="personalForm.full_name"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition"
                            />
                            <p v-if="personalForm.errors.full_name" class="mt-1 text-xs text-rose-400">{{ personalForm.errors.full_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Ngày sinh</label>
                            <input
                                id="date_of_birth"
                                v-model="personalForm.date_of_birth"
                                type="date"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Giới tính</label>
                            <select
                                id="gender"
                                v-model="personalForm.gender"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition"
                            >
                                <option value="">-- Không chọn --</option>
                                <option value="male">Nam</option>
                                <option value="female">Nữ</option>
                                <option value="other">Khác</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Số điện thoại</label>
                            <input
                                id="phone_number"
                                v-model="personalForm.phone_number"
                                type="tel"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Email liên hệ</label>
                            <input
                                id="contact_email"
                                v-model="personalForm.contact_email"
                                type="email"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition"
                            />
                            <p class="mt-1 text-[11px] text-slate-500">Khác với email đăng nhập SSO.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Địa chỉ</label>
                            <input
                                id="address"
                                v-model="personalForm.address"
                                type="text"
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition"
                            />
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-2">Giới thiệu bản thân</label>
                        <textarea
                            id="bio"
                            v-model="personalForm.bio"
                            rows="3"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-violet-500 focus:ring-1 focus:ring-violet-500 outline-none transition resize-none"
                        />
                    </div>
                    <div class="flex justify-end pt-1">
                        <button
                            type="submit"
                            :disabled="personalForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-violet-600 hover:bg-violet-500 text-white font-medium text-sm transition shadow-lg shadow-violet-600/20 disabled:opacity-50"
                        >
                            {{ personalForm.processing ? 'Đang lưu...' : 'Lưu Hồ sơ' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- ═══ SECTION: Kết nối tài khoản ══════════════════════════════ -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8 backdrop-blur-xl shadow-xl">
                <div class="flex items-center justify-between pb-6 border-b border-slate-800/80 mb-6">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-cyan-500/10 border border-cyan-500/20 text-cyan-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-lg font-semibold text-slate-100">Kết nối Tài khoản Ngoài</h2>
                            <p class="text-xs text-slate-400">Liên kết để đăng nhập nhanh bằng Google hoặc GitHub.</p>
                        </div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full text-xs font-medium border bg-cyan-500/10 text-cyan-300 border-cyan-500/30">
                        {{ connectedCount }}/{{ linkedProviders.length }} đã kết nối
                    </span>
                </div>

                <div class="space-y-3">
                    <SocialProviderCard
                        v-for="provider in linkedProviders"
                        :key="provider.provider"
                        :provider="provider"
                        :connect-url="`/profile/social-connections/${provider.provider}/connect`"
                        :can-unlink="canUnlink(provider.provider)"
                        @unlink="requestUnlink"
                    />
                </div>

                <p v-if="!profile.has_password && connectedCount <= 1" class="mt-4 text-xs text-amber-400/80 bg-amber-500/5 border border-amber-500/20 rounded-xl p-3">
                    ⚠️ Tài khoản này chưa đặt mật khẩu. Bạn cần giữ ít nhất một kết nối để có thể đăng nhập. Hãy đặt mật khẩu trong phần bên dưới để tăng tính bảo mật.
                </p>
            </div>

            <!-- ═══ SECTION: Thông tin Tài khoản (Account Info) ════════════ -->

            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8 backdrop-blur-xl shadow-xl">
                <div class="flex items-center justify-between pb-6 border-b border-slate-800/80 mb-6">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-100">Thông tin Tài khoản</h2>
                        <p class="text-xs text-slate-400">Thông tin này được chia sẻ tới các ứng dụng vệ tinh qua OIDC Profile scope.</p>
                    </div>
                    <span
                        :class="[
                            'px-2.5 py-1 rounded-full text-xs font-medium border',
                            profile.status === 'active'
                                ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
                                : 'bg-rose-500/10 text-rose-300 border-rose-500/30'
                        ]"
                    >
                        {{ profile.status === 'active' ? 'Đang hoạt động' : 'Tạm khóa' }}
                    </span>
                </div>

                <form @submit.prevent="submitProfile" class="space-y-6">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Họ và tên</label>
                            <input
                                v-model="profileForm.name"
                                type="text"
                                required
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                            />
                            <p v-if="profileForm.errors.name" class="mt-1 text-xs text-rose-400">{{ profileForm.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Địa chỉ Email (Định danh)</label>
                            <input
                                :value="profile.email"
                                type="email"
                                disabled
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950/50 border border-slate-800/60 text-slate-500 text-sm cursor-not-allowed"
                            />
                            <p class="mt-1 text-[11px] text-slate-500">Email là khóa định danh chính (sub claim) và không thể tự thay đổi.</p>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-2">URL Ảnh đại diện (Avatar)</label>
                        <input
                            v-model="profileForm.avatar_url"
                            type="url"
                            placeholder="https://example.com/avatar.jpg"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                        />
                        <p v-if="profileForm.errors.avatar_url" class="mt-1 text-xs text-rose-400">{{ profileForm.errors.avatar_url }}</p>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="profileForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm transition shadow-lg shadow-indigo-600/20 disabled:opacity-50"
                        >
                            {{ profileForm.processing ? 'Đang lưu...' : 'Lưu Thay đổi' }}
                        </button>
                    </div>
                </form>
            </div>

            <!-- Two-Factor Authentication (MFA) Section -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8 backdrop-blur-xl shadow-xl">
                <div class="flex items-start justify-between pb-6 border-b border-slate-800/80 mb-6">
                    <div>
                        <div class="flex items-center gap-3">
                            <h2 class="text-lg font-semibold text-slate-100">Xác thực Hai Yếu Tố (2FA / TOTP)</h2>
                            <span
                                :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-medium border',
                                    profile.two_factor_enabled
                                        ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
                                        : 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                                ]"
                            >
                                {{ profile.two_factor_enabled ? 'Đã kích hoạt' : 'Chưa bật' }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-1">
                            Bảo vệ tài khoản bằng mã bảo mật 6 số từ Google Authenticator hoặc ứng dụng TOTP tương đương khi đăng nhập.
                        </p>
                    </div>

                    <div v-if="profile.two_factor_enabled">
                        <button
                            @click="showDisableMfaModal = true"
                            class="px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-medium transition"
                        >
                            Tắt 2FA
                        </button>
                    </div>
                    <div v-else>
                        <button
                            @click="startMfaSetup"
                            :disabled="mfaLoading"
                            class="px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition shadow-md shadow-indigo-600/20 disabled:opacity-50 flex items-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                            Thiết lập 2FA ngay
                        </button>
                    </div>
                </div>

                <div v-if="profile.two_factor_enabled" class="flex items-center gap-4 text-xs text-slate-400 bg-slate-950/60 p-4 rounded-xl border border-slate-800">
                    <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 shrink-0">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <div class="font-medium text-slate-200">Bảo vệ cấp độ cao đang có hiệu lực</div>
                        <div>Mỗi lần đăng nhập, token ID sẽ mang claim <code class="text-indigo-300">amr: ["pwd", "otp"]</code> xác nhận xác thực nâng cao.</div>
                    </div>
                </div>
            </div>

            <!-- Password Change Section -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 sm:p-8 backdrop-blur-xl shadow-xl">
                <div class="pb-6 border-b border-slate-800/80 mb-6">
                    <h2 class="text-lg font-semibold text-slate-100">Đổi Mật khẩu</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Khi đổi mật khẩu, <strong class="text-amber-400 font-medium">toàn bộ phiên đăng nhập, access tokens và refresh tokens cũ trên các ứng dụng vệ tinh sẽ tự động bị thu hồi ngay lập tức</strong>.
                    </p>
                    <p v-if="profile.password_changed_at" class="text-[11px] text-slate-500 mt-2">
                        Lần đổi mật khẩu gần nhất: {{ profile.password_changed_at }}
                    </p>
                </div>

                <form @submit.prevent="submitPassword" class="space-y-6">
                    <div v-if="profile.has_password">
                        <label class="block text-xs font-medium text-slate-300 mb-2">Mật khẩu hiện tại</label>
                        <input
                            v-model="passwordForm.current_password"
                            type="password"
                            required
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                        />
                        <p v-if="passwordForm.errors.current_password" class="mt-1 text-xs text-rose-400">{{ passwordForm.errors.current_password }}</p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Mật khẩu mới</label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                required
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                            />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-xs text-rose-400">{{ passwordForm.errors.password }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-medium text-slate-300 mb-2">Xác nhận Mật khẩu mới</label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                required
                                class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="passwordForm.processing"
                            class="px-5 py-2.5 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-medium text-sm transition shadow-lg shadow-amber-600/20 disabled:opacity-50"
                        >
                            {{ passwordForm.processing ? 'Đang cập nhật...' : 'Cập nhật Mật khẩu & Thu hồi Token Cũ' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Thiết lập MFA (QR Code) -->
        <div v-if="showMfaSetupModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                        </svg>
                        Quét Mã QR với Authenticator
                    </h3>
                    <button @click="showMfaSetupModal = false" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <!-- QR Code Display -->
                <div class="flex flex-col items-center justify-center p-4 bg-white rounded-xl shadow-inner">
                    <div v-html="mfaQrCodeSvg" class="w-48 h-48 flex items-center justify-center [&>svg]:w-full [&>svg]:h-full"></div>
                </div>

                <!-- Manual Secret Key -->
                <div class="bg-slate-950 p-3 rounded-xl border border-slate-800 text-xs">
                    <div class="text-slate-400 mb-1 flex items-center justify-between">
                        <span>Hoặc nhập mã khóa bí mật thủ công:</span>
                        <button @click="copySecret" type="button" class="text-indigo-400 hover:text-indigo-300 font-medium">Sao chép</button>
                    </div>
                    <div class="font-mono text-slate-200 tracking-wider font-semibold select-all break-all">{{ mfaSecret }}</div>
                </div>

                <!-- 6-digit Code input -->
                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-2">Nhập mã xác thực 6 số từ ứng dụng</label>
                    <input
                        v-model="mfaCode"
                        type="text"
                        maxlength="6"
                        placeholder="123456"
                        class="w-full px-4 py-3 rounded-xl bg-slate-950 border border-slate-800 text-center font-mono text-2xl tracking-[0.5em] text-indigo-400 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 outline-none transition"
                    />
                    <p v-if="mfaConfirmError" class="mt-2 text-xs text-rose-400 text-center">{{ mfaConfirmError }}</p>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="showMfaSetupModal = false"
                        type="button"
                        class="flex-1 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                    >
                        Hủy
                    </button>
                    <button
                        @click="confirmMfa"
                        :disabled="mfaLoading || mfaCode.length !== 6"
                        type="button"
                        class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-medium transition shadow-lg shadow-indigo-600/25 disabled:opacity-50"
                    >
                        {{ mfaLoading ? 'Đang kiểm tra...' : 'Xác nhận Kích hoạt' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- Modal: Mã dự phòng (Recovery Codes) -->
        <div v-if="showRecoveryCodesModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-sm">
            <div class="w-full max-w-lg bg-slate-900 border border-emerald-500/30 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center gap-3 text-emerald-400">
                    <div class="p-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-100">Kích hoạt 2FA Thành Công!</h3>
                        <p class="text-xs text-slate-400">Lưu lại các mã khôi phục dự phòng bên dưới.</p>
                    </div>
                </div>

                <div class="p-4 bg-amber-500/10 border border-amber-500/20 rounded-xl text-xs text-amber-300">
                    <strong>Cảnh báo quan trọng:</strong> Mỗi mã chỉ sử dụng được duy nhất một lần khi bạn mất thiết bị xác thực. Hãy lưu ở nơi an toàn.
                </div>

                <div class="grid grid-cols-2 gap-3 p-4 bg-slate-950 rounded-xl border border-slate-800 font-mono text-sm text-center text-slate-200">
                    <div v-for="code in recoveryCodes" :key="code" class="p-2 bg-slate-900/60 rounded border border-slate-800/80">
                        {{ code }}
                    </div>
                </div>

                <button
                    @click="showRecoveryCodesModal = false"
                    type="button"
                    class="w-full py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-sm font-semibold transition shadow-lg shadow-emerald-600/25"
                >
                    Tôi đã lưu các mã này an toàn
                </button>
            </div>
        </div>

        <!-- Modal: Tắt 2FA -->
        <div v-if="showDisableMfaModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-100 text-rose-400 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Xác nhận Tắt 2FA
                    </h3>
                    <button @click="showDisableMfaModal = false" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <p class="text-xs text-slate-400">
                    Tắt 2FA sẽ làm giảm đáng kể mức độ an toàn của tài khoản SSO của bạn. Vui lòng nhập mật khẩu tài khoản để xác nhận.
                </p>

                <div>
                    <label class="block text-xs font-medium text-slate-300 mb-2">Mật khẩu của bạn</label>
                    <input
                        v-model="disablePassword"
                        type="password"
                        required
                        class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-rose-500 focus:ring-1 focus:ring-rose-500 outline-none transition"
                    />
                    <p v-if="disableError" class="mt-1 text-xs text-rose-400">{{ disableError }}</p>
                </div>

                <div class="flex gap-3">
                    <button
                        @click="showDisableMfaModal = false"
                        type="button"
                        class="flex-1 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                    >
                        Hủy
                    </button>
                    <button
                        @click="submitDisableMfa"
                        type="button"
                        class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold transition shadow-lg shadow-rose-600/25"
                    >
                        Xác nhận Tắt
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>

    <!-- Modal: Xác nhận Hủy liên kết Provider -->
    <div v-if="showUnlinkModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
        <div class="w-full max-w-md bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-5">
            <div class="flex items-center gap-3 text-rose-400">
                <div class="p-2 rounded-xl bg-rose-500/10 border border-rose-500/20">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-100">Hủy liên kết {{ unlinkProvider }}</h3>
            </div>
            <p class="text-xs text-slate-400">
                Bạn có chắc muốn hủy liên kết tài khoản <strong class="text-slate-200">{{ unlinkProvider }}</strong>?
                Sau khi hủy, bạn sẽ không thể đăng nhập bằng provider này nữa.
            </p>
            <div class="flex gap-3">
                <button
                    @click="showUnlinkModal = false"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                >
                    Hủy bỏ
                </button>
                <button
                    @click="confirmUnlink"
                    type="button"
                    class="flex-1 py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white text-sm font-semibold transition shadow-lg shadow-rose-600/25"
                >
                    Xác nhận Hủy liên kết
                </button>
            </div>
        </div>
    </div>
</template>

