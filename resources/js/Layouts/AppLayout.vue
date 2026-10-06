<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import type { PageProps } from '@/types';

const props = defineProps<{
    title?: string;
}>();

const page = usePage<PageProps>();

// Sidebar collapse state
const sidebarCollapsed = ref(false);
const mobileMenuOpen = ref(false);

onMounted(() => {
    const saved = localStorage.getItem('csm_sidebar_collapsed');
    if (saved !== null) {
        sidebarCollapsed.value = saved === 'true';
    }
});

const toggleSidebar = () => {
    sidebarCollapsed.value = !sidebarCollapsed.value;
    localStorage.setItem('csm_sidebar_collapsed', String(sidebarCollapsed.value));
};

const handleLogout = () => {
    router.post(route('logout'));
};

// Compute breadcrumb path from component name
const breadcrumbs = computed(() => {
    const comp = page.component;
    if (comp === 'Dashboard') {
        return [
            { label: 'Hệ thống CSM', href: route('dashboard') },
            { label: 'Quản lý Người Dùng', href: route('dashboard') },
        ];
    }
    if (comp === 'Admin/Departments/Index') {
        return [
            { label: 'Hệ thống CSM', href: route('dashboard') },
            { label: 'Cơ cấu Tổ chức & HRM', href: route('admin.departments.index') },
        ];
    }
    if (comp === 'Admin/Users/Import') {
        return [
            { label: 'Hệ thống CSM', href: route('dashboard') },
            { label: 'Quản lý Người Dùng', href: route('dashboard') },
            { label: 'Import từ Excel', href: route('admin.users.import.create') },
        ];
    }
    if (comp === 'Admin/Users/Show') {
        return [
            { label: 'Hệ thống CSM', href: route('dashboard') },
            { label: 'Quản lý Người Dùng', href: route('dashboard') },
            { label: props.title || 'Chi tiết Hồ sơ', href: '#' },
        ];
    }
    if (comp === 'Admin/AuditLogs') {
        return [
            { label: 'Hệ thống CSM', href: route('dashboard') },
            { label: 'Nhật ký Kiểm toán', href: route('admin.audit-logs.index') },
        ];
    }
    if (comp === 'Admin/ApplicationAccess/Index') {
        return [
            { label: 'Hệ thống CSM', href: route('dashboard') },
            { label: 'Quyền truy cập ứng dụng', href: route('admin.application-access.index') },
        ];
    }
    if (comp === 'Developer/Clients') {
        return [
            { label: 'Cổng Ứng dụng', href: route('developer.clients.index') },
            { label: 'OAuth Clients', href: route('developer.clients.index') },
        ];
    }
    if (comp.startsWith('Profile/')) {
        return [
            { label: 'Tài khoản', href: route('profile.index') },
            { label: props.title || 'Hồ sơ & Bảo mật', href: '#' },
        ];
    }
    return [
        { label: 'Hệ thống', href: route('dashboard') },
        { label: props.title || 'Trang chủ', href: '#' },
    ];
});
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex selection:bg-indigo-500 selection:text-white font-sans antialiased">
        <Head :title="title ? `${title} - SSO Identity Provider` : 'SSO Identity Provider'" />

        <!-- 1. Left Sidebar (Desktop) -->
        <aside
            :class="[
                'hidden lg:flex flex-col fixed inset-y-0 left-0 z-40 bg-slate-900/95 border-r border-slate-800/80 backdrop-blur-xl transition-all duration-300 ease-in-out',
                sidebarCollapsed ? 'w-20' : 'w-64'
            ]"
        >
            <!-- Sidebar Header & Brand -->
            <div class="h-16 px-4 flex items-center justify-between border-b border-slate-800/80">
                <Link :href="route('dashboard')" class="flex items-center gap-3 overflow-hidden group">
                    <div class="w-10 h-10 shrink-0 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-cyan-400 p-[1px] shadow-lg shadow-indigo-500/20 group-hover:shadow-indigo-500/35 transition-all duration-300">
                        <div class="w-full h-full bg-slate-950 rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 text-indigo-400 group-hover:scale-110 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                    </div>
                    <div v-show="!sidebarCollapsed" class="whitespace-nowrap transition-opacity duration-200">
                        <div class="flex items-center gap-1.5">
                            <span class="font-bold text-sm bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
                                SSO Identity
                            </span>
                            <span class="px-1.5 py-0.2 rounded text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                CSM
                            </span>
                        </div>
                        <div class="text-[10px] text-slate-400">Trung tâm Định danh & HRM</div>
                    </div>
                </Link>
            </div>

            <!-- Sidebar Navigation Links -->
            <div class="flex-1 overflow-y-auto py-5 px-3 space-y-6">
                <!-- Group 1: Identity & HRM Administration (Admins only) -->
                <div v-if="page.props.auth?.user?.role === 'admin'" class="space-y-1">
                    <div v-show="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-300">
                        Quản Trị Hệ Thống (CSM)
                    </div>

                    <!-- Quản lý Users -->
                    <Link
                        :href="route('dashboard')"
                        :title="sidebarCollapsed ? 'Quản lý Người Dùng' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Dashboard'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Quản lý Users</span>
                    </Link>

                    <!-- Cơ cấu Tổ chức & HRM -->
                    <Link
                        :href="route('admin.departments.index')"
                        :title="sidebarCollapsed ? 'Cơ cấu Tổ chức & HRM' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Admin/Departments/Index'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Cơ cấu & HRM</span>
                    </Link>

                    <!-- Quyền truy cập ứng dụng -->
                    <Link
                        :href="route('admin.application-access.index')"
                        :title="sidebarCollapsed ? 'Quyền truy cập ứng dụng' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Admin/ApplicationAccess/Index'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Quyền truy cập ứng dụng</span>
                    </Link>

                    <!-- Audit Logs -->
                    <Link
                        :href="route('admin.audit-logs.index')"
                        :title="sidebarCollapsed ? 'Nhật ký Kiểm toán' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Admin/AuditLogs'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Nhật ký Audit Logs</span>
                    </Link>
                </div>

                <!-- Group 2: Integrations (OAuth Clients) -->
                <div class="space-y-1">
                    <div v-show="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-300">
                        Cổng Tích Hợp
                    </div>

                    <Link
                        :href="route('developer.clients.index')"
                        :title="sidebarCollapsed ? 'OAuth Clients' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Developer/Clients'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Ứng dụng OAuth</span>
                    </Link>
                </div>

                <!-- Group 3: Personal & Security -->
                <div class="space-y-1">
                    <div v-show="!sidebarCollapsed" class="px-3 mb-2 text-[10px] font-bold uppercase tracking-wider text-slate-300">
                        Tài Khoản Cá Nhân
                    </div>

                    <Link
                        :href="route('profile.index')"
                        :title="sidebarCollapsed ? 'Hồ sơ & 2FA' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Profile/Index'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.121 17.804A13.937 13.937 0 0112 16c2.5 0 4.847.655 6.879 1.804M15 10a3 3 0 11-6 0 3 3 0 016 0zm6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Hồ sơ & 2FA</span>
                    </Link>

                    <Link
                        :href="route('profile.sessions')"
                        :title="sidebarCollapsed ? 'Phiên đăng nhập' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Profile/Sessions'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Phiên hoạt động</span>
                    </Link>

                    <Link
                        :href="route('profile.authorized-apps')"
                        :title="sidebarCollapsed ? 'Ứng dụng liên kết' : undefined"
                        :class="[
                            'flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-150 group',
                            $page.component === 'Profile/AuthorizedApps'
                                ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                        ]"
                    >
                        <svg class="w-5 h-5 shrink-0 group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 4a2 2 0 114 0v1a1 1 0 001 1h3a1 1 0 011 1v3a1 1 0 01-1 1h-1a2 2 0 100 4h1a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1v-1a2 2 0 10-4 0v1a1 1 0 01-1 1H7a1 1 0 01-1-1v-3a1 1 0 00-1-1H4a2 2 0 110-4h1a1 1 0 001-1V7a1 1 0 011-1h3a1 1 0 001-1V4z" />
                        </svg>
                        <span v-show="!sidebarCollapsed" class="truncate">Ứng dụng đã duyệt</span>
                    </Link>
                </div>
            </div>

            <!-- Sidebar Footer: Collapse Toggle & User Profile -->
            <div class="p-3 border-t border-slate-800/80 bg-slate-950/40 space-y-2">
                <!-- Toggle collapse button -->
                <button
                    type="button"
                    @click="toggleSidebar"
                    class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl text-xs text-slate-400 hover:text-white hover:bg-slate-800/60 transition"
                    :title="sidebarCollapsed ? 'Mở rộng thanh điều hướng' : 'Thu gọn thanh điều hướng'"
                >
                    <svg
                        class="w-4 h-4 transition-transform duration-200"
                        :class="{ 'rotate-180': sidebarCollapsed }"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 19l-7-7 7-7m8 14l-7-7 7-7" />
                    </svg>
                    <span v-show="!sidebarCollapsed">Thu gọn menu</span>
                </button>

                <!-- Current User Mini Card -->
                <div v-if="page.props.auth?.user" class="flex items-center gap-3 p-2 rounded-xl bg-slate-900/60 border border-slate-800">
                    <Link :href="route('profile.index')" class="flex items-center gap-2.5 overflow-hidden flex-1">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-xs text-white shrink-0">
                            {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                        </div>
                        <div v-show="!sidebarCollapsed" class="overflow-hidden text-left">
                            <div class="text-xs font-semibold text-slate-200 truncate">{{ page.props.auth.user.name }}</div>
                            <div class="text-[10px] text-slate-400 truncate">{{ page.props.auth.user.email }}</div>
                        </div>
                    </Link>
                    <button
                        @click="handleLogout"
                        title="Đăng xuất an toàn"
                        class="text-slate-400 hover:text-rose-400 p-1.5 rounded-lg hover:bg-rose-500/10 transition shrink-0"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </div>
            </div>
        </aside>

        <!-- 2. Mobile Drawer Navigation -->
        <div v-if="mobileMenuOpen" class="fixed inset-0 z-50 lg:hidden flex">
            <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm" @click="mobileMenuOpen = false"></div>
            <div class="relative w-72 bg-slate-900 border-r border-slate-800 p-5 flex flex-col justify-between z-10">
                <div class="space-y-6">
                    <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                        <div class="flex items-center gap-2 font-bold text-white text-base">
                            <div class="w-8 h-8 rounded-lg bg-indigo-600 flex items-center justify-center text-white">
                                SSO
                            </div>
                            <span>CSM Admin</span>
                        </div>
                        <button @click="mobileMenuOpen = false" class="text-slate-400 hover:text-white p-1">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <nav class="space-y-1 text-sm">
                        <template v-if="page.props.auth?.user?.role === 'admin'">
                            <Link :href="route('dashboard')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                                Quản lý Users
                            </Link>
                            <Link :href="route('admin.departments.index')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                                Cơ cấu & HRM
                            </Link>
                            <Link :href="route('admin.application-access.index')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                                Quyền truy cập ứng dụng
                            </Link>
                            <Link :href="route('admin.audit-logs.index')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                                Nhật ký Audit Logs
                            </Link>
                        </template>
                        <Link :href="route('developer.clients.index')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                            Ứng dụng OAuth
                        </Link>
                        <Link :href="route('profile.index')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                            Hồ sơ & 2FA
                        </Link>
                        <Link :href="route('profile.sessions')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                            Phiên hoạt động
                        </Link>
                        <Link :href="route('profile.authorized-apps')" @click="mobileMenuOpen = false" class="block px-3 py-2 rounded-lg text-slate-300 hover:bg-slate-800">
                            Ứng dụng đã duyệt
                        </Link>
                    </nav>
                </div>

                <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                    <span class="text-xs text-slate-400">{{ page.props.auth?.user?.name }}</span>
                    <button @click="handleLogout" class="text-xs text-rose-400 hover:underline">
                        Đăng xuất
                    </button>
                </div>
            </div>
        </div>

        <!-- 3. Main Area Wrapper (Right of Sidebar) -->
        <div
            :class="[
                'flex-1 flex flex-col min-w-0 transition-all duration-300 ease-in-out',
                sidebarCollapsed ? 'lg:pl-20' : 'lg:pl-64'
            ]"
        >
            <!-- Topbar Header -->
            <header class="h-16 border-b border-slate-800/80 bg-slate-900/80 backdrop-blur-xl sticky top-0 z-30 flex items-center justify-between px-4 sm:px-6 lg:px-8">
                <!-- Left: Mobile Menu Toggle & Breadcrumbs -->
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        @click="mobileMenuOpen = true"
                        class="lg:hidden p-2 rounded-xl text-slate-400 hover:text-white hover:bg-slate-800"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <!-- Breadcrumbs -->
                    <nav class="flex items-center gap-2 text-xs">
                        <template v-for="(bc, index) in breadcrumbs" :key="bc.label">
                            <span v-if="index > 0" class="text-slate-600">/</span>
                            <Link
                                v-if="bc.href !== '#'"
                                :href="bc.href"
                                class="text-slate-400 hover:text-slate-200 transition font-medium"
                            >
                                {{ bc.label }}
                            </Link>
                            <span v-else class="text-slate-200 font-semibold truncate max-w-[200px]">
                                {{ bc.label }}
                            </span>
                        </template>
                    </nav>
                </div>

                <!-- Right Topbar: System Pill & Profile -->
                <div class="flex items-center gap-3">
                    <div class="hidden sm:flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs font-medium">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        SSO Provider Trực Tuyến
                    </div>

                    <div v-if="page.props.auth?.user" class="flex items-center gap-3 pl-3 border-l border-slate-800">
                        <Link :href="route('profile.index')" class="flex items-center gap-2.5 group">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-xs text-white ring-2 ring-transparent group-hover:ring-indigo-500/50 transition">
                                {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="hidden md:block text-left">
                                <div class="text-xs font-semibold text-slate-200 group-hover:text-indigo-300 transition leading-tight">
                                    {{ page.props.auth.user.name }}
                                </div>
                                <div class="text-[10px] text-slate-400 uppercase">
                                    {{ page.props.auth.user.role }}
                                </div>
                            </div>
                        </Link>
                    </div>
                </div>
            </header>

            <!-- Flash messages -->
            <div v-if="$page.props.flash?.success || $page.props.flash?.error" class="px-4 sm:px-6 lg:px-8 mt-4 w-full">
                <div
                    v-if="$page.props.flash?.success"
                    class="flex items-center gap-3 p-4 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-300 shadow-lg text-sm"
                >
                    <svg class="w-5 h-5 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>{{ $page.props.flash.success }}</span>
                </div>
                <div
                    v-if="$page.props.flash?.error"
                    class="flex items-center gap-3 p-4 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-300 shadow-lg text-sm"
                >
                    <svg class="w-5 h-5 shrink-0 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <span>{{ $page.props.flash.error }}</span>
                </div>
            </div>

            <!-- Main Content Slot -->
            <main class="flex-1 w-full px-4 sm:px-6 lg:px-8 py-8">
                <slot />
            </main>
        </div>
    </div>
</template>
