<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface AuthorizedApp {
    id: string;
    name: string;
    description: string;
    client_type: 'PUBLIC' | 'CONFIDENTIAL' | null;
    scopes: string[];
    authorized_at: string;
}

const props = defineProps<{
    apps: AuthorizedApp[];
}>();

const revokeApp = (app: AuthorizedApp) => {
    if (!confirm(`Bạn có chắc chắn muốn thu hồi toàn bộ quyền truy cập của "${app.name}"? Ứng dụng này sẽ bị ngắt kết nối và đăng xuất ngay lập tức.`)) {
        return;
    }

    router.delete(`/profile/authorized-apps/${app.id}`, {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout title="Ứng Dụng Đã Cấp Quyền">
        <div class="max-w-4xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-3">
                    <div class="p-2 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                    </div>
                    Ứng Dụng & Dịch Vụ Đã Liên Kết
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    Các ứng dụng bên ngoài hoặc hệ thống vệ tinh mà bạn đã cho phép truy cập tài khoản SSO của mình.
                </p>
            </div>

            <!-- Apps List -->
            <div class="space-y-4">
                <div
                    v-for="app in apps"
                    :key="app.id"
                    class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 backdrop-blur-xl hover:border-slate-700 transition space-y-4"
                >
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="flex items-start gap-4">
                            <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-lg text-white shadow shrink-0">
                                {{ app.name.charAt(0).toUpperCase() }}
                            </div>

                            <div>
                                <div class="flex items-center gap-2">
                                    <h3 class="text-base font-bold text-slate-100">{{ app.name }}</h3>
                                    <span
                                        :class="[
                                            'px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider border',
                                            app.client_type === 'CONFIDENTIAL'
                                                ? 'bg-purple-500/10 text-purple-300 border-purple-500/30'
                                                : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30'
                                        ]"
                                    >
                                        {{ app.client_type || 'CLIENT' }}
                                    </span>
                                </div>
                                <p class="text-xs text-slate-400 mt-1">{{ app.description }}</p>
                                <div class="text-[11px] text-slate-500 mt-2">
                                    Cấp quyền lúc: {{ app.authorized_at }} • ID: <code class="text-slate-400 font-mono">{{ app.id }}</code>
                                </div>
                            </div>
                        </div>

                        <div>
                            <button
                                @click="revokeApp(app)"
                                class="px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 border border-rose-500/30 text-rose-300 text-xs font-semibold transition"
                            >
                                Thu hồi quyền
                            </button>
                        </div>
                    </div>

                    <!-- Scopes granted -->
                    <div class="pt-4 border-t border-slate-800/80 flex items-center gap-2 flex-wrap">
                        <span class="text-xs text-slate-400 font-medium mr-1">Quyền đã cấp:</span>
                        <span
                            v-for="scope in app.scopes"
                            :key="scope"
                            class="px-2 py-0.5 rounded-md bg-slate-800 border border-slate-700 text-indigo-300 font-mono text-[11px]"
                        >
                            {{ scope }}
                        </span>
                        <span v-if="app.scopes.length === 0" class="text-xs text-slate-500">
                            Không có quyền cụ thể
                        </span>
                    </div>
                </div>

                <div v-if="apps.length === 0" class="text-center py-12 border border-dashed border-slate-800 rounded-2xl text-slate-500 text-sm">
                    Bạn chưa cấp quyền cho bất kỳ ứng dụng vệ tinh bên thứ ba nào.
                </div>
            </div>
        </div>
    </AppLayout>
</template>
