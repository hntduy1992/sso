<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import AppLayout from '@/Layouts/AppLayout.vue';

interface LeadershipUser {
    user_id: number;
    name: string;
    avatar?: string | null;
    is_primary?: boolean;
    is_concurrent?: boolean;
}

interface MemberItem {
    id: number;
    user_id: number;
    name: string;
    email: string;
    avatar?: string | null;
    started_at: string;
}

interface DepartmentItem {
    id: number;
    name: string;
    code: string;
    type: 'management_board' | 'specialized_team';
    type_label: string;
    description: string | null;
    is_active: boolean;
    display_order: number;
    members_count: number;
    leadership: {
        director: LeadershipUser | null;
        deputy_directors: LeadershipUser[];
        team_lead: LeadershipUser | null;
        deputy_team_leads: LeadershipUser[];
    };
    members_list: MemberItem[];
}

interface PositionTypeItem {
    id: number;
    code: string;
    name: string;
    level: number;
    applicable_to: string;
}

const props = defineProps<{
    departments: DepartmentItem[];
    positionTypes: PositionTypeItem[];
}>();

// Modal state
const isModalOpen = ref(false);
const editingDepartment = ref<DepartmentItem | null>(null);

const form = useForm({
    name: '',
    code: '',
    type: 'specialized_team' as 'management_board' | 'specialized_team',
    description: '',
    display_order: 0,
    is_active: true,
});

const openCreateModal = () => {
    editingDepartment.value = null;
    form.reset();
    form.clearErrors();
    form.type = 'specialized_team';
    form.display_order = props.departments.length + 1;
    form.is_active = true;
    isModalOpen.value = true;
};

const openEditModal = (dept: DepartmentItem) => {
    editingDepartment.value = dept;
    form.clearErrors();
    form.name = dept.name;
    form.code = dept.code;
    form.type = dept.type;
    form.description = dept.description ?? '';
    form.display_order = dept.display_order;
    form.is_active = dept.is_active;
    isModalOpen.value = true;
};

const closeModal = () => {
    isModalOpen.value = false;
    editingDepartment.value = null;
    form.reset();
};

const submitForm = () => {
    if (editingDepartment.value) {
        form.put(route('admin.departments.update', editingDepartment.value.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    } else {
        form.post(route('admin.departments.store'), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    }
};

const deleteDepartment = (dept: DepartmentItem) => {
    if (confirm(`Bạn có chắc chắn muốn xóa đơn vị "${dept.name}" không?`)) {
        useForm({}).delete(route('admin.departments.destroy', dept.id), {
            preserveScroll: true,
        });
    }
};

// Expand member list state
const expandedDepts = ref<Record<number, boolean>>({});
const toggleMembers = (deptId: number) => {
    expandedDepts.value[deptId] = !expandedDepts.value[deptId];
};
</script>

<template>
    <AppLayout title="Cơ cấu Tổ chức">
        <Head title="Cơ cấu Tổ chức & Nhân sự" />

        <div class="space-y-8 max-w-7xl mx-auto pb-12">
            <!-- Header Section -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-6">
                <div>
                    <div class="flex items-center gap-3">
                        <span class="p-2.5 rounded-xl bg-indigo-500/10 text-indigo-400 border border-indigo-500/20">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                        </span>
                        <div>
                            <h1 class="text-2xl font-bold text-white tracking-tight">Cơ cấu Tổ chức & Nhân sự</h1>
                            <p class="text-sm text-slate-400 mt-0.5">
                                Quản lý Ban Giám đốc, các Tổ chuyên môn, phân bổ vị trí lãnh đạo và kiêm nhiệm
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex items-center gap-3">
                    <button
                        @click="openCreateModal"
                        class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-gradient-to-r from-indigo-500 to-indigo-600 hover:from-indigo-600 hover:to-indigo-700 text-white font-medium text-sm shadow-lg shadow-indigo-500/25 transition-all duration-200"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                        Thêm Đơn vị / Tổ mới
                    </button>
                </div>
            </div>

            <!-- 1. Ban Giám đốc (Management Board) -->
            <div
                v-for="board in departments.filter(d => d.type === 'management_board')"
                :key="board.id"
                class="rounded-2xl border border-amber-500/30 bg-gradient-to-br from-slate-900/90 via-amber-950/20 to-slate-900/90 shadow-2xl overflow-hidden backdrop-blur-sm"
            >
                <div class="p-6 border-b border-amber-500/20 flex flex-col md:flex-row md:items-center justify-between gap-4 bg-amber-500/5">
                    <div class="flex items-center gap-3">
                        <div class="p-2.5 rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-xl font-bold text-white">{{ board.name }}</h2>
                                <span class="px-2 py-0.5 rounded text-xs font-semibold uppercase bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                    {{ board.code }}
                                </span>
                            </div>
                            <p class="text-xs text-amber-200/70 mt-0.5">
                                {{ board.description || 'Ban Lãnh đạo cấp cao của đơn vị, chỉ đạo toàn diện hoạt động' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            @click="openEditModal(board)"
                            class="px-3 py-1.5 rounded-lg text-xs font-medium text-amber-300 hover:text-white hover:bg-amber-500/20 border border-amber-500/30 transition"
                        >
                            Chỉnh sửa
                        </button>
                    </div>
                </div>

                <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Giám đốc -->
                    <div class="p-5 rounded-xl border border-slate-800 bg-slate-900/60 flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-3">
                                <span class="text-xs font-semibold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                    </svg>
                                    Giám đốc Đơn vị
                                </span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/10 text-amber-300 border border-amber-500/20">
                                    Cấp 5
                                </span>
                            </div>

                            <div v-if="board.leadership.director" class="flex items-center gap-3 mt-2">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-300 p-[1px] shadow-md shadow-amber-500/20">
                                    <div class="w-full h-full rounded-xl bg-slate-950 flex items-center justify-center font-bold text-amber-300">
                                        <img
                                            v-if="board.leadership.director.avatar"
                                            :src="board.leadership.director.avatar"
                                            class="w-full h-full rounded-xl object-cover"
                                            alt=""
                                        />
                                        <span v-else>{{ board.leadership.director.name.charAt(0) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <Link
                                        :href="route('admin.users.show', board.leadership.director.user_id)"
                                        class="font-semibold text-white hover:text-amber-400 transition"
                                    >
                                        {{ board.leadership.director.name }}
                                    </Link>
                                    <div class="text-xs text-slate-400">Đang đương nhiệm</div>
                                </div>
                            </div>
                            <div v-else class="text-sm text-slate-500 italic py-3">
                                Chưa bổ nhiệm Giám đốc
                            </div>
                        </div>
                    </div>

                    <!-- Các Phó Giám đốc -->
                    <div class="p-5 rounded-xl border border-slate-800 bg-slate-900/60">
                        <div class="flex items-center justify-between mb-3">
                            <span class="text-xs font-semibold uppercase tracking-wider text-cyan-400 flex items-center gap-1.5">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                                </svg>
                                Phó Giám đốc ({{ board.leadership.deputy_directors.length }})
                            </span>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-cyan-500/10 text-cyan-300 border border-cyan-500/20">
                                Cấp 4
                            </span>
                        </div>

                        <div v-if="board.leadership.deputy_directors.length > 0" class="space-y-3">
                            <div
                                v-for="pgd in board.leadership.deputy_directors"
                                :key="pgd.user_id"
                                class="flex items-center justify-between p-2 rounded-lg bg-slate-950/50 border border-slate-800/80"
                            >
                                <div class="flex items-center gap-3">
                                    <div class="w-9 h-9 rounded-lg bg-slate-800 flex items-center justify-center font-bold text-xs text-cyan-300">
                                        <img
                                            v-if="pgd.avatar"
                                            :src="pgd.avatar"
                                            class="w-full h-full rounded-lg object-cover"
                                            alt=""
                                        />
                                        <span v-else>{{ pgd.name.charAt(0) }}</span>
                                    </div>
                                    <div>
                                        <Link
                                            :href="route('admin.users.show', pgd.user_id)"
                                            class="text-sm font-semibold text-white hover:text-cyan-400 transition"
                                        >
                                            {{ pgd.name }}
                                        </Link>
                                        <div class="text-[11px] text-cyan-300/80">
                                            Phó Giám đốc
                                        </div>
                                    </div>
                                </div>

                                <Link
                                    :href="route('admin.users.show', pgd.user_id)"
                                    class="text-xs text-slate-400 hover:text-white px-2 py-1 rounded hover:bg-slate-800 transition"
                                >
                                    Xem hồ sơ &rarr;
                                </Link>
                            </div>
                        </div>
                        <div v-else class="text-sm text-slate-500 italic py-3">
                            Chưa có Phó Giám đốc được bổ nhiệm
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Các Tổ Chuyên Môn (Specialized Teams) -->
            <div>
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <svg class="w-5 h-5 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Các Tổ Chuyên môn
                        <span class="text-xs px-2 py-0.5 rounded-full bg-slate-800 text-slate-400 border border-slate-700">
                            {{ departments.filter(d => d.type === 'specialized_team').length }} tổ
                        </span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div
                        v-for="team in departments.filter(d => d.type === 'specialized_team')"
                        :key="team.id"
                        class="rounded-2xl border border-slate-800/80 bg-slate-900/60 backdrop-blur-sm p-6 flex flex-col justify-between hover:border-slate-700 transition-all duration-200"
                    >
                        <div>
                            <!-- Team Header -->
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-lg font-bold text-white">{{ team.name }}</h3>
                                        <span class="px-2 py-0.5 rounded text-[11px] font-semibold bg-indigo-500/10 text-indigo-300 border border-indigo-500/20">
                                            {{ team.code }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-1">
                                        {{ team.description || 'Tổ chuyên môn thực hiện nhiệm vụ theo chức năng phân công' }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-1.5 shrink-0">
                                    <button
                                        @click="openEditModal(team)"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                                        title="Chỉnh sửa tổ"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                                        </svg>
                                    </button>
                                    <button
                                        @click="deleteDepartment(team)"
                                        class="p-1.5 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 transition"
                                        title="Xóa tổ"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <!-- Leadership summary -->
                            <div class="mt-5 space-y-3">
                                <!-- Tổ trưởng -->
                                <div class="p-3 rounded-xl border border-slate-800 bg-slate-950/60">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-[11px] font-semibold uppercase text-emerald-400">
                                            Tổ trưởng
                                        </span>
                                        <template v-if="team.leadership.team_lead">
                                            <span
                                                v-if="team.leadership.team_lead.is_concurrent"
                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30"
                                            >
                                                Kiêm nhiệm (Phó GĐ)
                                            </span>
                                            <span
                                                v-else
                                                class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/10 text-emerald-300 border border-emerald-500/20"
                                            >
                                                Chức vụ chính
                                            </span>
                                        </template>
                                    </div>

                                    <div v-if="team.leadership.team_lead" class="flex items-center gap-3">
                                        <div class="w-9 h-9 rounded-lg bg-emerald-500/20 text-emerald-300 flex items-center justify-center font-bold text-xs">
                                            <img
                                                v-if="team.leadership.team_lead.avatar"
                                                :src="team.leadership.team_lead.avatar"
                                                class="w-full h-full rounded-lg object-cover"
                                                alt=""
                                            />
                                            <span v-else>{{ team.leadership.team_lead.name.charAt(0) }}</span>
                                        </div>
                                        <div>
                                            <Link
                                                :href="route('admin.users.show', team.leadership.team_lead.user_id)"
                                                class="text-sm font-semibold text-white hover:text-emerald-300 transition"
                                            >
                                                {{ team.leadership.team_lead.name }}
                                            </Link>
                                            <div class="text-[11px] text-slate-400">Đang giữ chức Tổ trưởng</div>
                                        </div>
                                    </div>
                                    <div v-else class="text-xs text-slate-500 italic">
                                        Chưa có Tổ trưởng
                                    </div>
                                </div>

                                <!-- Tổ phó -->
                                <div class="p-3 rounded-xl border border-slate-800 bg-slate-950/60">
                                    <div class="text-[11px] font-semibold uppercase text-violet-400 mb-2">
                                        Tổ phó ({{ team.leadership.deputy_team_leads.length }})
                                    </div>

                                    <div v-if="team.leadership.deputy_team_leads.length > 0" class="flex flex-wrap gap-2">
                                        <Link
                                            v-for="dtl in team.leadership.deputy_team_leads"
                                            :key="dtl.user_id"
                                            :href="route('admin.users.show', dtl.user_id)"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-xs text-slate-200 border border-slate-700/60 transition"
                                        >
                                            <span>{{ dtl.name }}</span>
                                        </Link>
                                    </div>
                                    <div v-else class="text-xs text-slate-500 italic">
                                        Chưa có Tổ phó
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Footer: Members count & toggle list -->
                        <div class="mt-6 pt-4 border-t border-slate-800 flex items-center justify-between text-xs">
                            <span class="text-slate-400">
                                Tổng số nhân sự: <strong class="text-white">{{ team.members_count }}</strong>
                            </span>

                            <button
                                @click="toggleMembers(team.id)"
                                class="text-indigo-400 hover:text-indigo-300 font-medium transition flex items-center gap-1"
                            >
                                <span>{{ expandedDepts[team.id] ? 'Thu gọn' : 'Xem danh sách tổ viên' }}</span>
                                <svg
                                    class="w-3.5 h-3.5 transition-transform"
                                    :class="{ 'rotate-180': expandedDepts[team.id] }"
                                    fill="none"
                                    viewBox="0 0 24 24"
                                    stroke="currentColor"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg>
                            </button>
                        </div>

                        <!-- Expandable members list -->
                        <div v-if="expandedDepts[team.id]" class="mt-4 pt-4 border-t border-slate-800/80 space-y-2">
                            <div v-if="team.members_list.length > 0" class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
                                <div
                                    v-for="member in team.members_list"
                                    :key="member.id"
                                    class="flex items-center justify-between p-2 rounded-lg bg-slate-950/40 border border-slate-800/60 text-xs"
                                >
                                    <div class="flex items-center gap-2">
                                        <div class="w-6 h-6 rounded-full bg-slate-800 flex items-center justify-center font-bold text-[10px] text-slate-300">
                                            {{ member.name.charAt(0) }}
                                        </div>
                                        <div>
                                            <span class="font-medium text-slate-200">{{ member.name }}</span>
                                            <span class="text-slate-500 ml-1.5">({{ member.email }})</span>
                                        </div>
                                    </div>
                                    <Link
                                        :href="route('admin.users.show', member.user_id)"
                                        class="text-indigo-400 hover:text-indigo-300 text-[11px]"
                                    >
                                        Chi tiết &rarr;
                                    </Link>
                                </div>
                            </div>
                            <div v-else class="text-xs text-slate-500 italic py-2 text-center">
                                Chưa có tổ viên nào trong tổ này
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Create / Edit Department Modal -->
        <div v-if="isModalOpen" class="fixed inset-0 z-50 overflow-y-auto bg-slate-950/80 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-lg w-full p-6 shadow-2xl space-y-5 animate-in fade-in duration-200">
                <div class="flex items-center justify-between pb-4 border-b border-slate-800">
                    <h3 class="text-lg font-bold text-white">
                        {{ editingDepartment ? 'Chỉnh sửa Đơn vị' : 'Thêm Đơn vị / Tổ mới' }}
                    </h3>
                    <button @click="closeModal" class="text-slate-400 hover:text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <form @submit.prevent="submitForm" class="space-y-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Tên Đơn vị / Tổ chuyên môn *
                        </label>
                        <input
                            v-model="form.name"
                            type="text"
                            placeholder="Ví dụ: Tổ Phát triển Phần mềm"
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                        />
                        <div v-if="form.errors.name" class="text-xs text-rose-400 mt-1">{{ form.errors.name }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                                Mã định danh *
                            </label>
                            <input
                                v-model="form.code"
                                type="text"
                                placeholder="Ví dụ: TCM-DEV"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500 uppercase"
                            />
                            <div v-if="form.errors.code" class="text-xs text-rose-400 mt-1">{{ form.errors.code }}</div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                                Loại cơ cấu *
                            </label>
                            <select
                                v-model="form.type"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            >
                                <option value="specialized_team">Tổ chuyên môn</option>
                                <option value="management_board">Ban Giám đốc</option>
                            </select>
                            <div v-if="form.errors.type" class="text-xs text-rose-400 mt-1">{{ form.errors.type }}</div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                            Mô tả chức năng nhiệm vụ
                        </label>
                        <textarea
                            v-model="form.description"
                            rows="3"
                            placeholder="Mô tả chức năng, nhiệm vụ chính của tổ..."
                            class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                        ></textarea>
                        <div v-if="form.errors.description" class="text-xs text-rose-400 mt-1">{{ form.errors.description }}</div>
                    </div>

                    <div class="grid grid-cols-2 gap-4 items-center">
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                                Thứ tự hiển thị
                            </label>
                            <input
                                v-model.number="form.display_order"
                                type="number"
                                min="0"
                                class="w-full px-3.5 py-2.5 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                        </div>

                        <div class="pt-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input
                                    v-model="form.is_active"
                                    type="checkbox"
                                    class="w-4 h-4 rounded text-indigo-600 bg-slate-950 border-slate-800 focus:ring-indigo-500"
                                />
                                <span class="text-sm font-medium text-slate-300">Đang hoạt động</span>
                            </label>
                        </div>
                    </div>

                    <div class="pt-4 border-t border-slate-800 flex justify-end gap-3">
                        <button
                            type="button"
                            @click="closeModal"
                            class="px-4 py-2 rounded-xl text-sm font-medium text-slate-400 hover:text-white hover:bg-slate-800 transition"
                        >
                            Hủy bỏ
                        </button>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="px-5 py-2 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition disabled:opacity-50"
                        >
                            {{ editingDepartment ? 'Lưu thay đổi' : 'Tạo mới' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
