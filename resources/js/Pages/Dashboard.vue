<script setup lang="ts">
import { ref } from 'vue';
import { Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PageProps, User } from '@/types';

interface ExtendedUser extends User {
    deleted_at?: string | null;
}

interface PaginatedUsers {
    data: ExtendedUser[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

interface Stats {
    total: number;
    active: number;
    suspended: number;
    trashed: number;
    admins: number;
}

const props = defineProps<{
    users: PaginatedUsers;
    stats: Stats;
    filters: {
        search?: string;
        status?: string;
        role?: string;
    };
}>();

const page = usePage<PageProps>();
const searchQuery = ref(props.filters.search || '');
const currentStatus = ref(props.filters.status || '');
const currentRole = ref(props.filters.role || '');
const updatingUserId = ref<number | null>(null);

const applyFilters = () => {
    router.get(
        '/dashboard',
        {
            search: searchQuery.value || undefined,
            status: currentStatus.value || undefined,
            role: currentRole.value || undefined,
        },
        { preserveState: true, replace: true }
    );
};

const filterByStatus = (status: string) => {
    currentStatus.value = status;
    applyFilters();
};

const handleSearch = () => {
    applyFilters();
};

// Toggle status active / suspended
const toggleUserStatus = (user: ExtendedUser) => {
    const newStatus = user.status === 'active' ? 'suspended' : 'active';
    const actionName = newStatus === 'active' ? 'Mở khóa' : 'Khóa';

    if (!confirm(`Bạn có chắc chắn muốn ${actionName} tài khoản "${user.name}"?`)) {
        return;
    }

    updatingUserId.value = user.id;

    router.patch(
        `/admin/users/${user.id}/status`,
        { status: newStatus },
        {
            preserveScroll: true,
            onFinish: () => {
                updatingUserId.value = null;
            },
        }
    );
};

// Force logout user
const forceLogoutUser = (user: ExtendedUser) => {
    if (!confirm(`Bạn có chắc chắn muốn cưỡng chế đăng xuất "${user.name}" khỏi toàn bộ phiên và ứng dụng vệ tinh?`)) {
        return;
    }

    router.post(`/admin/users/${user.id}/force-logout`, {}, {
        preserveScroll: true,
    });
};

// Soft delete user
const softDeleteUser = (user: ExtendedUser) => {
    if (!confirm(`Bạn có chắc chắn muốn chuyển tài khoản "${user.name}" vào thùng rác? Người dùng sẽ không thể đăng nhập cho đến khi được khôi phục.`)) {
        return;
    }

    router.delete(`/admin/users/${user.id}`, {
        preserveScroll: true,
    });
};

// Restore soft-deleted user
const restoreUser = (user: ExtendedUser) => {
    if (!confirm(`Khôi phục tài khoản "${user.name}" hoạt động trở lại?`)) {
        return;
    }

    router.post(`/admin/users/${user.id}/restore`, {}, {
        preserveScroll: true,
    });
};

// Force permanent delete
const forceDeleteUser = (user: ExtendedUser) => {
    if (!confirm(`CẢNH BÁO NGUY HIỂM: Bạn có chắc chắn muốn XÓA VĨNH VIỄN tài khoản "${user.name}"? Toàn bộ dữ liệu hồ sơ, chức vụ, phiên đăng nhập sẽ bị xóa hoàn toàn khỏi cơ sở dữ liệu và không thể hoàn tác!`)) {
        return;
    }

    router.delete(`/admin/users/${user.id}/force`, {
        preserveScroll: true,
    });
};

// Edit User Modal
const isEditModalOpen = ref(false);
const editingUserId = ref<number | null>(null);

const editForm = useForm({
    name: '',
    email: '',
    role: 'user' as 'admin' | 'user',
    status: 'active' as 'active' | 'suspended',
    full_name: '',
    phone_number: '',
    contact_email: '',
    address: '',
    gender: '' as '' | 'male' | 'female' | 'other',
    date_of_birth: '',
    bio: '',
});

const openEditModal = (user: ExtendedUser) => {
    editingUserId.value = user.id;
    editForm.clearErrors();
    editForm.name = user.name;
    editForm.email = user.email;
    editForm.role = user.role;
    editForm.status = user.status;
    editForm.full_name = user.profile?.full_name || user.name;
    editForm.phone_number = (user.profile as Record<string, any>)?.phone_number || '';
    editForm.contact_email = (user.profile as Record<string, any>)?.contact_email || '';
    editForm.address = (user.profile as Record<string, any>)?.address || '';
    editForm.gender = (user.profile as Record<string, any>)?.gender || '';
    editForm.date_of_birth = (user.profile as Record<string, any>)?.date_of_birth || '';
    editForm.bio = (user.profile as Record<string, any>)?.bio || '';
    isEditModalOpen.value = true;
};

const closeEditModal = () => {
    isEditModalOpen.value = false;
    editingUserId.value = null;
    editForm.reset();
};

const submitEditForm = () => {
    if (!editingUserId.value) return;

    editForm.put(`/admin/users/${editingUserId.value}`, {
        preserveScroll: true,
        onSuccess: () => closeEditModal(),
    });
};
</script>

<template>
    <AppLayout title="Quản trị Người Dùng CSM">
        <div class="space-y-6 max-w-full">
            <!-- Page Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight flex items-center gap-2.5">
                        <span class="p-2 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                            </svg>
                        </span>
                        Quản Lý Người Dùng & Hồ Sơ Định Danh
                    </h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Bảng điều khiển CSM: Quản lý danh sách tài khoản, chỉnh sửa thông tin, phân quyền và lưu trữ thùng rác
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <Link
                        href="/admin/departments"
                        class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition"
                    >
                        <svg class="w-4 h-4 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        Sơ đồ Cơ cấu & HRM
                    </Link>
                </div>
            </div>

            <!-- KPI Summary Cards (CSM Metrics) -->
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Users -->
                <div
                    @click="filterByStatus('')"
                    class="bg-slate-900/60 border rounded-2xl p-4.5 backdrop-blur-sm cursor-pointer transition hover:border-indigo-500/50"
                    :class="currentStatus === '' ? 'border-indigo-500/80 bg-indigo-950/20' : 'border-slate-800/80'"
                >
                    <div class="flex items-center justify-between text-slate-400 mb-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wider">Tổng tài khoản</span>
                        <div class="p-1.5 rounded-lg bg-indigo-500/10 text-indigo-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-white">{{ stats.total }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Tất cả người dùng hệ thống</div>
                </div>

                <!-- Active Users -->
                <div
                    @click="filterByStatus('active')"
                    class="bg-slate-900/60 border rounded-2xl p-4.5 backdrop-blur-sm cursor-pointer transition hover:border-emerald-500/50"
                    :class="currentStatus === 'active' ? 'border-emerald-500/80 bg-emerald-950/20' : 'border-slate-800/80'"
                >
                    <div class="flex items-center justify-between text-slate-400 mb-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wider">Đang hoạt động</span>
                        <div class="p-1.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-emerald-400">{{ stats.active }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Cho phép đăng nhập SSO</div>
                </div>

                <!-- Suspended Users -->
                <div
                    @click="filterByStatus('suspended')"
                    class="bg-slate-900/60 border rounded-2xl p-4.5 backdrop-blur-sm cursor-pointer transition hover:border-amber-500/50"
                    :class="currentStatus === 'suspended' ? 'border-amber-500/80 bg-amber-950/20' : 'border-slate-800/80'"
                >
                    <div class="flex items-center justify-between text-slate-400 mb-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wider">Tài khoản bị khóa</span>
                        <div class="p-1.5 rounded-lg bg-amber-500/10 text-amber-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-amber-400">{{ stats.suspended }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Tạm dừng quyền truy cập</div>
                </div>

                <!-- Trashed Users -->
                <div
                    @click="filterByStatus('trashed')"
                    class="bg-slate-900/60 border rounded-2xl p-4.5 backdrop-blur-sm cursor-pointer transition hover:border-rose-500/50"
                    :class="currentStatus === 'trashed' ? 'border-rose-500/80 bg-rose-950/20' : 'border-slate-800/80'"
                >
                    <div class="flex items-center justify-between text-slate-400 mb-1.5">
                        <span class="text-xs font-semibold uppercase tracking-wider">Thùng rác (Đã xóa)</span>
                        <div class="p-1.5 rounded-lg bg-rose-500/10 text-rose-400">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-2xl font-extrabold text-rose-400">{{ stats.trashed }}</div>
                    <div class="text-[11px] text-slate-500 mt-0.5">Có thể khôi phục hoặc xóa hẳn</div>
                </div>
            </div>

            <!-- Main CSM Table & Filter Bar -->
            <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-sm shadow-xl">
                <!-- Status Tab Pills -->
                <div class="p-4 border-b border-slate-800/80 flex flex-wrap items-center justify-between gap-4 bg-slate-950/40">
                    <div class="flex items-center gap-1.5 overflow-x-auto">
                        <button
                            type="button"
                            @click="filterByStatus('')"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                                currentStatus === ''
                                    ? 'bg-indigo-600 text-white shadow-md shadow-indigo-600/20'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Tất cả ({{ stats.total }})
                        </button>
                        <button
                            type="button"
                            @click="filterByStatus('active')"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                                currentStatus === 'active'
                                    ? 'bg-emerald-600 text-white shadow-md shadow-emerald-600/20'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Đang hoạt động ({{ stats.active }})
                        </button>
                        <button
                            type="button"
                            @click="filterByStatus('suspended')"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                                currentStatus === 'suspended'
                                    ? 'bg-amber-600 text-white shadow-md shadow-amber-600/20'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Bị khóa ({{ stats.suspended }})
                        </button>
                        <button
                            type="button"
                            @click="filterByStatus('trashed')"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                                currentStatus === 'trashed'
                                    ? 'bg-rose-600 text-white shadow-md shadow-rose-600/20'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Thùng rác ({{ stats.trashed }})
                        </button>
                    </div>

                    <!-- Search and Role Dropdown -->
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <!-- Role Filter -->
                        <select
                            v-model="currentRole"
                            @change="applyFilters"
                            class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-200 focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">Tất cả vai trò</option>
                            <option value="admin">Quản trị viên (Admin)</option>
                            <option value="user">Người dùng (User)</option>
                        </select>

                        <!-- Search Input -->
                        <div class="relative flex-1 sm:w-64">
                            <input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Tìm theo tên, email, SĐT..."
                                class="w-full pl-8 pr-3 py-1.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500"
                            />
                            <svg class="w-3.5 h-3.5 text-slate-500 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>

                        <button
                            type="button"
                            @click="handleSearch"
                            class="px-3 py-1.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-semibold transition"
                        >
                            Tìm
                        </button>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-950/70 text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-5 py-3 font-semibold">Người Dùng</th>
                                <th class="px-5 py-3 font-semibold">Chức Vụ (HRM)</th>
                                <th class="px-5 py-3 font-semibold">Vai Trò Hệ Thống</th>
                                <th class="px-5 py-3 font-semibold">Trạng Thái</th>
                                <th class="px-5 py-3 font-semibold">Ngày Tạo</th>
                                <th class="px-5 py-3 font-semibold text-right">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-slate-800/30 transition"
                            >
                                <!-- User Info -->
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-xl bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-indigo-300 shrink-0">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-semibold text-slate-100 flex items-center gap-1.5">
                                                <span>{{ user.profile?.full_name || user.name }}</span>
                                                <span v-if="user.profile?.full_name && user.profile.full_name !== user.name" class="text-xs text-slate-400">
                                                    ({{ user.name }})
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-400">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- HRM Position Badge -->
                                <td class="px-5 py-3.5">
                                    <div v-if="user.active_positions && user.active_positions.length > 0" class="flex flex-col gap-1">
                                        <div
                                            v-for="pos in user.active_positions"
                                            :key="pos.id"
                                            class="inline-flex items-center gap-1.5"
                                        >
                                            <span
                                                :class="[
                                                    'px-2 py-0.5 rounded text-[11px] font-semibold border',
                                                    pos.is_primary
                                                        ? 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'
                                                        : 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                                                ]"
                                            >
                                                {{ pos.position_type?.name }}
                                                <span v-if="!pos.is_primary" class="text-[9px] opacity-80">(Kiêm)</span>
                                            </span>
                                            <span class="text-[11px] text-slate-400">
                                                {{ pos.department?.name }}
                                            </span>
                                        </div>
                                    </div>
                                    <span v-else class="text-xs text-slate-500 italic">
                                        Chưa phân bổ
                                    </span>
                                </td>

                                <!-- Role Badge -->
                                <td class="px-5 py-3.5">
                                    <span
                                        class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold uppercase tracking-wide border"
                                        :class="user.role === 'admin'
                                            ? 'bg-amber-500/10 text-amber-300 border-amber-500/30'
                                            : 'bg-indigo-500/10 text-indigo-300 border-indigo-500/30'"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-5 py-3.5">
                                    <span
                                        v-if="user.deleted_at"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-rose-500/15 text-rose-300 border border-rose-500/30"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        Đã xóa tạm
                                    </span>
                                    <span
                                        v-else-if="user.status === 'active'"
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        Hoạt động
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-500/10 text-amber-400 border border-amber-500/30"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                        Bị khóa
                                    </span>
                                </td>

                                <!-- Created At -->
                                <td class="px-5 py-3.5 text-xs text-slate-400">
                                    {{ user.created_at ? new Date(user.created_at).toLocaleDateString('vi-VN') : '—' }}
                                </td>

                                <!-- Actions -->
                                <td class="px-5 py-3.5 text-right">
                                    <!-- When user is Trashed (Soft Deleted) -->
                                    <div v-if="user.deleted_at" class="flex items-center justify-end gap-2">
                                        <button
                                            type="button"
                                            @click="restoreUser(user)"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 transition"
                                            title="Khôi phục tài khoản người dùng"
                                        >
                                            Khôi phục
                                        </button>
                                        <button
                                            v-if="page.props.auth.user?.id !== user.id"
                                            type="button"
                                            @click="forceDeleteUser(user)"
                                            class="px-2.5 py-1 rounded-lg text-xs font-semibold bg-rose-500/15 hover:bg-rose-500/25 text-rose-300 border border-rose-500/30 transition"
                                            title="Xóa vĩnh viễn khỏi hệ thống"
                                        >
                                            Xóa hẳn
                                        </button>
                                    </div>

                                    <!-- When user is Active / Normal -->
                                    <div v-else class="flex items-center justify-end gap-1.5">
                                        <!-- Edit button -->
                                        <button
                                            type="button"
                                            @click="openEditModal(user)"
                                            class="px-2.5 py-1 rounded-lg text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
                                            title="Chỉnh sửa thông tin"
                                        >
                                            Sửa
                                        </button>

                                        <!-- HRM Detail Link -->
                                        <Link
                                            :href="`/admin/users/${user.id}`"
                                            class="px-2.5 py-1 rounded-lg text-xs font-medium bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 transition"
                                            title="Xem hồ sơ nhân sự & phân bổ chức vụ"
                                        >
                                            Hồ sơ & Chức vụ
                                        </Link>

                                        <template v-if="page.props.auth.user?.id !== user.id">
                                            <!-- Force Logout -->
                                            <button
                                                type="button"
                                                @click="forceLogoutUser(user)"
                                                class="px-2 py-1 rounded-lg text-xs font-medium bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 transition"
                                                title="Cưỡng chế đăng xuất"
                                            >
                                                ⚡
                                            </button>

                                            <!-- Toggle Status Lock/Unlock -->
                                            <button
                                                type="button"
                                                :disabled="updatingUserId === user.id"
                                                @click="toggleUserStatus(user)"
                                                class="px-2.5 py-1 rounded-lg text-xs font-medium transition disabled:opacity-50"
                                                :class="user.status === 'active'
                                                    ? 'bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30'
                                                    : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                                                :title="user.status === 'active' ? 'Khóa tài khoản' : 'Mở khóa tài khoản'"
                                            >
                                                {{ user.status === 'active' ? 'Khóa' : 'Mở khóa' }}
                                            </button>

                                            <!-- Soft Delete (Thùng rác) -->
                                            <button
                                                type="button"
                                                @click="softDeleteUser(user)"
                                                class="p-1 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                                title="Chuyển vào thùng rác"
                                            >
                                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                </svg>
                                            </button>
                                        </template>

                                        <span v-else class="text-[11px] text-slate-500 italic pl-1">
                                            (Bạn)
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination Footer -->
                <div class="p-4 border-t border-slate-800 flex items-center justify-between text-xs text-slate-400">
                    <div>
                        Hiển thị {{ users.data.length }} trên tổng số {{ users.total }} tài khoản
                    </div>
                    <div class="flex items-center gap-2">
                        <Link
                            v-if="users.prev_page_url"
                            :href="users.prev_page_url"
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl transition"
                        >
                            &larr; Trang trước
                        </Link>
                        <Link
                            v-if="users.next_page_url"
                            :href="users.next_page_url"
                            class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-slate-200 rounded-xl transition"
                        >
                            Trang sau &rarr;
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit User Modal (CSM Modal) -->
        <div v-if="isEditModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-2xl w-full p-6 shadow-2xl space-y-5 animate-in fade-in duration-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div>
                        <h3 class="text-lg font-bold text-white">Chỉnh Sửa Thông Tin Người Dùng</h3>
                        <p class="text-xs text-slate-400">Cập nhật tài khoản đăng nhập và hồ sơ nhân sự</p>
                    </div>
                    <button @click="closeEditModal" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitEditForm" class="space-y-4">
                    <!-- Account Details -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Tên hiển thị (Username) *
                            </label>
                            <input
                                v-model="editForm.name"
                                type="text"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                            <div v-if="editForm.errors.name" class="text-xs text-rose-400 mt-1">{{ editForm.errors.name }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Email đăng nhập *
                            </label>
                            <input
                                v-model="editForm.email"
                                type="email"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                            <div v-if="editForm.errors.email" class="text-xs text-rose-400 mt-1">{{ editForm.errors.email }}</div>
                        </div>
                    </div>

                    <!-- Role & Status -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Vai trò hệ thống *
                            </label>
                            <select
                                v-model="editForm.role"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            >
                                <option value="user">Người dùng (User)</option>
                                <option value="admin">Quản trị viên (Admin)</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1">
                                Trạng thái tài khoản *
                            </label>
                            <select
                                v-model="editForm.status"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            >
                                <option value="active">Đang hoạt động (Active)</option>
                                <option value="suspended">Tạm khóa (Suspended)</option>
                            </select>
                        </div>
                    </div>

                    <!-- HRM Profile Fields -->
                    <div class="pt-2 border-t border-slate-800 space-y-3">
                        <div class="text-xs font-bold uppercase tracking-wider text-indigo-400">
                            Hồ Sơ Nhân Sự (HRM Profile)
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Họ và tên đầy đủ</label>
                                <input
                                    v-model="editForm.full_name"
                                    type="text"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Số điện thoại</label>
                                <input
                                    v-model="editForm.phone_number"
                                    type="text"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Giới tính</label>
                                <select
                                    v-model="editForm.gender"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                                >
                                    <option value="">-- Chọn --</option>
                                    <option value="male">Nam</option>
                                    <option value="female">Nữ</option>
                                    <option value="other">Khác</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Ngày sinh</label>
                                <input
                                    v-model="editForm.date_of_birth"
                                    type="date"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>

                            <div>
                                <label class="block text-xs text-slate-400 mb-1">Email phụ liên hệ</label>
                                <input
                                    v-model="editForm.contact_email"
                                    type="email"
                                    class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs text-slate-400 mb-1">Địa chỉ thường trú</label>
                            <input
                                v-model="editForm.address"
                                type="text"
                                class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="closeEditModal"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="submit"
                            :disabled="editForm.processing"
                            class="px-5 py-2 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition disabled:opacity-50"
                        >
                            Lưu thông tin
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
