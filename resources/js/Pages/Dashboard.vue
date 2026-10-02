<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import type { PageProps, User } from '@/types';

interface PaginatedUsers {
    data: User[];
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
    admins: number;
}

const props = defineProps<{
    users: PaginatedUsers;
    stats: Stats;
    filters: {
        search?: string;
    };
}>();

const page = usePage<PageProps>();
const searchQuery = ref(props.filters.search || '');
const updatingUserId = ref<number | null>(null);

const handleSearch = () => {
    router.get(
        '/dashboard',
        { search: searchQuery.value },
        { preserveState: true, replace: true }
    );
};

const toggleUserStatus = (user: User) => {
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

const handleLogout = () => {
    router.post('/logout');
};
</script>

<template>
    <Head title="SSO Admin Dashboard - Quản trị Danh tính" />

    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col selection:bg-indigo-500 selection:text-white">
        <!-- Top Navbar -->
        <header class="border-b border-slate-800/80 bg-slate-900/60 backdrop-blur-xl sticky top-0 z-30">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand Logo -->
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 to-cyan-400 p-[1px] shadow-md shadow-indigo-500/20">
                        <div class="w-full h-full bg-slate-950 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                    </div>
                    <div>
                        <span class="font-bold text-base bg-gradient-to-r from-white to-slate-300 bg-clip-text text-transparent">
                            SSO Identity Hub
                        </span>
                        <span class="hidden sm:inline-block ml-2 px-2 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                            Central Auth
                        </span>
                    </div>
                </div>

                <!-- Current User Profile & Logout -->
                <div class="flex items-center gap-4">
                    <div v-if="page.props.auth.user" class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-sm text-white shadow">
                            {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div class="hidden md:block text-right">
                            <div class="text-sm font-medium text-slate-200">
                                {{ page.props.auth.user.name }}
                            </div>
                            <div class="text-xs text-slate-400 flex items-center justify-end gap-1.5">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                <span>{{ page.props.auth.user.email }}</span>
                                <span class="capitalize px-1.5 py-0.2 rounded text-[10px] bg-slate-800 text-indigo-300 border border-slate-700">
                                    {{ page.props.auth.user.role }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <button
                        type="button"
                        @click="handleLogout"
                        class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-slate-800/80 hover:bg-rose-500/10 hover:text-rose-400 border border-slate-700 hover:border-rose-500/30 text-xs font-medium text-slate-300 transition duration-150 cursor-pointer"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span class="hidden sm:inline">Đăng xuất</span>
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content Area -->
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
            <!-- Flash Message Banner -->
            <div v-if="page.props.flash?.success" class="p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/30 text-emerald-300 text-sm flex items-center justify-between shadow-lg shadow-emerald-950/20">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>{{ page.props.flash.success }}</span>
                </div>
            </div>

            <div v-if="page.props.flash?.error" class="p-4 rounded-xl bg-rose-500/10 border border-rose-500/30 text-rose-300 text-sm flex items-center justify-between shadow-lg shadow-rose-950/20">
                <div class="flex items-center gap-3">
                    <svg class="w-5 h-5 text-rose-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ page.props.flash.error }}</span>
                </div>
            </div>

            <!-- Page Title Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-white tracking-tight">
                        Trung Tâm Quản Lý Định Danh (IAM Hub)
                    </h2>
                    <p class="text-sm text-slate-400 mt-1">
                        Kiểm soát tài khoản người dùng, phân quyền và trạng thái truy cập hệ thống SSO
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
                        SSO Server: Hoạt động bình thường
                    </span>
                </div>
            </div>

            <!-- KPI Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Total Users -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between text-slate-400 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Tổng tài khoản</span>
                        <div class="p-2 rounded-xl bg-indigo-500/10 text-indigo-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-white">{{ stats.total }}</div>
                    <div class="text-xs text-slate-500 mt-1">Được lưu trữ trên Identity Database</div>
                </div>

                <!-- Active Users -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between text-slate-400 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Đang hoạt động</span>
                        <div class="p-2 rounded-xl bg-emerald-500/10 text-emerald-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-emerald-400">{{ stats.active }}</div>
                    <div class="text-xs text-slate-500 mt-1">Được phép đăng nhập hệ thống</div>
                </div>

                <!-- Suspended Users -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between text-slate-400 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Tài khoản bị khóa</span>
                        <div class="p-2 rounded-xl bg-rose-500/10 text-rose-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-rose-400">{{ stats.suspended }}</div>
                    <div class="text-xs text-slate-500 mt-1">Bị chặn đăng nhập vào SSO Hub</div>
                </div>

                <!-- Administrators -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur-sm">
                    <div class="flex items-center justify-between text-slate-400 mb-2">
                        <span class="text-xs font-semibold uppercase tracking-wider">Quản trị viên</span>
                        <div class="p-2 rounded-xl bg-amber-500/10 text-amber-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
                            </svg>
                        </div>
                    </div>
                    <div class="text-3xl font-extrabold text-amber-400">{{ stats.admins }}</div>
                    <div class="text-xs text-slate-500 mt-1">Quyền quản trị toàn hệ thống</div>
                </div>
            </div>

            <!-- User Management Table Section -->
            <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl overflow-hidden backdrop-blur-sm shadow-xl">
                <!-- Header & Search Toolbar -->
                <div class="p-5 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h3 class="font-bold text-lg text-white">Danh Sách Người Dùng Tập Trung</h3>
                        <p class="text-xs text-slate-400">Xem thông tin và thay đổi trạng thái hoạt động của tài khoản</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative w-full sm:w-64">
                            <input
                                v-model="searchQuery"
                                @keyup.enter="handleSearch"
                                type="text"
                                placeholder="Tìm theo tên hoặc email..."
                                class="w-full pl-9 pr-4 py-2 bg-slate-950/70 border border-slate-800 rounded-xl text-xs text-slate-100 placeholder-slate-500 focus:outline-none focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500"
                            />
                            <svg class="w-4 h-4 text-slate-500 absolute left-3 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            </svg>
                        </div>
                        <button
                            type="button"
                            @click="handleSearch"
                            class="px-3.5 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-medium transition cursor-pointer"
                        >
                            Tìm
                        </button>
                    </div>
                </div>

                <!-- Table Content -->
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-950/50 text-slate-400 border-b border-slate-800">
                            <tr>
                                <th class="px-6 py-3.5 font-semibold">Người Dùng</th>
                                <th class="px-6 py-3.5 font-semibold">Vai Trò (Role)</th>
                                <th class="px-6 py-3.5 font-semibold">Trạng Thái</th>
                                <th class="px-6 py-3.5 font-semibold">Thời Gian Tạo</th>
                                <th class="px-6 py-3.5 font-semibold text-right">Thao Tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr
                                v-for="user in users.data"
                                :key="user.id"
                                class="hover:bg-slate-800/30 transition"
                            >
                                <!-- User Info -->
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-xs text-indigo-300">
                                            {{ user.name.charAt(0).toUpperCase() }}
                                        </div>
                                        <div>
                                            <div class="font-medium text-slate-100">{{ user.name }}</div>
                                            <div class="text-xs text-slate-400">{{ user.email }}</div>
                                        </div>
                                    </div>
                                </td>

                                <!-- Role Badge -->
                                <td class="px-6 py-4">
                                    <span
                                        class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium uppercase tracking-wide"
                                        :class="user.role === 'admin'
                                            ? 'bg-amber-500/10 text-amber-300 border border-amber-500/30'
                                            : 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/30'"
                                    >
                                        {{ user.role }}
                                    </span>
                                </td>

                                <!-- Status Badge -->
                                <td class="px-6 py-4">
                                    <span
                                        v-if="user.status === 'active'"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/30"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                        Hoạt động
                                    </span>
                                    <span
                                        v-else
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-rose-500/10 text-rose-400 border border-rose-500/30"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                        Đã khóa
                                    </span>
                                </td>

                                <!-- Created At -->
                                <td class="px-6 py-4 text-xs text-slate-400">
                                    {{ user.created_at ? new Date(user.created_at).toLocaleDateString('vi-VN') : '—' }}
                                </td>

                                <!-- Actions -->
                                <td class="px-6 py-4 text-right">
                                    <button
                                        v-if="page.props.auth.user?.id !== user.id"
                                        type="button"
                                        :disabled="updatingUserId === user.id"
                                        @click="toggleUserStatus(user)"
                                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
                                        :class="user.status === 'active'
                                            ? 'bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30'
                                            : 'bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-300 border border-emerald-500/30'"
                                    >
                                        <svg v-if="updatingUserId === user.id" class="animate-spin w-3.5 h-3.5 text-current" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                        </svg>
                                        <span v-else>{{ user.status === 'active' ? '🔒 Khóa' : '🔓 Mở khóa' }}</span>
                                    </button>
                                    <span v-else class="text-xs text-slate-500 italic">
                                        (Tài khoản hiện tại)
                                    </span>
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
                        <button
                            v-if="users.prev_page_url"
                            type="button"
                            @click="router.get(users.prev_page_url)"
                            class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
                        >
                            Trang trước
                        </button>
                        <button
                            v-if="users.next_page_url"
                            type="button"
                            @click="router.get(users.next_page_url)"
                            class="px-3 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 transition"
                        >
                            Trang kế
                        </button>
                    </div>
                </div>
            </div>

            <!-- SSO Integration Guide Card -->
            <div class="bg-gradient-to-r from-indigo-950/40 via-slate-900/60 to-purple-950/40 border border-indigo-500/20 rounded-2xl p-6">
                <div class="flex items-start gap-4">
                    <div class="p-3 rounded-xl bg-indigo-500/20 text-indigo-400 shrink-0">
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="space-y-2">
                        <h4 class="text-base font-semibold text-white">Tích hợp Single Sign-On cho Client Apps</h4>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Để tích hợp các ứng dụng con (Client Web Apps) với SSO Hub này:
                            khi người dùng chưa xác thực tại Client App, chuyển hướng người dùng đến
                            <code class="px-2 py-0.5 rounded bg-slate-900 text-indigo-300 font-mono text-[11px] border border-slate-800">
                                /login?redirect=https://client.yourdomain.com/auth/callback
                            </code>.
                            Sau khi đăng nhập thành công tại SSO Hub, hệ thống sẽ xác thực phiên và chuyển hướng an toàn trở lại ứng dụng của bạn.
                        </p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>
