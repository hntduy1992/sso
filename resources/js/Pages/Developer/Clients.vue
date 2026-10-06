<script setup lang="ts">
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';

interface ClientItem {
    id: string;
    name: string;
    client_type: 'PUBLIC' | 'CONFIDENTIAL';
    redirect_uris: string[];
    grant_types: string[];
    backchannel_logout_uri: string | null;
    description: string | null;
    is_trusted: boolean;
    created_at: string;
}

const props = defineProps<{
    clients: ClientItem[];
    plainSecret?: string | null;
    newClientId?: string | null;
}>();

// Create Modal
const showCreateModal = ref(false);
const createForm = useForm({
    name: '',
    client_type: 'CONFIDENTIAL' as 'PUBLIC' | 'CONFIDENTIAL',
    redirect_uris: '',
    backchannel_logout_uri: '',
    description: '',
});

const submitCreate = () => {
    createForm.post(route('developer.clients.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showCreateModal.value = false;
            createForm.reset();
        },
    });
};

// Edit Modal
const showEditModal = ref(false);
const editingClient = ref<ClientItem | null>(null);
const editForm = useForm({
    name: '',
    redirect_uris: '',
    backchannel_logout_uri: '',
    description: '',
});

const openEditModal = (client: ClientItem) => {
    editingClient.value = client;
    editForm.name = client.name;
    editForm.redirect_uris = client.redirect_uris.join('\n');
    editForm.backchannel_logout_uri = client.backchannel_logout_uri || '';
    editForm.description = client.description || '';
    showEditModal.value = true;
};

const submitEdit = () => {
    if (!editingClient.value) return;

    editForm.put(route('developer.clients.update', editingClient.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showEditModal.value = false;
            editingClient.value = null;
        },
    });
};

// Regenerate Secret
const regenerateSecret = (client: ClientItem) => {
    if (!confirm(`Bạn có chắc muốn làm mới Secret cho "${client.name}"? Secret cũ sẽ mất hiệu lực ngay lập tức!`)) {
        return;
    }

    router.post(route('developer.clients.secret', client.id), {}, {
        preserveScroll: true,
    });
};

// Revoke Client
const revokeClient = (client: ClientItem) => {
    if (!confirm(`Bạn có chắc muốn thu hồi vĩnh viễn ứng dụng "${client.name}"? Mọi token phát hành cho ứng dụng này sẽ bị hủy bỏ!`)) {
        return;
    }

    router.delete(route('developer.clients.destroy', client.id), {
        preserveScroll: true,
    });
};

const copyToClipboard = (text: string, label: string) => {
    navigator.clipboard.writeText(text);
    alert(`Đã sao chép ${label} vào clipboard!`);
};
</script>

<template>
    <AppLayout title="Developer Portal - OAuth Clients">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-slate-100 flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-indigo-500/10 border border-indigo-500/20 text-indigo-400">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                            </svg>
                        </div>
                        Developer Portal — Quản lý OAuth Clients
                    </h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Đăng ký và quản lý các ứng dụng vệ tinh (SPA, Mobile, Backend) tích hợp Single Sign-On (SSO / OIDC).
                    </p>
                </div>

                <div>
                    <button
                        @click="showCreateModal = true"
                        class="px-5 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold text-sm transition shadow-lg shadow-indigo-600/25 flex items-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Đăng Ký Ứng Dụng Mới
                    </button>
                </div>
            </div>

            <!-- Newly Created / Regenerated Secret Alert Banner -->
            <div
                v-if="plainSecret"
                class="rounded-2xl border border-amber-500/40 bg-amber-950/20 p-6 backdrop-blur-xl shadow-xl space-y-4"
            >
                <div class="flex items-center gap-3 text-amber-300">
                    <svg class="w-6 h-6 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <div>
                        <h3 class="font-bold text-base">Client Secret Mới Đã Được Sinh!</h3>
                        <p class="text-xs text-amber-200/80">
                            Vì lý do an ninh, giá trị Secret chỉ hiển thị duy nhất một lần này và được mã hóa bcrypt trong cơ sở dữ liệu. Hãy sao chép và lưu trữ ngay lập tức.
                        </p>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div v-if="newClientId" class="p-3 bg-slate-950 rounded-xl border border-slate-800">
                        <div class="text-[11px] text-slate-400 mb-1">Client ID:</div>
                        <div class="flex items-center justify-between gap-2">
                            <code class="font-mono text-xs text-slate-200 truncate">{{ newClientId }}</code>
                            <button
                                @click="copyToClipboard(newClientId!, 'Client ID')"
                                class="text-xs text-indigo-400 hover:text-indigo-300 font-medium shrink-0"
                            >
                                Sao chép
                            </button>
                        </div>
                    </div>

                    <div class="p-3 bg-slate-950 rounded-xl border border-amber-500/30">
                        <div class="text-[11px] text-amber-400 mb-1 font-semibold">Client Secret (Bảo mật):</div>
                        <div class="flex items-center justify-between gap-2">
                            <code class="font-mono text-xs text-amber-300 truncate select-all">{{ plainSecret }}</code>
                            <button
                                @click="copyToClipboard(plainSecret!, 'Client Secret')"
                                class="text-xs text-amber-400 hover:text-amber-200 font-bold shrink-0"
                            >
                                Sao chép
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Clients List -->
            <div class="space-y-4">
                <div
                    v-for="client in clients"
                    :key="client.id"
                    class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 backdrop-blur-xl shadow-lg space-y-4"
                >
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex items-center gap-3">
                                <h3 class="text-lg font-bold text-slate-100">{{ client.name }}</h3>
                                <span
                                    :class="[
                                        'px-2.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider border',
                                        client.client_type === 'CONFIDENTIAL'
                                            ? 'bg-purple-500/10 text-purple-300 border-purple-500/30'
                                            : 'bg-cyan-500/10 text-cyan-300 border-cyan-500/30'
                                    ]"
                                >
                                    {{ client.client_type }}
                                </span>
                                <span
                                    v-if="client.is_trusted"
                                    class="px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-300 border border-emerald-500/30"
                                >
                                    First-Party Trusted
                                </span>
                            </div>
                            <p v-if="client.description" class="text-xs text-slate-400">{{ client.description }}</p>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                v-if="client.client_type === 'CONFIDENTIAL'"
                                @click="regenerateSecret(client)"
                                class="px-3 py-1.5 rounded-xl border border-slate-700 hover:border-amber-500/40 text-slate-300 hover:text-amber-300 hover:bg-amber-500/10 text-xs font-medium transition"
                            >
                                Làm mới Secret
                            </button>
                            <button
                                @click="openEditModal(client)"
                                class="px-3 py-1.5 rounded-xl border border-slate-700 hover:border-indigo-500/40 text-slate-300 hover:text-indigo-300 hover:bg-indigo-500/10 text-xs font-medium transition"
                            >
                                Chỉnh sửa
                            </button>
                            <button
                                @click="revokeClient(client)"
                                class="px-3 py-1.5 rounded-xl border border-slate-700 hover:border-rose-500/40 text-slate-300 hover:text-rose-300 hover:bg-rose-500/10 text-xs font-medium transition"
                            >
                                Thu hồi
                            </button>
                        </div>
                    </div>

                    <!-- Client Specs Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs bg-slate-950/70 p-4 rounded-xl border border-slate-800/80">
                        <div>
                            <div class="text-slate-500 mb-1">Client ID:</div>
                            <div class="flex items-center gap-2">
                                <code class="font-mono text-slate-300 truncate">{{ client.id }}</code>
                                <button @click="copyToClipboard(client.id, 'Client ID')" class="text-indigo-400 hover:text-indigo-300 shrink-0">Copy</button>
                            </div>
                        </div>

                        <div>
                            <div class="text-slate-500 mb-1">Backchannel Logout URI:</div>
                            <code class="font-mono text-slate-300 truncate block">
                                {{ client.backchannel_logout_uri || 'Chưa thiết lập (Không đồng bộ logout)' }}
                            </code>
                        </div>

                        <div class="md:col-span-2">
                            <div class="text-slate-500 mb-1">Allowed Redirect URIs:</div>
                            <div class="flex flex-wrap gap-2">
                                <span
                                    v-for="uri in client.redirect_uris"
                                    :key="uri"
                                    class="px-2 py-1 rounded bg-slate-900 border border-slate-800 text-slate-300 font-mono text-[11px]"
                                >
                                    {{ uri }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="clients.length === 0" class="text-center py-16 border border-dashed border-slate-800 rounded-2xl text-slate-500 text-sm">
                    Bạn chưa có OAuth Client nào. Bấm "Đăng Ký Ứng Dụng Mới" để bắt đầu tích hợp.
                </div>
            </div>
        </div>

        <!-- Modal: Đăng ký Client Mới -->
        <div v-if="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Đăng Ký OAuth Client Mới
                    </h3>
                    <button @click="showCreateModal = false" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitCreate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tên ứng dụng</label>
                        <input
                            v-model="createForm.name"
                            type="text"
                            required
                            placeholder="Ví dụ: Cổng Quản Lý Đào Tạo (Vue 3 SPA)"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 outline-none"
                        />
                        <p v-if="createForm.errors.name" class="mt-1 text-xs text-rose-400">{{ createForm.errors.name }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Loại Ứng dụng (Client Type)</label>
                        <div class="grid grid-cols-2 gap-3">
                            <button
                                type="button"
                                @click="createForm.client_type = 'CONFIDENTIAL'"
                                :class="[
                                    'p-3 rounded-xl border text-left transition',
                                    createForm.client_type === 'CONFIDENTIAL'
                                        ? 'border-indigo-500 bg-indigo-500/10 text-indigo-300'
                                        : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700'
                                ]"
                            >
                                <div class="font-bold text-xs text-slate-200">CONFIDENTIAL</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">Backend Web App có lưu Client Secret</div>
                            </button>

                            <button
                                type="button"
                                @click="createForm.client_type = 'PUBLIC'"
                                :class="[
                                    'p-3 rounded-xl border text-left transition',
                                    createForm.client_type === 'PUBLIC'
                                        ? 'border-cyan-500 bg-cyan-500/10 text-cyan-300'
                                        : 'border-slate-800 bg-slate-950 text-slate-400 hover:border-slate-700'
                                ]"
                            >
                                <div class="font-bold text-xs text-slate-200">PUBLIC (PKCE)</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">SPA (Vue/React) hoặc Mobile App Flutter</div>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Redirect URIs (Mỗi URI một dòng)</label>
                        <textarea
                            v-model="createForm.redirect_uris"
                            required
                            rows="3"
                            placeholder="https://app.example.com/callback&#10;http://localhost:3000/callback"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono focus:border-indigo-500 outline-none"
                        ></textarea>
                        <p v-if="createForm.errors.redirect_uris" class="mt-1 text-xs text-rose-400">{{ createForm.errors.redirect_uris }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Backchannel Logout URI (Tùy chọn)</label>
                        <input
                            v-model="createForm.backchannel_logout_uri"
                            type="url"
                            placeholder="https://app.example.com/oauth/backchannel-logout"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 outline-none"
                        />
                        <p class="text-[11px] text-slate-500 mt-1">IdP sẽ gửi POST logout_token tới đây khi người dùng đăng xuất khỏi SSO.</p>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Mô tả ứng dụng</label>
                        <input
                            v-model="createForm.description"
                            type="text"
                            placeholder="Mô tả ngắn gọn mục đích ứng dụng"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 outline-none"
                        />
                    </div>

                    <div class="flex gap-3 pt-3">
                        <button
                            @click="showCreateModal = false"
                            type="button"
                            class="flex-1 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            :disabled="createForm.processing"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition shadow-lg shadow-indigo-600/25 disabled:opacity-50"
                        >
                            {{ createForm.processing ? 'Đang tạo...' : 'Tạo Ứng Dụng' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Modal: Chỉnh sửa Client -->
        <div v-if="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm">
            <div class="w-full max-w-lg bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl space-y-6">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-100">Chỉnh sửa Ứng Dụng</h3>
                    <button @click="showEditModal = false" class="text-slate-400 hover:text-slate-200">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEdit" class="space-y-4">
                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Tên ứng dụng</label>
                        <input
                            v-model="editForm.name"
                            type="text"
                            required
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Redirect URIs (Mỗi URI một dòng)</label>
                        <textarea
                            v-model="editForm.redirect_uris"
                            required
                            rows="3"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-xs font-mono focus:border-indigo-500 outline-none"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Backchannel Logout URI</label>
                        <input
                            v-model="editForm.backchannel_logout_uri"
                            type="url"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 outline-none"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-medium text-slate-300 mb-1">Mô tả</label>
                        <input
                            v-model="editForm.description"
                            type="text"
                            class="w-full px-4 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-100 text-sm focus:border-indigo-500 outline-none"
                        />
                    </div>

                    <div class="flex gap-3 pt-3">
                        <button
                            @click="showEditModal = false"
                            type="button"
                            class="flex-1 py-2.5 rounded-xl border border-slate-700 hover:bg-slate-800 text-slate-300 text-sm font-medium transition"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="flex-1 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-sm font-semibold transition shadow-lg shadow-indigo-600/25 disabled:opacity-50"
                        >
                            {{ editForm.processing ? 'Đang lưu...' : 'Lưu Thay Đổi' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
