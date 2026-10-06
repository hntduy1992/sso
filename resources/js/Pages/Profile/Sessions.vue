<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';

interface SessionItem {
    id: string;
    client_name: string;
    ip_address: string;
    user_agent: string | null;
    last_activity: string;
    is_current: boolean;
}

const props = defineProps<{
    sessions: SessionItem[];
}>();

const terminateSession = (session: SessionItem) => {
    if (!confirm(`Bạn có chắc muốn chấm dứt phiên tại IP ${session.ip_address}?`)) {
        return;
    }

    router.delete(route('profile.sessions.destroy', session.id), {
        preserveScroll: true,
    });
};

const revokeOthers = () => {
    if (!confirm('Bạn có chắc muốn đăng xuất khỏi tất cả các thiết bị khác? Các ứng dụng và phiên khác sẽ bị thu hồi ngay lập tức.')) {
        return;
    }

    router.post(route('profile.sessions.revoke-others'), {}, {
        preserveScroll: true,
    });
};

const parseBrowser = (ua: string | null): string => {
    if (!ua) return 'Thiết bị không xác định';
    if (ua.includes('Chrome')) return 'Google Chrome';
    if (ua.includes('Firefox')) return 'Mozilla Firefox';
    if (ua.includes('Safari')) return 'Apple Safari';
    if (ua.includes('Edge')) return 'Microsoft Edge';
    return 'Trình duyệt Web';
};

const parsePlatform = (ua: string | null): string => {
    if (!ua) return 'Không rõ';
    if (ua.includes('Windows')) return 'Windows';
    if (ua.includes('Macintosh') || ua.includes('Mac OS')) return 'macOS';
    if (ua.includes('Android')) return 'Android';
    if (ua.includes('iPhone') || ua.includes('iPad')) return 'iOS';
    if (ua.includes('Linux')) return 'Linux';
    return 'Máy khách';
};
</script>

<template>
    <AppLayout title="Quản lý Phiên Đăng Nhập">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        Các Phiên Đang Hoạt Động
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Danh sách các thiết bị và ứng dụng hiện đang duy trì phiên xác thực với tài khoản của bạn.
                    </p>
                </div>

                <div v-if="sessions.length > 1">
                    <button
                        @click="revokeOthers"
                        class="px-4 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-semibold transition flex items-center gap-2"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Đăng xuất khỏi tất cả phiên khác
                    </button>
                </div>
            </div>

            <!-- Sessions List -->
            <div class="space-y-4">
                <div
                    v-for="session in sessions"
                    :key="session.id"
                    :class="[
                        'rounded-2xl border p-5 backdrop-blur-xl transition-all duration-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4',
                        session.is_current
                            ? 'border-indigo-500/40 bg-indigo-950/20 shadow-lg shadow-indigo-500/5'
                            : 'border-slate-800 bg-slate-900/60 hover:border-slate-700'
                    ]"
                >
                    <div class="flex items-start gap-4">
                        <div
                            :class="[
                                'w-10 h-10 rounded-xl flex items-center justify-center shrink-0 border',
                                session.is_current
                                    ? 'bg-indigo-600/20 border-indigo-500/30 text-indigo-400'
                                    : 'bg-slate-800/80 border-slate-700 text-slate-400'
                            ]"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>

                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-slate-100 text-sm">
                                    {{ parseBrowser(session.user_agent) }} trên {{ parsePlatform(session.user_agent) }}
                                </span>
                                <span
                                    v-if="session.is_current"
                                    class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30"
                                >
                                    Phiên hiện tại
                                </span>
                            </div>

                            <div class="text-xs text-slate-400 flex flex-wrap items-center gap-x-3 gap-y-1">
                                <span>Ứng dụng: <strong class="text-slate-300">{{ session.client_name }}</strong></span>
                                <span>•</span>
                                <span>IP: <code class="text-indigo-300">{{ session.ip_address }}</code></span>
                                <span>•</span>
                                <span>Hoạt động: {{ session.last_activity }}</span>
                            </div>
                        </div>
                    </div>

                    <div v-if="!session.is_current">
                        <button
                            @click="terminateSession(session)"
                            class="px-3 py-1.5 rounded-xl border border-slate-700 hover:border-rose-500/40 text-slate-300 hover:text-rose-300 hover:bg-rose-500/10 text-xs font-medium transition"
                        >
                            Thu hồi phiên
                        </button>
                    </div>
                </div>

                <div v-if="sessions.length === 0" class="text-center py-12 border border-dashed border-slate-800 rounded-2xl text-slate-500 text-sm">
                    Không có phiên hoạt động nào được ghi nhận.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
