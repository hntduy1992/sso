<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

interface ClientItem {
    id: string;
    name: string;
    client_type: string;
    description: string | null;
    grants_count: number;
}

interface UserGrant {
    id: number;
    user_id: number;
    name: string;
    full_name: string | null;
    email: string;
    granted_at: string | null;
}

interface DepartmentGrant {
    id: number;
    department_id: number;
    name: string;
    code: string;
    type_label: string;
    members_count: number;
    granted_at: string | null;
}

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
    type_label: string;
}

interface UserResult {
    id: number;
    name: string;
    full_name: string | null;
    email: string;
}

const props = defineProps<{
    clients: ClientItem[];
    selectedClientId: string | null;
    userGrants: UserGrant[];
    departmentGrants: DepartmentGrant[];
    availableDepartments: DepartmentOption[];
    userResults: UserResult[];
    search: string;
}>();

const selectedClient = computed(() => props.clients.find((c) => c.id === props.selectedClientId) ?? null);

const baseUrl = '/admin/application-access';

const searchTerm = ref(props.search || '');
const departmentToGrant = ref<number | ''>('');
const processing = ref(false);

let searchTimer: ReturnType<typeof setTimeout> | undefined;

onBeforeUnmount(() => clearTimeout(searchTimer));

const selectClient = (clientId: string) => {
    searchTerm.value = '';
    router.get(baseUrl, { client: clientId }, { preserveScroll: true });
};

const onSearchInput = () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get(
            baseUrl,
            { client: props.selectedClientId, search: searchTerm.value || undefined },
            { preserveState: true, preserveScroll: true, replace: true, only: ['userResults', 'search'] },
        );
    }, 300);
};

const finish = () => {
    processing.value = false;
};

const grantUser = (userId: number) => {
    if (!props.selectedClientId) return;
    processing.value = true;
    router.post(
        `${baseUrl}/${props.selectedClientId}/users`,
        { user_id: userId },
        {
            preserveScroll: true,
            onSuccess: () => {
                searchTerm.value = '';
            },
            onFinish: finish,
        },
    );
};

const grantDepartment = () => {
    if (!props.selectedClientId || departmentToGrant.value === '') return;
    processing.value = true;
    router.post(
        `${baseUrl}/${props.selectedClientId}/departments`,
        { department_id: departmentToGrant.value },
        {
            preserveScroll: true,
            onSuccess: () => {
                departmentToGrant.value = '';
            },
            onFinish: finish,
        },
    );
};

const revoke = (grantId: number, label: string) => {
    if (!props.selectedClientId) return;
    if (!confirm(`Thu hồi quyền truy cập của ${label}?`)) return;
    processing.value = true;
    router.delete(`${baseUrl}/${props.selectedClientId}/grants/${grantId}`, {
        preserveScroll: true,
        onFinish: finish,
    });
};
</script>

<template>
    <AppLayout title="Quyền truy cập ứng dụng">
        <div class="space-y-6">
            <div>
                <h1 class="text-2xl font-bold text-slate-100">Quyền truy cập ứng dụng</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Chỉ người dùng được cấp quyền (cá nhân hoặc theo đơn vị) mới đăng nhập được vào từng ứng dụng.
                    Quản trị viên hệ thống luôn có quyền truy cập.
                </p>
            </div>

            <div v-if="clients.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-10 text-center text-sm text-slate-400">
                Chưa có ứng dụng OAuth nào. Hãy đăng ký ứng dụng tại mục "Ứng dụng OAuth" trước.
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Applications list -->
                <aside class="lg:col-span-1 rounded-2xl border border-slate-800 bg-slate-900/60 p-3 space-y-1 h-fit">
                    <div class="px-3 py-2 text-[10px] font-bold uppercase tracking-wider text-slate-300">Ứng dụng</div>
                    <button
                        v-for="client in clients"
                        :id="`app-${client.id}`"
                        :key="client.id"
                        type="button"
                        :class="[
                            'w-full text-left px-3 py-2.5 rounded-xl border transition',
                            client.id === selectedClientId
                                ? 'bg-indigo-600/20 border-indigo-500/30 text-indigo-200'
                                : 'border-transparent text-slate-300 hover:bg-slate-800/60',
                        ]"
                        @click="selectClient(client.id)"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-sm font-medium truncate">{{ client.name }}</span>
                            <span
                                :class="[
                                    'shrink-0 px-2 py-0.5 rounded-full text-[10px] font-semibold border',
                                    client.grants_count > 0
                                        ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/30'
                                        : 'bg-rose-500/10 text-rose-300 border-rose-500/30',
                                ]"
                            >
                                {{ client.grants_count > 0 ? `${client.grants_count} quyền` : 'Chưa cấp' }}
                            </span>
                        </div>
                        <div class="text-[11px] text-slate-500 truncate">{{ client.client_type }}</div>
                    </button>
                </aside>

                <!-- Selected application grants -->
                <section v-if="selectedClient" class="lg:col-span-2 space-y-6">
                    <!-- Departments -->
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 space-y-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-100">Cấp theo đơn vị</h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Toàn bộ người dùng đang giữ chức vụ trong đơn vị đều được vào "{{ selectedClient.name }}".
                                Người mới vào đơn vị tự có quyền, người rời đơn vị tự mất quyền.
                            </p>
                        </div>

                        <form class="flex flex-col sm:flex-row gap-3" @submit.prevent="grantDepartment">
                            <select
                                id="grant-department-select"
                                v-model="departmentToGrant"
                                class="flex-1 px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-slate-100 focus:outline-none focus:border-indigo-500"
                            >
                                <option value="">-- Chọn đơn vị --</option>
                                <option v-for="dept in availableDepartments" :key="dept.id" :value="dept.id">
                                    [{{ dept.code }}] {{ dept.name }} · {{ dept.type_label }}
                                </option>
                            </select>
                            <button
                                id="grant-department-button"
                                type="submit"
                                :disabled="processing || departmentToGrant === ''"
                                class="px-4 py-2 rounded-xl text-sm font-medium bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-40 disabled:cursor-not-allowed transition"
                            >
                                Cấp cho đơn vị
                            </button>
                        </form>

                        <ul v-if="departmentGrants.length" class="divide-y divide-slate-800/70">
                            <li v-for="grant in departmentGrants" :key="grant.id" class="py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-100 truncate">[{{ grant.code }}] {{ grant.name }}</div>
                                    <div class="text-xs text-slate-400">
                                        {{ grant.type_label }} · {{ grant.members_count }} nhân sự hiện tại
                                        <span v-if="grant.granted_at"> · cấp ngày {{ grant.granted_at }}</span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium text-rose-300 hover:text-white hover:bg-rose-600/30 border border-rose-500/30 transition"
                                    @click="revoke(grant.id, `đơn vị ${grant.name}`)"
                                >
                                    Thu hồi
                                </button>
                            </li>
                        </ul>
                        <div v-else class="text-sm text-slate-500">Chưa cấp cho đơn vị nào.</div>
                    </div>

                    <!-- Individual users -->
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-5 space-y-4">
                        <div>
                            <h2 class="text-base font-semibold text-slate-100">Cấp cho người dùng cụ thể</h2>
                            <p class="text-xs text-slate-400 mt-0.5">Tìm theo tên, email hoặc số điện thoại.</p>
                        </div>

                        <div class="relative">
                            <input
                                id="grant-user-search"
                                v-model="searchTerm"
                                type="text"
                                autocomplete="off"
                                placeholder="Nhập ít nhất 2 ký tự để tìm người dùng..."
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                                @input="onSearchInput"
                            />

                            <div
                                v-if="searchTerm.trim().length >= 2"
                                class="mt-2 rounded-xl border border-slate-800 bg-slate-950/80 divide-y divide-slate-800/70 overflow-hidden"
                            >
                                <div
                                    v-for="result in userResults"
                                    :key="result.id"
                                    class="px-3 py-2.5 flex items-center justify-between gap-3"
                                >
                                    <div class="min-w-0">
                                        <div class="text-sm text-slate-100 truncate">{{ result.full_name || result.name }}</div>
                                        <div class="text-xs text-slate-400 truncate">{{ result.email }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        :disabled="processing"
                                        class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium bg-indigo-600 hover:bg-indigo-500 text-white disabled:opacity-40 transition"
                                        @click="grantUser(result.id)"
                                    >
                                        Cấp quyền
                                    </button>
                                </div>
                                <div v-if="userResults.length === 0" class="px-3 py-3 text-sm text-slate-500">
                                    Không tìm thấy người dùng phù hợp (hoặc đã được cấp quyền).
                                </div>
                            </div>
                        </div>

                        <ul v-if="userGrants.length" class="divide-y divide-slate-800/70">
                            <li v-for="grant in userGrants" :key="grant.id" class="py-3 flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-sm font-medium text-slate-100 truncate">{{ grant.full_name || grant.name }}</div>
                                    <div class="text-xs text-slate-400 truncate">
                                        {{ grant.email }}
                                        <span v-if="grant.granted_at"> · cấp ngày {{ grant.granted_at }}</span>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 px-3 py-1.5 rounded-lg text-xs font-medium text-rose-300 hover:text-white hover:bg-rose-600/30 border border-rose-500/30 transition"
                                    @click="revoke(grant.id, grant.full_name || grant.name)"
                                >
                                    Thu hồi
                                </button>
                            </li>
                        </ul>
                        <div v-else class="text-sm text-slate-500">Chưa cấp cho người dùng cụ thể nào.</div>
                    </div>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
