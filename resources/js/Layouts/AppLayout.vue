<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';

defineProps<{
    title?: string;
}>();

const page = usePage<PageProps>();
const mobileMenuOpen = ref(false);

const handleLogout = () => {
    router.post('/logout');
};
</script>

<template>
    <div class="min-h-screen bg-slate-950 text-slate-100 flex flex-col selection:bg-indigo-500 selection:text-white font-sans antialiased">
        <Head :title="title ? `${title} - SSO Identity Provider` : 'SSO Identity Provider'" />

        <!-- Navigation Bar -->
        <header class="border-b border-slate-800/80 bg-slate-900/70 backdrop-blur-xl sticky top-0 z-40">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between">
                <!-- Brand & Main Nav Links -->
                <div class="flex items-center gap-8">
                    <Link href="/dashboard" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-indigo-600 via-indigo-500 to-cyan-400 p-[1px] shadow-lg shadow-indigo-500/20 group-hover:shadow-indigo-500/35 transition-all duration-300">
                            <div class="w-full h-full bg-slate-950 rounded-xl flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-400 group-hover:scale-110 transition-transform duration-200" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-base bg-gradient-to-r from-white via-slate-200 to-slate-400 bg-clip-text text-transparent">
                                    SSO Identity
                                </span>
                                <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                    Hub
                                </span>
                            </div>
                        </div>
                    </Link>

                    <!-- Desktop Nav Links -->
                    <nav class="hidden md:flex items-center gap-1">
                        <!-- Admin section (if admin) -->
                        <template v-if="page.props.auth?.user?.role === 'admin'">
                            <Link
                                href="/dashboard"
                                :class="[
                                    'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                                    $page.component === 'Dashboard'
                                        ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                        : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                                ]"
                            >
                                Quản lý Users
                            </Link>
                            <Link
                                href="/admin/audit-logs"
                                :class="[
                                    'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                                    $page.component === 'Admin/AuditLogs'
                                        ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                        : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                                ]"
                            >
                                Audit Logs
                            </Link>
                        </template>

                        <!-- User Portal links -->
                        <Link
                            href="/profile"
                            :class="[
                                'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                                $page.component === 'Profile/Index'
                                    ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Hồ sơ & 2FA
                        </Link>
                        <Link
                            href="/profile/sessions"
                            :class="[
                                'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                                $page.component === 'Profile/Sessions'
                                    ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Phiên đăng nhập
                        </Link>
                        <Link
                            href="/profile/authorized-apps"
                            :class="[
                                'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                                $page.component === 'Profile/AuthorizedApps'
                                    ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            Ứng dụng liên kết
                        </Link>

                        <!-- Developer Portal -->
                        <Link
                            href="/developer/clients"
                            :class="[
                                'px-3 py-2 rounded-lg text-sm font-medium transition-all duration-150',
                                $page.component === 'Developer/Clients'
                                    ? 'bg-indigo-600/20 text-indigo-300 border border-indigo-500/30 shadow-sm shadow-indigo-500/10'
                                    : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60'
                            ]"
                        >
                            OAuth Clients
                        </Link>
                    </nav>
                </div>

                <!-- Right Action / User Profile -->
                <div class="flex items-center gap-3">
                    <div v-if="page.props.auth?.user" class="flex items-center gap-3 pl-3 border-l border-slate-800">
                        <Link href="/profile" class="flex items-center gap-2 group">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-indigo-500 to-purple-600 flex items-center justify-center font-bold text-xs text-white shadow ring-2 ring-transparent group-hover:ring-indigo-500/50 transition">
                                {{ page.props.auth.user.name.charAt(0).toUpperCase() }}
                            </div>
                            <div class="hidden lg:block text-left">
                                <div class="text-xs font-semibold text-slate-200 leading-tight group-hover:text-indigo-300 transition">
                                    {{ page.props.auth.user.name }}
                                </div>
                                <div class="text-[10px] text-slate-400">
                                    {{ page.props.auth.user.email }}
                                </div>
                            </div>
                        </Link>

                        <button
                            @click="handleLogout"
                            title="Đăng xuất an toàn"
                            class="p-2 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition-colors duration-150"
                        >
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </button>
                    </div>

                    <!-- Mobile menu button -->
                    <button
                        @click="mobileMenuOpen = !mobileMenuOpen"
                        class="md:hidden p-2 rounded-lg text-slate-400 hover:bg-slate-800"
                    >
                        <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path v-if="!mobileMenuOpen" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Mobile Nav Menu -->
            <div v-if="mobileMenuOpen" class="md:hidden border-t border-slate-800/80 bg-slate-900/95 px-4 pt-3 pb-4 space-y-1">
                <template v-if="page.props.auth?.user?.role === 'admin'">
                    <Link href="/dashboard" class="block px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800">
                        Quản lý Users
                    </Link>
                    <Link href="/admin/audit-logs" class="block px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800">
                        Audit Logs
                    </Link>
                </template>
                <Link href="/profile" class="block px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800">
                    Hồ sơ & 2FA
                </Link>
                <Link href="/profile/sessions" class="block px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800">
                    Phiên đăng nhập
                </Link>
                <Link href="/profile/authorized-apps" class="block px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800">
                    Ứng dụng liên kết
                </Link>
                <Link href="/developer/clients" class="block px-3 py-2 rounded-md text-sm font-medium text-slate-300 hover:bg-slate-800">
                    OAuth Clients
                </Link>
            </div>
        </header>

        <!-- Flash messages -->
        <div v-if="$page.props.flash?.success || $page.props.flash?.error" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
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
        <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <slot />
        </main>
    </div>
</template>
