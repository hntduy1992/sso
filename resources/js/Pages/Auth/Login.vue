<script setup lang="ts">
import { ref } from 'vue';
import { useForm, usePage, Head } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import type { PageProps } from '@/types';

const props = defineProps<{
    redirect?: string | null;
}>();

const page = usePage<PageProps>();

const showPassword = ref(false);

const form = useForm({
    login: '',
    email: '',
    password: '',
    remember: false,
    redirect: props.redirect || '',
});

const submit = () => {
    form.email = form.login;
    form.post(route('login.store'), {
        onFinish: () => {
            form.password = '';
        },
    });
};
</script>

<template>
    <Head title="Đăng nhập Tập trung - SSO Hub" />

    <div class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 sm:p-6 overflow-hidden selection:bg-indigo-500 selection:text-white">
        <!-- Ambient Glowing Background Orbs -->
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none animate-pulse" />
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-cyan-600/20 rounded-full blur-3xl pointer-events-none" />
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-purple-600/10 rounded-full blur-[140px] pointer-events-none" />

        <div class="relative w-full max-w-lg z-10">
            <!-- Branding Header -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-cyan-400 p-[1px] shadow-lg shadow-indigo-500/25 mb-4">
                    <div class="w-full h-full bg-slate-950 rounded-2xl flex items-center justify-center">
                        <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                </div>
                <h1 class="font-bold tracking-tight bg-gradient-to-r from-white via-slate-100 to-slate-400 bg-clip-text text-transparent">
                    <span class="block text-xl sm:text-2xl whitespace-nowrap">Trung tâm cung ứng dịch vụ công</span>
                    <span class="block text-lg sm:text-xl font-semibold mt-1">Phường Sa Đéc</span>
                </h1>

                <!-- Client App Redirect Notice -->
                <div v-if="redirect" class="mt-4 inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-indigo-500/10 border border-indigo-500/30 text-indigo-300 text-xs font-medium">
                    <span class="w-2 h-2 rounded-full bg-indigo-400 animate-ping" />
                    <span>Chuyển hướng đến Client App sau khi xác thực</span>
                </div>
            </div>

            <!-- Main Auth Card -->
            <div class="backdrop-blur-xl bg-slate-900/70 border border-slate-800/80 rounded-2xl p-6 sm:p-8 shadow-2xl shadow-indigo-950/40">
                <!-- Flash Notification -->
                <div v-if="page.props.flash?.success" class="mb-5 p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-sm flex items-center gap-2.5">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ page.props.flash.success }}</span>
                </div>

                <div v-if="page.props.flash?.error" class="mb-5 p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-400 text-sm flex items-center gap-2.5">
                    <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ page.props.flash.error }}</span>
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <!-- Email or Phone field -->
                    <div>
                        <label for="login" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-2">
                            Địa chỉ Email hoặc Số điện thoại
                        </label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                                </svg>
                            </div>
                            <input
                                id="login"
                                v-model="form.login"
                                type="text"
                                autocomplete="username"
                                required
                                placeholder="name@company.com hoặc 0901234567"
                                class="w-full pl-10 pr-4 py-2.5 bg-slate-950/70 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition duration-150"
                                :class="{ 'border-rose-500 focus:border-rose-500 focus:ring-rose-500': form.errors.login || form.errors.email }"
                            />
                        </div>
                        <p v-if="form.errors.login || form.errors.email" class="mt-1.5 text-xs text-rose-400">
                            {{ form.errors.login || form.errors.email }}
                        </p>
                    </div>

                    <!-- Password field -->
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                                Mật khẩu
                            </label>
                        
                        </div>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input
                                id="password"
                                v-model="form.password"
                                :type="showPassword ? 'text' : 'password'"
                                autocomplete="current-password"
                                required
                                placeholder="••••••••"
                                class="w-full pl-10 pr-10 py-2.5 bg-slate-950/70 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 text-sm focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500 transition duration-150"
                                :class="{ 'border-rose-500 focus:border-rose-500 focus:ring-rose-500': form.errors.password }"
                            />
                            <button
                                type="button"
                                @click="showPassword = !showPassword"
                                class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-slate-500 hover:text-slate-300 transition"
                            >
                                <svg v-if="!showPassword" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <svg v-else class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l18 18" />
                                </svg>
                            </button>
                        </div>
                        <p v-if="form.errors.password" class="mt-1.5 text-xs text-rose-400">
                            {{ form.errors.password }}
                        </p>
                    </div>

                    <!-- Remember Me checkbox -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer select-none">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-slate-700 bg-slate-950 text-indigo-600 focus:ring-indigo-500 focus:ring-offset-slate-900"
                            />
                            <span class="text-xs text-slate-300">Ghi nhớ phiên đăng nhập (30 ngày)</span>
                        </label>
                    </div>

                    <!-- Submit Button -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full py-3 px-4 rounded-xl bg-gradient-to-r from-indigo-600 via-indigo-500 to-cyan-500 text-white font-medium text-sm shadow-lg shadow-indigo-600/30 hover:shadow-indigo-600/50 hover:opacity-95 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:ring-offset-slate-900 transition duration-150 disabled:opacity-60 disabled:cursor-not-allowed flex items-center justify-center gap-2 cursor-pointer"
                    >
                        <svg v-if="form.processing" class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                        <span>{{ form.processing ? 'Đang xác thực...' : 'Đăng nhập vào SSO Hub' }}</span>
                    </button>
                </form>
            </div>

            <!-- Footer notes -->
            <div class="text-center mt-6 text-xs text-slate-500">
                <span>Tổ Thông tin và Truyền thông</span>
            </div>
        </div>
    </div>
</template>
