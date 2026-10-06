<script setup lang="ts">
import { ref } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';

interface AuditLogItem {
    id: string;
    event: string;
    user: { id: number; name: string; email: string } | null;
    client: { id: string; name: string } | null;
    ip_address: string | null;
    user_agent: string | null;
    payload: Record<string, any> | null;
    created_at: string;
    created_at_human: string;
}

interface PaginatedLogs {
    data: AuditLogItem[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

const props = defineProps<{
    logs: PaginatedLogs;
    availableEvents: string[];
    filters: {
        event?: string;
        search?: string;
    };
}>();

const search = ref(props.filters.search || '');
const selectedEvent = ref(props.filters.event || '');

const handleFilter = () => {
    router.get(
        route('admin.audit-logs.index'),
        {
            event: selectedEvent.value || undefined,
            search: search.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

// Payload Modal
const showPayloadModal = ref(false);
const activePayload = ref<Record<string, any> | null>(null);
const activeLogEvent = ref<string>('');

const viewPayload = (log: AuditLogItem) => {
    activePayload.value = log.payload;
    activeLogEvent.value = log.event;
    showPayloadModal.value = true;
};

const getEventBadgeClass = (event: string): string => {
    if (event.includes('REUSE') || event.includes('FAILED') || event.includes('SUSPENDED')) {
        return 'bg-rose-500/10 text-rose-300 border-rose-500/30';
    }
    if (event.includes('PASSWORD') || event.includes('REVOKE') || event.includes('LOGOUT')) {
        return 'bg-amber-500/10 text-amber-300 border-amber-500/30';
    }
    if (event.includes('ROTATED') || event.includes('CREATED') || event.includes('MFA_ENABLED')) {
        return 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30';
    }
    return 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30';
};
</script>

<template>
    <AppLayout title="Audit Logs - Nhật Ký Kiểm Toán">
        <div class="space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        Nhật Ký Kiểm Toán An Ninh (Audit Logs)
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Theo dõi thời gian thực mọi sự kiện phát hành token, luân chuyển (rotation), thu hồi, và phát hiện xâm nhập.
                    </p>
                </div>
            </div>

            <!-- Filters Bar -->
            <div class="p-4 rounded-2xl border border-slate-800 bg-slate-900/60 backdrop-blur-xl flex flex-col sm:flex-row gap-4">
                <!-- Search input -->
                <div class="flex-1 relative">
                    <input
                        v-model="search"
                        @keyup.enter="handleFilter"
                        type="text"
                        placeholder="Tìm theo IP, Email người dùng, ID log..."
                        class="w-full pl-10 pr-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-slate-200 placeholder-slate-500 focus:border-indigo-500 outline-none"
                    />
                    <svg class="w-4 h-4 text-slate-500 absolute left-3.5 top-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                </div>

                <!-- Event Dropdown Filter -->
                <div class="sm:w-64">
                    <select
                        v-model="selectedEvent"
                        @change="handleFilter"
                        class="w-full px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-slate-200 focus:border-indigo-500 outline-none cursor-pointer"
                    >
                        <option value="">Tất cả sự kiện</option>
                        <option v-for="ev in availableEvents" :key="ev" :value="ev">{{ ev }}</option>
                    </select>
                </div>

                <button
                    @click="handleFilter"
                    class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-sm font-semibold transition"
                >
                    Lọc
                </button>
            </div>

            <!-- Table of Logs -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900/60 backdrop-blur-xl shadow-xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase tracking-wider text-slate-400 bg-slate-950/60 border-b border-slate-800/80">
                            <tr>
                                <th class="px-6 py-3.5 font-semibold">Thời Gian</th>
                                <th class="px-6 py-3.5 font-semibold">Sự Kiện (Event)</th>
                                <th class="px-6 py-3.5 font-semibold">Người Dùng</th>
                                <th class="px-6 py-3.5 font-semibold">Ứng Dụng (Client)</th>
                                <th class="px-6 py-3.5 font-semibold">IP Address</th>
                                <th class="px-6 py-3.5 font-semibold text-right">Chi Tiết</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="log in logs.data" :key="log.id" class="hover:bg-slate-800/30 transition">
                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <div class="font-medium text-slate-200">{{ log.created_at }}</div>
                                    <div class="text-[11px] text-slate-500">{{ log.created_at_human }}</div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span :class="['px-2.5 py-1 rounded-md text-xs font-mono font-semibold border', getEventBadgeClass(log.event)]">
                                        {{ log.event }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <div v-if="log.user">
                                        <div class="font-semibold text-slate-200">{{ log.user.name }}</div>
                                        <div class="text-slate-400 font-mono text-[11px]">{{ log.user.email }}</div>
                                    </div>
                                    <span v-else class="text-slate-500 italic">Hệ thống / Guest</span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-xs">
                                    <span v-if="log.client" class="font-medium text-indigo-300">
                                        {{ log.client.name }}
                                    </span>
                                    <span v-else class="text-slate-500">—</span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-slate-400">
                                    {{ log.ip_address || '—' }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap text-right">
                                    <button
                                        v-if="log.payload"
                                        @click="viewPayload(log)"
                                        class="px-3 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-medium transition"
                                    >
                                        Payload
                                    </button>
                                    <span v-else class="text-slate-600 text-xs">—</span>
                                </td>
                            </tr>

                            <tr v-if="logs.data.length === 0">
                                <td colspan="6" class="px-6 py-12 text-center text-slate-500 text-sm">
                                    Không tìm thấy nhật ký kiểm toán nào phù hợp.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="logs.total > 20" class="px-6 py-4 border-t border-slate-800/80 bg-slate-950/40 flex items-center justify-between text-xs text-slate-400">
                    <div>
                        Hiển thị trang <strong class="text-slate-200">{{ logs.current_page }}</strong> / {{ logs.last_page }} (Tổng cộng {{ logs.total }} sự kiện)
                    </div>

                    <div class="flex gap-2">
                        <Link
                            v-if="logs.prev_page_url"
                            :href="logs.prev_page_url"
                            class="px-3 py-1.5 rounded-lg border border-slate-700 hover:bg-slate-800 text-slate-300 font-medium transition"
                        >
                            Trang trước
                        </Link>
                        <Link
                            v-if="logs.next_page_url"
                            :href="logs.next_page_url"
                            class="px-3 py-1.5 rounded-lg border border-slate-700 hover:bg-slate-800 text-slate-300 font-medium transition"
                        >
                            Trang sau
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal: Xem Payload JSON -->
        <div v-if="showPayloadModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-2xl space-y-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-sm font-bold text-slate-100 flex items-center gap-2">
                        <span>Chi tiết Payload sự kiện:</span>
                        <code class="text-indigo-400 font-mono">{{ activeLogEvent }}</code>
                    </h3>
                    <button @click="showPayloadModal = false" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 max-h-80 overflow-y-auto">
                    <pre class="font-mono text-xs text-indigo-300 whitespace-pre-wrap">{{ JSON.stringify(activePayload, null, 2) }}</pre>
                </div>

                <div class="flex justify-end">
                    <button
                        @click="showPayloadModal = false"
                        class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition"
                    >
                        Đóng
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
