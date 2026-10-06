<script setup lang="ts">
import { ref } from 'vue';
import { useForm, Head, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';

const showRecoveryMode = ref(false);

const form = useForm({
    code: '',
    is_recovery_code: false,
});

const submit = () => {
    form.post(route('mfa.challenge.store'), {
        onFinish: () => {
            form.code = '';
        },
    });
};

const toggleRecoveryMode = () => {
    showRecoveryMode.value = !showRecoveryMode.value;
    form.is_recovery_code = showRecoveryMode.value;
    form.code = '';
};
</script>

<template>
    <Head title="Xác thực hai yếu tố - SSO Hub" />

    <div class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 overflow-hidden">
        <!-- Ambient background -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none animate-pulse" />
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-cyan-600/15 rounded-full blur-3xl pointer-events-none" />

        <div class="relative w-full max-w-md z-10">
            <!-- Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-cyan-400 p-[1px] shadow-lg shadow-indigo-500/25 mb-4">
                    <div class="w-full h-full bg-slate-950 rounded-2xl flex items-center justify-center">
                        <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                </div>
                <h1 class="text-2xl font-bold tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">
                    {{ showRecoveryMode ? 'Mã khôi phục' : 'Xác thực hai yếu tố' }}
                </h1>
                <p class="text-sm text-slate-400 mt-1">
                    {{ showRecoveryMode
                        ? 'Nhập mã khôi phục định dạng XXXX-XXXX-XXXX-XXXX'
                        : 'Nhập mã 6 chữ số từ ứng dụng Authenticator của bạn' }}
                </p>
            </div>

            <!-- Card -->
            <div class="backdrop-blur-xl bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl shadow-indigo-950/40">

                <!-- TOTP visual indicator -->
                <div v-if="!showRecoveryMode" class="flex justify-center mb-6">
                    <div class="flex gap-2">
                        <div v-for="i in 6" :key="i"
                             class="w-9 h-12 rounded-lg bg-slate-800/60 border border-slate-700/60 flex items-center justify-center">
                            <span class="text-xl font-mono text-indigo-300 font-bold">
                                {{ form.code[i - 1] ?? '' }}
                            </span>
                        </div>
                    </div>
                </div>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Error -->
                    <div v-if="form.errors.code" class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm flex items-center gap-2.5">
                        <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <span>{{ form.errors.code }}</span>
                    </div>

                    <!-- Code input -->
                    <div>
                        <label :for="showRecoveryMode ? 'recovery_code' : 'totp_code'"
                               class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            {{ showRecoveryMode ? 'Mã khôi phục' : 'Mã xác thực (OTP)' }}
                        </label>
                        <input
                            :id="showRecoveryMode ? 'recovery_code' : 'totp_code'"
                            v-model="form.code"
                            :type="showRecoveryMode ? 'text' : 'text'"
                            :maxlength="showRecoveryMode ? 19 : 6"
                            :inputmode="showRecoveryMode ? 'text' : 'numeric'"
                            :placeholder="showRecoveryMode ? 'XXXX-XXXX-XXXX-XXXX' : '000000'"
                            autocomplete="one-time-code"
                            required
                            class="w-full px-4 py-3 bg-slate-950/70 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 text-center text-xl font-mono tracking-[0.3em] focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition duration-150"
                            :class="{ 'border-rose-500 focus:border-rose-500 focus:ring-rose-500': form.errors.code }"
                        />
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing || form.code.length < (showRecoveryMode ? 19 : 6)"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-cyan-500 text-white font-medium text-sm shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/50 hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition duration-150 disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z" />
                        </svg>
                        <span>{{ form.processing ? 'Đang xác thực...' : 'Xác nhận' }}</span>
                    </button>
                </form>

                <!-- Toggle recovery mode -->
                <div class="mt-6 pt-5 border-t border-slate-800 text-center">
                    <button
                        type="button"
                        @click="toggleRecoveryMode"
                        class="text-sm text-indigo-400 hover:text-indigo-300 transition cursor-pointer"
                    >
                        {{ showRecoveryMode
                            ? '← Quay lại nhập mã Authenticator'
                            : 'Không có thiết bị? Dùng mã khôi phục' }}
                    </button>
                </div>

                <div class="mt-3 text-center">
                    <Link :href="route('login')" class="text-xs text-slate-500 hover:text-slate-400 transition">
                        ← Đăng nhập bằng tài khoản khác
                    </Link>
                </div>
            </div>

            <div class="text-center mt-6 text-xs text-slate-500">
                <span>Bảo vệ bởi TOTP RFC 6238 • SSO Hub</span>
            </div>
        </div>
    </div>
</template>
