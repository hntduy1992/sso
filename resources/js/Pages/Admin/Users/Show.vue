<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatToDisplayDate } from '@/utils/date';

interface UserInfo {
    id: number;
    name: string;
    email: string;
    role: string;
    status: string;
    avatar_url: string | null;
    has_password: boolean;
    mfa_enabled: boolean;
    created_at: string;
    is_deputy_director: boolean;
}

interface ProfileInfo {
    full_name: string | null;
    date_of_birth: string | null;
    gender: string | null;
    phone_number: string | null;
    contact_email: string | null;
    address: string | null;
    bio: string | null;
}

interface SocialAccount {
    provider: string;
    created_at: string;
}

interface PositionItem {
    id: number;
    department_id: number;
    department_name: string;
    department_code: string;
    department_type: string;
    position_type_id: number;
    position_type_name: string;
    position_type_code: string;
    position_level: number;
    is_primary: boolean;
    started_at: string;
    ended_at: string | null;
    is_active: boolean;
    notes: string | null;
}

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
    type: string;
}

interface PositionTypeOption {
    id: number;
    name: string;
    code: string;
    level: number;
    applicable_to: string;
}

const props = defineProps<{
    targetUser: UserInfo;
    profile: ProfileInfo | null;
    socialAccounts: SocialAccount[];
    activePositions: PositionItem[];
    historyPositions: PositionItem[];
    departments: DepartmentOption[];
    positionTypes: PositionTypeOption[];
}>();

// Assign Position Modal
const isAssignModalOpen = ref(false);
const assignForm = useForm({
    department_id: '' as number | '',
    position_type_id: '' as number | '',
    started_at: new Date().toISOString().split('T')[0],
    is_primary: false,
    notes: '',
});

// Active primary position of this user
const activePrimaryPosition = computed(() => {
    return props.activePositions.find(p => p.is_primary && p.is_active);
});

// Helper to check if user already has an active position in a department
const getActivePositionInDept = (deptId: number): PositionItem | undefined => {
    return props.activePositions.find(p => p.department_id === deptId && p.is_active);
};

// Filter position types based on selected department type
const availablePositionTypes = computed(() => {
    if (!assignForm.department_id) {
        return props.positionTypes;
    }
    const dept = props.departments.find(d => d.id === assignForm.department_id);
    if (!dept) return props.positionTypes;

    return props.positionTypes.filter(
        pt => pt.applicable_to === 'both' || pt.applicable_to === dept.type
    );
});

const onDepartmentChange = () => {
    // Reset position type if not valid for chosen dept
    const valid = availablePositionTypes.value.some(pt => pt.id === assignForm.position_type_id);
    if (!valid) {
        assignForm.position_type_id = '';
    }
};

const openAssignModal = () => {
    assignForm.reset();
    assignForm.clearErrors();
    assignForm.started_at = new Date().toISOString().split('T')[0];
    assignForm.is_primary = !activePrimaryPosition.value;
    isAssignModalOpen.value = true;
};

const submitAssign = () => {
    assignForm.post(route('admin.users.positions.assign', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => {
            isAssignModalOpen.value = false;
            assignForm.reset();
        },
    });
};

// Terminate Position Modal
const isTerminateModalOpen = ref(false);
const positionToTerminate = ref<PositionItem | null>(null);
const terminateForm = useForm({
    notes: '',
});

const openTerminateModal = (pos: PositionItem) => {
    positionToTerminate.value = pos;
    terminateForm.reset();
    isTerminateModalOpen.value = true;
};

const submitTerminate = () => {
    if (!positionToTerminate.value) return;

    terminateForm.delete(route('admin.users.positions.terminate', { id: props.targetUser.id, positionId: positionToTerminate.value.id }), {
        preserveScroll: true,
        onSuccess: () => {
            isTerminateModalOpen.value = false;
            positionToTerminate.value = null;
        },
    });
};

// Reset Password Modal
const isResetPasswordModalOpen = ref(false);
const resetPasswordForm = useForm({
    password: '',
    password_confirmation: '',
    reason: '',
});

const openResetPasswordModal = () => {
    resetPasswordForm.reset();
    resetPasswordForm.clearErrors();
    isResetPasswordModalOpen.value = true;
};

const submitResetPassword = () => {
    resetPasswordForm.post(route('admin.users.reset-password', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => {
            isResetPasswordModalOpen.value = false;
            resetPasswordForm.reset();
        },
    });
};

// Reset 2FA Modal
const isResetMfaModalOpen = ref(false);
const resetMfaForm = useForm({
    reason: '',
});

const openResetMfaModal = () => {
    resetMfaForm.reset();
    resetMfaForm.clearErrors();
    isResetMfaModalOpen.value = true;
};

const submitResetMfa = () => {
    resetMfaForm.post(route('admin.users.reset-mfa', props.targetUser.id), {
        preserveScroll: true,
        onSuccess: () => {
            isResetMfaModalOpen.value = false;
            resetMfaForm.reset();
        },
    });
};
</script>

<template>
    <AppLayout :title="`Hồ sơ Nhân sự - ${targetUser.name}`">
        <Head :title="`Hồ sơ Nhân sự - ${targetUser.name}`" />

        <div class="space-y-8 max-w-7xl mx-auto pb-12">
            <!-- Breadcrumbs -->
            <div class="flex items-center gap-2 text-xs text-slate-400">
                <Link :href="route('dashboard')" class="hover:text-slate-200 transition">Quản lý Users</Link>
                <span>/</span>
                <span class="text-slate-200 font-medium">{{ targetUser.name }}</span>
            </div>

            <!-- Header Card -->
            <div class="p-6 md:p-8 rounded-2xl border border-slate-800 bg-slate-900/80 backdrop-blur-sm shadow-xl flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div class="flex items-center gap-5">
                    <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-indigo-500 via-purple-500 to-pink-500 p-[2px] shadow-lg shadow-indigo-500/20">
                        <div class="w-full h-full rounded-2xl bg-slate-950 flex items-center justify-center font-bold text-2xl text-white overflow-hidden">
                            <img
                                v-if="targetUser.avatar_url"
                                :src="targetUser.avatar_url"
                                class="w-full h-full object-cover"
                                alt=""
                            />
                            <span v-else>{{ targetUser.name.charAt(0).toUpperCase() }}</span>
                        </div>
                    </div>

                    <div>
                        <div class="flex flex-wrap items-center gap-3">
                            <h1 class="text-2xl font-bold text-white">{{ profile?.full_name || targetUser.name }}</h1>
                            <span
                                :class="[
                                    'px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider border',
                                    targetUser.status === 'active'
                                        ? 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20'
                                        : 'bg-rose-500/10 text-rose-300 border-rose-500/20'
                                ]"
                            >
                                {{ targetUser.status === 'active' ? 'Hoạt động' : 'Tạm khóa' }}
                            </span>
                            <span
                                v-if="targetUser.role === 'admin'"
                                class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20 uppercase"
                            >
                                Quản trị viên
                            </span>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400 mt-2">
                            <span>Email: <strong class="text-slate-200">{{ targetUser.email }}</strong></span>
                            <span>&bull;</span>
                            <span>Mã ID: <strong class="text-slate-200">#{{ targetUser.id }}</strong></span>
                            <span>&bull;</span>
                            <span>Ngày tạo: <strong class="text-slate-200">{{ targetUser.created_at }}</strong></span>
                            <span>&bull;</span>
                            <span class="flex items-center gap-1">
                                2FA:
                                <strong :class="targetUser.mfa_enabled ? 'text-emerald-400' : 'text-slate-400'">
                                    {{ targetUser.mfa_enabled ? 'Đã bật' : 'Chưa bật' }}
                                </strong>
                            </span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        @click="openAssignModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-sm shadow-lg shadow-indigo-600/30 transition"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Bổ nhiệm chức vụ
                    </button>

                    <button
                        @click="openResetPasswordModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-sm font-medium transition"
                    >
                        <svg class="w-4 h-4 text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                        Đặt lại mật khẩu
                    </button>

                    <button
                        v-if="targetUser.mfa_enabled"
                        @click="openResetMfaModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/30 text-sm font-medium transition shadow-sm"
                        title="Hủy kích hoạt xác thực 2 bước cho tài khoản này"
                    >
                        <svg class="w-4 h-4 text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Xóa 2FA
                    </button>
                </div>
            </div>

            <!-- Grid: 2 Columns (Left: HRM Profile & Social, Right: Positions & Timeline) -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Left Column: Personal Profile & Connected Accounts (1 Col) -->
                <div class="space-y-6">
                    <!-- Personal Profile Card -->
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                        <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                            Hồ sơ Nhân sự Cá nhân
                        </h2>

                        <div class="space-y-3 text-sm">
                            <div>
                                <span class="text-xs text-slate-400">Họ và tên đầy đủ</span>
                                <div class="font-medium text-slate-200">{{ profile?.full_name || 'Chưa cập nhật' }}</div>
                            </div>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <span class="text-xs text-slate-400">Ngày sinh</span>
                                    <div class="font-medium text-slate-200">{{ formatToDisplayDate(profile?.date_of_birth) || 'Chưa cập nhật' }}</div>
                                </div>
                                <div>
                                    <span class="text-xs text-slate-400">Giới tính</span>
                                    <div class="font-medium text-slate-200">
                                        {{ profile?.gender === 'male' ? 'Nam' : profile?.gender === 'female' ? 'Nữ' : profile?.gender === 'other' ? 'Khác' : 'Chưa cập nhật' }}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Số điện thoại</span>
                                <div class="font-medium text-slate-200">{{ profile?.phone_number || 'Chưa cập nhật' }}</div>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Email liên hệ phụ</span>
                                <div class="font-medium text-slate-200">{{ profile?.contact_email || 'Chưa cập nhật' }}</div>
                            </div>
                            <div>
                                <span class="text-xs text-slate-400">Địa chỉ</span>
                                <div class="font-medium text-slate-200">{{ profile?.address || 'Chưa cập nhật' }}</div>
                            </div>
                            <div v-if="profile?.bio">
                                <span class="text-xs text-slate-400">Ghi chú / Tiểu sử</span>
                                <div class="text-xs text-slate-300 mt-1 italic">{{ profile.bio }}</div>
                            </div>
                        </div>
                    </div>

                    <!-- Linked Social Accounts (Chỉ hiển thị khi có liên kết) -->
                    <div v-if="socialAccounts && socialAccounts.length > 0" class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-4">
                        <h2 class="text-base font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                            </svg>
                            Tài khoản Liên kết (SSO)
                        </h2>

                        <div class="space-y-2">
                            <div
                                v-for="sa in socialAccounts"
                                :key="sa.provider"
                                class="flex items-center justify-between p-3 rounded-xl bg-slate-950/60 border border-slate-800 text-xs"
                            >
                                <span class="font-semibold uppercase text-slate-200">{{ sa.provider }}</span>
                                <span class="text-slate-400">Liên kết: {{ sa.created_at }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Active Positions & History Timeline (2 Cols) -->
                <div class="lg:col-span-2 space-y-8">
                    <!-- 1. Active Positions Section -->
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-5">
                        <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                            <div>
                                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                                    <svg class="w-5 h-5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                    </svg>
                                    Chức vụ Đang giữ & Kiêm nhiệm
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-emerald-500/10 text-emerald-300 border border-emerald-500/20">
                                        {{ activePositions.length }}
                                    </span>
                                </h2>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Mỗi nhân sự có tối đa 01 chức vụ chính; các chức vụ còn lại là kiêm nhiệm (ví dụ: Phó Giám đốc kiêm Tổ trưởng)
                                </p>
                            </div>
                        </div>

                        <div v-if="activePositions.length > 0" class="space-y-4">
                            <div
                                v-for="pos in activePositions"
                                :key="pos.id"
                                class="p-5 rounded-xl border bg-slate-950/70 flex flex-col md:flex-row md:items-center justify-between gap-4 transition"
                                :class="[
                                    pos.is_primary
                                        ? 'border-indigo-500/40 shadow-lg shadow-indigo-500/5'
                                        : 'border-amber-500/30 shadow-lg shadow-amber-500/5'
                                ]"
                            >
                                <div class="space-y-2">
                                    <div class="flex flex-wrap items-center gap-2.5">
                                        <h3 class="text-base font-bold text-white">{{ pos.position_type_name }}</h3>
                                        <span class="text-slate-400">&mdash;</span>
                                        <span class="text-sm font-semibold text-slate-200">{{ pos.department_name }}</span>

                                        <!-- Badge Primary vs Concurrent -->
                                        <span
                                            v-if="pos.is_primary"
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30"
                                        >
                                            Chức vụ chính
                                        </span>
                                        <span
                                            v-else
                                            class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30"
                                        >
                                            Kiêm nhiệm
                                        </span>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                                        <span>Đơn vị: <strong class="text-slate-300">[{{ pos.department_code }}] {{ pos.department_type === 'management_board' ? 'Ban Giám đốc' : 'Tổ chuyên môn' }}</strong></span>
                                        <span>&bull;</span>
                                        <span>Ngày bổ nhiệm: <strong class="text-slate-300">{{ formatToDisplayDate(pos.started_at) }}</strong></span>
                                        <span v-if="pos.notes">&bull;</span>
                                        <span v-if="pos.notes" class="italic text-slate-400">"{{ pos.notes }}"</span>
                                    </div>
                                </div>

                                <div class="shrink-0">
                                    <button
                                        @click="openTerminateModal(pos)"
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-semibold text-rose-300 hover:text-white hover:bg-rose-600/30 border border-rose-500/30 transition"
                                    >
                                        Kết thúc nhiệm kỳ
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-500 italic py-6 text-center">
                            Nhân sự này hiện chưa được bổ nhiệm chức vụ nào
                        </div>
                    </div>

                    <!-- 2. Position History Timeline -->
                    <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-6 space-y-5">
                        <h2 class="text-lg font-bold text-white flex items-center gap-2 border-b border-slate-800 pb-3">
                            <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Lịch sử Nhiệm kỳ & Công tác
                        </h2>

                        <div v-if="historyPositions.length > 0" class="space-y-4">
                            <div
                                v-for="pos in historyPositions"
                                :key="pos.id"
                                class="relative pl-6 pb-4 border-l-2 border-slate-800 last:border-transparent last:pb-0"
                            >
                                <div class="absolute -left-[7px] top-0 w-3 h-3 rounded-full bg-slate-700 border-2 border-slate-950"></div>

                                <div class="p-4 rounded-xl bg-slate-950/50 border border-slate-800/80 text-sm space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-slate-200">
                                                {{ pos.position_type_name }} &mdash; {{ pos.department_name }}
                                            </span>
                                            <span
                                                :class="[
                                                    'text-[10px] px-2 py-0.5 rounded font-semibold uppercase',
                                                    pos.is_primary ? 'bg-indigo-500/10 text-indigo-300 border border-indigo-500/20' : 'bg-amber-500/10 text-amber-300 border border-amber-500/20'
                                                ]"
                                            >
                                                {{ pos.is_primary ? 'Chính' : 'Kiêm nhiệm' }}
                                            </span>
                                        </div>
                                        <span class="text-xs px-2 py-0.5 rounded bg-slate-800 text-slate-400">
                                            Đã kết thúc
                                        </span>
                                    </div>
                                    <div class="text-xs text-slate-400 flex items-center gap-3">
                                        <span>Từ: {{ formatToDisplayDate(pos.started_at) }}</span>
                                        <span>&rarr;</span>
                                        <span>Đến: {{ formatToDisplayDate(pos.ended_at) }}</span>
                                    </div>
                                    <div v-if="pos.notes" class="text-xs text-slate-500 italic pt-1">
                                        Ghi chú: {{ pos.notes }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-500 italic py-4 text-center">
                            Chưa có dữ liệu lịch sử chức vụ trước đây
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 1. Assign Position Modal -->
        <div v-if="isAssignModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in duration-200">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white">Bổ nhiệm Chức vụ & Tổ chức</h3>
                    <button @click="isAssignModalOpen = false" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitAssign" class="space-y-4">
                    <!-- Department Select -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Phòng ban / Tổ chuyên môn *
                        </label>
                        <select
                            v-model="assignForm.department_id"
                            @change="onDepartmentChange"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                        >
                            <option value="" disabled>-- Chọn đơn vị --</option>
                            <option
                                v-for="dept in departments"
                                :key="dept.id"
                                :value="dept.id"
                                :disabled="!!getActivePositionInDept(dept.id)"
                            >
                                {{ dept.name }} ({{ dept.type === 'management_board' ? 'Ban Giám đốc' : 'Tổ chuyên môn' }}){{ getActivePositionInDept(dept.id) ? ` - [Đang giữ: ${getActivePositionInDept(dept.id)?.position_type_name}]` : '' }}
                            </option>
                        </select>
                        <p v-if="assignForm.department_id && getActivePositionInDept(Number(assignForm.department_id))" class="text-xs text-rose-400 mt-1.5">
                            Nhân sự hiện đang giữ chức vụ <strong>{{ getActivePositionInDept(Number(assignForm.department_id))?.position_type_name }}</strong> tại đơn vị này. Mỗi đơn vị người dùng chỉ được đảm nhiệm 1 chức vụ.
                        </p>
                        <div v-if="assignForm.errors.department_id" class="text-xs text-rose-400 mt-1">{{ assignForm.errors.department_id }}</div>
                    </div>

                    <!-- Position Type Select -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Chức danh bổ nhiệm *
                        </label>
                        <select
                            v-model="assignForm.position_type_id"
                            :disabled="!assignForm.department_id || !!getActivePositionInDept(Number(assignForm.department_id))"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500 disabled:opacity-50"
                        >
                            <option value="" disabled>-- Chọn chức danh --</option>
                            <option
                                v-for="pt in availablePositionTypes"
                                :key="pt.id"
                                :value="pt.id"
                            >
                                {{ pt.name }} (Cấp {{ pt.level }})
                            </option>
                        </select>
                        <div v-if="assignForm.errors.position_type_id" class="text-xs text-rose-400 mt-1">{{ assignForm.errors.position_type_id }}</div>
                    </div>

                    <!-- Started At Date -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Ngày bắt đầu nhiệm kỳ *
                        </label>
                        <DateInput
                            v-model="assignForm.started_at"
                            :has-error="!!assignForm.errors.started_at"
                            required
                        />
                        <div v-if="assignForm.errors.started_at" class="text-xs text-rose-400 mt-1">{{ assignForm.errors.started_at }}</div>
                    </div>

                    <!-- Position Nature (Chính vs Kiêm nhiệm) -->
                    <div v-if="activePrimaryPosition" class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/20 space-y-1">
                        <div class="flex items-center gap-2 text-amber-300 font-semibold text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                Kiêm nhiệm
                            </span>
                            <span>Tính chất: Chức vụ kiêm nhiệm</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Nhân sự hiện đang giữ chức vụ chính là <strong class="text-amber-200">{{ activePrimaryPosition.position_type_name }}</strong> tại <strong class="text-amber-200">{{ activePrimaryPosition.department_name }}</strong>. Chức vụ mới bổ nhiệm tại đơn vị này sẽ là chức vụ kiêm nhiệm.
                        </p>
                    </div>

                    <div v-else class="p-4 rounded-xl bg-indigo-500/10 border border-indigo-500/20 space-y-1">
                        <div class="flex items-center gap-2 text-indigo-300 font-semibold text-sm">
                            <span class="px-2 py-0.5 rounded-full text-xs font-bold uppercase bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">
                                Chức vụ chính
                            </span>
                            <span>Tính chất: Chức vụ chính</span>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed">
                            Nhân sự chưa có chức vụ chính nào đang đương nhiệm. Chức vụ mới được bổ nhiệm này sẽ là chức vụ chính.
                        </p>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Ghi chú / Quyết định bổ nhiệm
                        </label>
                        <textarea
                            v-model="assignForm.notes"
                            rows="2"
                            placeholder="Số quyết định, lý do bổ nhiệm..."
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                        ></textarea>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="isAssignModalOpen = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800 transition"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="submit"
                            :disabled="assignForm.processing || (!!assignForm.department_id && !!getActivePositionInDept(Number(assignForm.department_id)))"
                            class="px-5 py-2 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition disabled:opacity-50"
                        >
                            Xác nhận bổ nhiệm
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 2. Terminate Position Modal -->
        <div v-if="isTerminateModalOpen && positionToTerminate" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-rose-400">Kết thúc nhiệm kỳ chức vụ</h3>
                    <button @click="isTerminateModalOpen = false" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="text-sm text-slate-300">
                    Bạn có chắc chắn muốn kết thúc nhiệm kỳ:
                    <div class="p-3 my-3 rounded-xl bg-slate-950 border border-slate-800 text-xs space-y-1">
                        <div>Chức danh: <strong class="text-white">{{ positionToTerminate.position_type_name }}</strong></div>
                        <div>Đơn vị: <strong class="text-white">{{ positionToTerminate.department_name }}</strong></div>
                        <div>Bắt đầu: <strong class="text-white">{{ formatToDisplayDate(positionToTerminate.started_at) }}</strong></div>
                    </div>
                </div>

                <form @submit.prevent="submitTerminate" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Ghi chú / Lý do kết thúc nhiệm kỳ
                        </label>
                        <input
                            v-model="terminateForm.notes"
                            type="text"
                            placeholder="Hết nhiệm kỳ, chuyển công tác..."
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-rose-500"
                        />
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="isTerminateModalOpen = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            :disabled="terminateForm.processing"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-rose-600 hover:bg-rose-500 text-white transition disabled:opacity-50"
                        >
                            Xác nhận kết thúc
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- 3. Reset Password Modal -->
        <div v-if="isResetPasswordModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-5">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <h3 class="text-base font-bold text-amber-400 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Đặt lại mật khẩu người dùng
                    </h3>
                    <button @click="isResetPasswordModalOpen = false" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-3 rounded-xl bg-amber-500/10 border border-amber-500/20 text-xs text-amber-300">
                    Cảnh báo: Hành động này sẽ cập nhật mật khẩu mới và thu hồi ngay lập tức toàn bộ phiên đăng nhập cũng như OAuth tokens của nhân sự này trên tất cả thiết bị.
                </div>

                <form @submit.prevent="submitResetPassword" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Mật khẩu mới *
                        </label>
                        <input
                            v-model="resetPasswordForm.password"
                            type="password"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-amber-500"
                        />
                        <div v-if="resetPasswordForm.errors.password" class="text-xs text-rose-400 mt-1">{{ resetPasswordForm.errors.password }}</div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Xác nhận mật khẩu mới *
                        </label>
                        <input
                            v-model="resetPasswordForm.password_confirmation"
                            type="password"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-amber-500"
                        />
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Lý do đặt lại mật khẩu * (Kiểm toán)
                        </label>
                        <input
                            v-model="resetPasswordForm.reason"
                            type="text"
                            placeholder="Ví dụ: Người dùng yêu cầu qua hotline do quên mật khẩu"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-amber-500"
                        />
                        <div v-if="resetPasswordForm.errors.reason" class="text-xs text-rose-400 mt-1">{{ resetPasswordForm.errors.reason }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="isResetPasswordModalOpen = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white"
                        >
                            Hủy
                        </button>
                        <button
                            type="submit"
                            :disabled="resetPasswordForm.processing"
                            class="px-4 py-2 rounded-xl text-sm font-semibold bg-amber-600 hover:bg-amber-500 text-white transition disabled:opacity-50"
                        >
                            Cập nhật & Đăng xuất
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Reset 2FA Modal -->
        <div v-if="isResetMfaModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in duration-200">
                <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                    <div class="flex items-center gap-3">
                        <div class="p-2 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Xóa Xác Thực 2 Bước (2FA)</h3>
                            <p class="text-xs text-slate-400">Tài khoản: {{ targetUser.name }} ({{ targetUser.email }})</p>
                        </div>
                    </div>
                    <button @click="isResetMfaModalOpen = false" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="p-3.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-xs text-rose-300 space-y-1.5">
                    <p class="font-semibold flex items-center gap-1.5">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Lưu ý quan trọng
                    </p>
                    <p>
                        Thao tác này sẽ hủy liên kết TOTP (Google Authenticator) và xóa toàn bộ mã phục hồi dự phòng của người dùng. Sau khi xóa, người dùng có thể đăng nhập bình thường chỉ bằng mật khẩu tài khoản.
                    </p>
                </div>

                <form @submit.prevent="submitResetMfa" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Lý do xóa 2FA (Tùy chọn - Lưu vết kiểm toán)
                        </label>
                        <input
                            v-model="resetMfaForm.reason"
                            type="text"
                            placeholder="Ví dụ: Người dùng mất điện thoại, cài lại máy..."
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-rose-500"
                        />
                        <div v-if="resetMfaForm.errors.reason" class="text-xs text-rose-400 mt-1">{{ resetMfaForm.errors.reason }}</div>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="isResetMfaModalOpen = false"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="submit"
                            :disabled="resetMfaForm.processing"
                            class="px-5 py-2 rounded-xl text-sm font-semibold bg-rose-600 hover:bg-rose-500 text-white shadow-lg shadow-rose-600/30 transition disabled:opacity-50"
                        >
                            {{ resetMfaForm.processing ? 'Đang xử lý...' : 'Xác nhận Xóa 2FA' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
