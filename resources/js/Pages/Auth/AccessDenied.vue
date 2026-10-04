<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{
    applicationName: string;
}>();

const page = usePage<PageProps>();

const switchAccount = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="Không có quyền truy cập - SSO Hub" />

    <div class="relative min-h-screen bg-slate-950 text-slate-100 flex items-center justify-center p-4 sm:p-6 overflow-hidden selection:bg-indigo-500 selection:text-white">
        <div class="absolute -top-40 -left-40 w-96 h-96 bg-rose-600/15 rounded-full blur-3xl pointer-events-none" />
        <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-600/20 rounded-full blur-3xl pointer-events-none" />

        <main class="relative z-10 w-full max-w-md">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/70 backdrop-blur-xl shadow-2xl p-8 text-center">
                <div class="mx-auto mb-5 w-16 h-16 rounded-2xl bg-rose-500/10 border border-rose-500/30 flex items-center justify-center">
                    <svg class="w-8 h-8 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                </div>

                <h1 class="text-xl font-bold text-white">Không có quyền truy cập quản trị ứng dụng</h1>

                <p class="mt-3 text-sm text-slate-300">
                    Tài khoản
                    <span class="font-semibold text-white">{{ page.props.auth?.user?.email }}</span>
                    chưa được cấp quyền sử dụng ứng dụng
                    <span class="font-semibold text-indigo-300">"{{ applicationName }}"</span>.
                </p>

                <p class="mt-3 text-sm text-slate-400">
                    Vui lòng liên hệ quản trị viên hệ thống để được cấp quyền truy cập.
                </p>

                <div class="mt-7 flex flex-col sm:flex-row gap-3">
                    <a
                        id="access-denied-portal"
                        href="/dashboard"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-medium bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 transition"
                    >
                        Về trang tài khoản
                    </a>
                    <button
                        id="access-denied-switch-account"
                        type="button"
                        class="flex-1 px-4 py-2.5 rounded-xl text-sm font-medium bg-indigo-600 hover:bg-indigo-500 text-white transition"
                        @click="switchAccount"
                    >
                        Đăng nhập tài khoản khác
                    </button>
                </div>
            </div>
        </main>
    </div>
</template>
