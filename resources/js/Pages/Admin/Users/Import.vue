<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useHttp } from '@inertiajs/vue3';
import { route } from 'ziggy-js';
import * as XLSX from 'xlsx';
import AppLayout from '@/Layouts/AppLayout.vue';
import DateInput from '@/Components/DateInput.vue';
import {
    IMPORT_FIELDS,
    TEMPLATE_HEADERS,
    createEmptyRow,
    findDuplicateEmails,
    normalizeDate,
    normalizeGender,
    resolveHeader,
    validateRow,
    type ImportField,
    type ImportRow,
} from '@/composables/useUserImportValidation';

interface DepartmentOption {
    id: number;
    name: string;
    code: string;
}

interface ImportResult {
    key: string;
    status: 'valid' | 'invalid' | 'created';
    errors: Record<string, string>;
    user_id: number | null;
}

const props = defineProps<{
    departments: DepartmentOption[];
    maxRowsPerRequest: number;
}>();

const CHUNK_SIZE = Math.min(100, props.maxRowsPerRequest);

const departmentId = ref<number | ''>('');
const defaultPassword = ref('');
const showPassword = ref(false);
const fileName = ref('');
const rows = ref<ImportRow[]>([]);
const isDragging = ref(false);
const parseError = ref('');
const filter = ref<'all' | 'valid' | 'invalid' | 'created'>('all');

const isRunning = ref(false);
const progress = ref({ done: 0, total: 0, label: '' });
const runMessage = ref('');

const http = useHttp<{ password: string; department_id: number | ''; rows: Record<string, string>[] }, { results: ImportResult[] }>({
    password: '',
    department_id: '',
    rows: [],
});

const passwordError = computed(() => {
    const value = defaultPassword.value;
    if (!value) return 'Vui lòng nhập mật khẩu mặc định.';
    if (value.length < 8) return 'Mật khẩu phải có ít nhất 8 ký tự.';
    if (!/[A-Za-z]/.test(value)) return 'Mật khẩu phải chứa ít nhất một chữ cái.';
    if (!/\d/.test(value)) return 'Mật khẩu phải chứa ít nhất một chữ số.';
    return '';
});

const counts = computed(() => ({
    total: rows.value.length,
    valid: rows.value.filter((r) => r.status === 'valid').length,
    invalid: rows.value.filter((r) => r.status === 'invalid').length,
    created: rows.value.filter((r) => r.status === 'created').length,
    pending: rows.value.filter((r) => r.status === 'pending').length,
}));

const visibleRows = computed(() => (filter.value === 'all' ? rows.value : rows.value.filter((r) => r.status === filter.value)));
const importableCount = computed(() => rows.value.filter((r) => r.status !== 'created' && r.status !== 'invalid').length);
const progressPercent = computed(() => (progress.value.total === 0 ? 0 : Math.round((progress.value.done / progress.value.total) * 100)));

// ---- Client-side validation -------------------------------------------------
const revalidateAll = () => {
    const duplicates = findDuplicateEmails(rows.value);

    for (const row of rows.value) {
        if (row.status === 'created') continue;

        const errors = validateRow(row);
        if (!errors.email && duplicates.has(row.key)) {
            errors.email = 'Email bị trùng với một dòng khác trong danh sách.';
        }

        row.errors = errors;
        row.status = Object.keys(errors).length > 0 ? 'invalid' : 'pending';
    }
};

const onCellEdit = (row: ImportRow) => {
    if (row.status === 'created') return;
    revalidateAll();
};

const removeRow = (key: string) => {
    rows.value = rows.value.filter((r) => r.key !== key);
    revalidateAll();
};

const addRow = () => {
    rows.value.push(createEmptyRow());
    revalidateAll();
};

const clearAll = () => {
    if (rows.value.length > 0 && !confirm('Xoá toàn bộ danh sách đã đọc?')) return;
    rows.value = [];
    fileName.value = '';
    runMessage.value = '';
    progress.value = { done: 0, total: 0, label: '' };
};

// ---- File parsing (SheetJS) -------------------------------------------------
const parseFile = async (file: File) => {
    parseError.value = '';
    runMessage.value = '';

    try {
        const buffer = await file.arrayBuffer();
        const workbook = XLSX.read(buffer, { type: 'array', cellDates: false });
        const sheet = workbook.Sheets[workbook.SheetNames[0]];
        const matrix = XLSX.utils.sheet_to_json<unknown[]>(sheet, { header: 1, raw: false, defval: '', blankrows: false });
        const rawMatrix = XLSX.utils.sheet_to_json<unknown[]>(sheet, { header: 1, raw: true, defval: '', blankrows: false });

        if (matrix.length < 2) {
            parseError.value = 'File không có dữ liệu (cần dòng tiêu đề và ít nhất một dòng dữ liệu).';
            return;
        }

        const columnMap = new Map<number, ImportField>();
        matrix[0].forEach((header, index) => {
            const field = resolveHeader(header);
            if (field && ![...columnMap.values()].includes(field)) columnMap.set(index, field);
        });

        if (![...columnMap.values()].includes('email')) {
            parseError.value = 'Không tìm thấy cột "Email" trong dòng tiêu đề. Vui lòng dùng file mẫu.';
            return;
        }

        const parsed: ImportRow[] = matrix.slice(1).map((cells, rowIndex) => {
            const row = createEmptyRow();
            columnMap.forEach((field, index) => {
                row[field] = String(cells[index] ?? '').trim();
            });
            row.email = row.email.toLowerCase();
            row.gender = normalizeGender(row.gender);

            const dobIndex = [...columnMap.entries()].find(([, field]) => field === 'date_of_birth')?.[0];
            const rawDob = dobIndex === undefined ? undefined : rawMatrix[rowIndex + 1]?.[dobIndex];
            if (typeof rawDob === 'number') {
                const parts = XLSX.SSF.parse_date_code(rawDob);
                row.date_of_birth = parts
                    ? `${parts.y}-${String(parts.m).padStart(2, '0')}-${String(parts.d).padStart(2, '0')}`
                    : String(rawDob);
            } else {
                row.date_of_birth = normalizeDate(String(rawDob ?? row.date_of_birth).trim());
            }
            if (!row.name && row.email) row.name = row.email.split('@')[0];
            return row;
        });

        fileName.value = file.name;
        rows.value = parsed;
        filter.value = 'all';
        revalidateAll();
    } catch {
        parseError.value = 'Không đọc được file. Vui lòng kiểm tra định dạng .xlsx / .xls / .csv.';
    }
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (file) parseFile(file);
    input.value = '';
};

const onDrop = (event: DragEvent) => {
    isDragging.value = false;
    const file = event.dataTransfer?.files?.[0];
    if (file) parseFile(file);
};

const downloadTemplate = () => {
    const headers = IMPORT_FIELDS.map((f) => TEMPLATE_HEADERS[f]);
    const sample = ['nguyenvana', 'nguyenvana@example.com', 'Nguyễn Văn A', '0901234567', '', 'Nam', '25/12/1990', 'Hà Nội'];
    const sheet = XLSX.utils.aoa_to_sheet([headers, sample]);
    sheet['!cols'] = headers.map(() => ({ wch: 26 }));
    const workbook = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(workbook, sheet, 'Users');
    XLSX.writeFile(workbook, 'mau-import-nguoi-dung.xlsx');
};

// ---- Server interaction (chunked) -------------------------------------------
const toPayload = (row: ImportRow): Record<string, string> => ({
    key: row.key,
    ...Object.fromEntries(IMPORT_FIELDS.map((f) => [f, row[f]])),
});

const applyResults = (results: ImportResult[]) => {
    const byKey = new Map(results.map((r) => [r.key, r]));
    for (const row of rows.value) {
        const result = byKey.get(row.key);
        if (!result) continue;
        row.status = result.status;
        row.errors = result.errors;
        row.user_id = result.user_id;
    }
};

const processInChunks = async (targets: ImportRow[], url: string, label: string) => {
    isRunning.value = true;
    runMessage.value = '';
    progress.value = { done: 0, total: targets.length, label };

    try {
        for (let i = 0; i < targets.length; i += CHUNK_SIZE) {
            const chunk = targets.slice(i, i + CHUNK_SIZE);
            http.password = defaultPassword.value;
            http.department_id = departmentId.value;
            http.rows = chunk.map(toPayload);

            const response = await http.post(url);
            if (!response) {
                // 422 on the envelope (e.g. weak password) — errors are on `http.errors`.
                runMessage.value = Object.values(http.errors)[0] ?? 'Dữ liệu gửi lên không hợp lệ.';
                return false;
            }

            applyResults(response.results);
            progress.value.done += chunk.length;
        }
        return true;
    } catch {
        runMessage.value = 'Có lỗi kết nối hoặc máy chủ. Các dòng đã xử lý được giữ nguyên, bạn có thể thử lại.';
        return false;
    } finally {
        isRunning.value = false;
    }
};

const canSubmit = computed(() => !isRunning.value && !passwordError.value && importableCount.value > 0 && counts.value.invalid === 0);
const canServerCheck = computed(() => !isRunning.value && counts.value.total > 0 && counts.value.invalid === 0 && counts.value.created < counts.value.total);

const serverCheck = async () => {
    const targets = rows.value.filter((r) => r.status !== 'created');
    const ok = await processInChunks(targets, route('admin.users.import.validate'), 'Đang kiểm tra trên máy chủ...');
    if (ok) {
        runMessage.value = counts.value.invalid > 0
            ? `Máy chủ phát hiện ${counts.value.invalid} dòng lỗi. Di chuột vào biểu tượng ❗ để xem chi tiết.`
            : 'Tất cả dòng hợp lệ. Sẵn sàng import.';
    }
};

const runImport = async () => {
    const targets = rows.value.filter((r) => r.status !== 'created' && r.status !== 'invalid');
    if (targets.length === 0) return;
    if (!confirm(`Tạo ${targets.length} tài khoản mới${departmentId.value ? ' và gán vào đơn vị đã chọn' : ''}?`)) return;

    const ok = await processInChunks(targets, route('admin.users.import.store'), 'Đang tạo tài khoản...');
    if (ok) {
        runMessage.value = counts.value.invalid > 0
            ? `Đã tạo ${counts.value.created} tài khoản. Còn ${counts.value.invalid} dòng lỗi — sửa lại rồi bấm Import để tiếp tục.`
            : `Hoàn tất: đã tạo ${counts.value.created} tài khoản.`;
    }
};

const errorSummary = (row: ImportRow): string => Object.values(row.errors).join('\n');
const cellHasError = (row: ImportRow, field: string): boolean => Boolean(row.errors[field]);

const textFields: { field: ImportField; label: string; width: string; type?: string }[] = [
    { field: 'name', label: 'Tên đăng nhập *', width: 'w-36' },
    { field: 'email', label: 'Email *', width: 'w-52' },
    { field: 'full_name', label: 'Họ và tên', width: 'w-44' },
    { field: 'phone_number', label: 'Số điện thoại', width: 'w-32' },
    { field: 'contact_email', label: 'Email liên hệ', width: 'w-48' },
];
</script>

<template>
    <AppLayout title="Import Người Dùng từ Excel">
        <div class="space-y-6 max-w-full">
            <!-- Header -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-5">
                <div>
                    <h1 class="text-2xl font-bold text-white tracking-tight">Import Người Dùng từ Excel</h1>
                    <p class="text-xs text-slate-400 mt-1">
                        Đọc file trên trình duyệt, kiểm tra từng dòng, chỉnh sửa trực tiếp rồi mới tạo tài khoản theo từng đợt.
                    </p>
                </div>
                <Link
                    :href="route('dashboard')"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition"
                >
                    &larr; Quay lại danh sách
                </Link>
            </div>

            <!-- Config -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="bg-slate-900/70 border border-slate-800/80 rounded-2xl p-5 space-y-4">
                    <div class="text-xs font-bold uppercase tracking-wider text-indigo-400">1. Cấu hình</div>

                    <div>
                        <label for="import-department" class="block text-xs font-semibold text-slate-300 mb-1">Đơn vị (tuỳ chọn)</label>
                        <select
                            id="import-department"
                            v-model="departmentId"
                            :disabled="isRunning"
                            class="w-full px-3 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                        >
                            <option value="">-- Không gán đơn vị --</option>
                            <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }} ({{ dept.code }})</option>
                        </select>
                        <p class="text-[11px] text-slate-500 mt-1">Nếu chọn, mọi tài khoản mới sẽ là Tổ viên của đơn vị này. Vai trò & chức vụ có thể gán sau.</p>
                    </div>

                    <div>
                        <label for="import-password" class="block text-xs font-semibold text-slate-300 mb-1">Mật khẩu mặc định *</label>
                        <div class="relative">
                            <input
                                id="import-password"
                                v-model="defaultPassword"
                                :type="showPassword ? 'text' : 'password'"
                                :disabled="isRunning"
                                autocomplete="new-password"
                                placeholder="Tối thiểu 8 ký tự, gồm chữ và số"
                                class="w-full pl-3 pr-16 py-2 rounded-xl bg-slate-950 border border-slate-800 text-sm text-white focus:outline-none focus:border-indigo-500"
                            />
                            <button
                                type="button"
                                class="absolute right-2 top-1.5 px-2 py-0.5 text-[11px] text-slate-400 hover:text-white"
                                @click="showPassword = !showPassword"
                            >
                                {{ showPassword ? 'Ẩn' : 'Hiện' }}
                            </button>
                        </div>
                        <p v-if="defaultPassword && passwordError" class="text-xs text-rose-400 mt-1">{{ passwordError }}</p>
                    </div>
                </div>

                <div class="lg:col-span-2 bg-slate-900/70 border border-slate-800/80 rounded-2xl p-5 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="text-xs font-bold uppercase tracking-wider text-indigo-400">2. Chọn file Excel</div>
                        <button
                            type="button"
                            class="text-xs text-indigo-300 hover:text-indigo-200 underline underline-offset-2"
                            @click="downloadTemplate"
                        >
                            Tải file mẫu (.xlsx)
                        </button>
                    </div>

                    <label
                        for="import-file"
                        :class="[
                            'flex flex-col items-center justify-center gap-2 h-36 rounded-2xl border-2 border-dashed cursor-pointer transition',
                            isDragging ? 'border-indigo-400 bg-indigo-500/10' : 'border-slate-700 hover:border-indigo-500/60 bg-slate-950/40',
                            isRunning ? 'pointer-events-none opacity-50' : '',
                        ]"
                        @dragover.prevent="isDragging = true"
                        @dragleave.prevent="isDragging = false"
                        @drop.prevent="onDrop"
                    >
                        <svg class="w-8 h-8 text-indigo-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                        </svg>
                        <span class="text-sm text-slate-200">{{ fileName || 'Kéo thả file vào đây hoặc bấm để chọn' }}</span>
                        <span class="text-[11px] text-slate-500">.xlsx, .xls, .csv — cột bắt buộc: Email (và Tên đăng nhập, mặc định lấy phần trước @)</span>
                        <input id="import-file" type="file" accept=".xlsx,.xls,.csv" class="hidden" @change="onFileChange" />
                    </label>

                    <div v-if="parseError" class="text-xs text-rose-300 bg-rose-500/10 border border-rose-500/30 rounded-xl px-3 py-2">{{ parseError }}</div>
                </div>
            </div>

            <!-- Preview -->
            <div v-if="rows.length > 0" class="bg-slate-900/70 border border-slate-800/80 rounded-2xl overflow-hidden shadow-xl">
                <div class="p-4 border-b border-slate-800/80 bg-slate-950/40 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-1.5 flex-wrap">
                        <button
                            v-for="tab in [
                                { id: 'all', label: `Tất cả (${counts.total})`, color: 'bg-indigo-600' },
                                { id: 'valid', label: `Đã kiểm tra (${counts.valid})`, color: 'bg-emerald-600' },
                                { id: 'invalid', label: `Lỗi (${counts.invalid})`, color: 'bg-rose-600' },
                                { id: 'created', label: `Đã tạo (${counts.created})`, color: 'bg-teal-600' },
                            ]"
                            :key="tab.id"
                            type="button"
                            :class="[
                                'px-3 py-1.5 rounded-xl text-xs font-semibold transition',
                                filter === tab.id ? `${tab.color} text-white shadow-md` : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/60',
                            ]"
                            @click="filter = tab.id as typeof filter"
                        >
                            {{ tab.label }}
                        </button>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" :disabled="isRunning" class="px-3 py-1.5 rounded-xl text-xs font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 disabled:opacity-50" @click="addRow">
                            + Thêm dòng
                        </button>
                        <button type="button" :disabled="isRunning" class="px-3 py-1.5 rounded-xl text-xs font-medium text-rose-300 hover:bg-rose-500/10 border border-rose-500/30 disabled:opacity-50" @click="clearAll">
                            Xoá danh sách
                        </button>
                    </div>
                </div>

                <div class="overflow-x-auto max-h-[28rem] overflow-y-auto">
                    <table class="w-full text-left text-sm text-slate-300">
                        <thead class="text-xs uppercase bg-slate-950/90 text-slate-400 border-b border-slate-800 sticky top-0 z-10">
                            <tr>
                                <th class="px-3 py-2.5 font-semibold w-10 text-center">#</th>
                                <th class="px-3 py-2.5 font-semibold w-14 text-center">Kết quả</th>
                                <th v-for="col in textFields" :key="col.field" class="px-3 py-2.5 font-semibold whitespace-nowrap">{{ col.label }}</th>
                                <th class="px-3 py-2.5 font-semibold">Giới tính</th>
                                <th class="px-3 py-2.5 font-semibold">Ngày sinh (dd/MM/yyyy)</th>
                                <th class="px-3 py-2.5 font-semibold">Địa chỉ</th>
                                <th class="px-3 py-2.5 font-semibold w-24 text-right">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60">
                            <tr v-for="(row, index) in visibleRows" :key="row.key" :class="row.status === 'created' ? 'bg-teal-500/5' : 'hover:bg-slate-800/30'">
                                <td class="px-3 py-1.5 text-center text-xs text-slate-500">{{ rows.indexOf(row) + 1 }}</td>

                                <!-- Status icon -->
                                <td class="px-3 py-1.5 text-center">
                                    <span v-if="row.status === 'invalid'" class="group relative inline-flex">
                                        <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-rose-500/20 text-rose-400 font-extrabold text-sm cursor-help">!</span>
                                        <span class="pointer-events-none absolute left-8 top-1/2 -translate-y-1/2 z-20 hidden group-hover:block w-64 whitespace-pre-line text-left text-xs text-rose-100 bg-slate-950 border border-rose-500/40 rounded-lg px-3 py-2 shadow-xl">{{ errorSummary(row) }}</span>
                                    </span>
                                    <span v-else-if="row.status === 'created'" class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-teal-500/20 text-teal-300" title="Đã tạo tài khoản">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                    <span v-else class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-emerald-500/20 text-emerald-400" title="Hợp lệ">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                    </span>
                                </td>

                                <td v-for="col in textFields" :key="col.field" class="px-2 py-1">
                                    <input
                                        v-model="row[col.field]"
                                        type="text"
                                        :disabled="row.status === 'created' || isRunning"
                                        :class="[
                                            'px-2 py-1 rounded-lg bg-slate-950/70 border text-xs text-white focus:outline-none focus:border-indigo-500 disabled:opacity-60',
                                            col.width,
                                            cellHasError(row, col.field) ? 'border-rose-500/70' : 'border-slate-800',
                                        ]"
                                        :title="row.errors[col.field]"
                                        @input="onCellEdit(row)"
                                    />
                                </td>

                                <td class="px-2 py-1">
                                    <select
                                        v-model="row.gender"
                                        :disabled="row.status === 'created' || isRunning"
                                        :class="['px-2 py-1 rounded-lg bg-slate-950/70 border text-xs text-white focus:outline-none', cellHasError(row, 'gender') ? 'border-rose-500/70' : 'border-slate-800']"
                                        @change="onCellEdit(row)"
                                    >
                                        <option value="">--</option>
                                        <option value="male">Nam</option>
                                        <option value="female">Nữ</option>
                                        <option value="other">Khác</option>
                                        <option v-if="row.gender && !['male', 'female', 'other'].includes(row.gender)" :value="row.gender">{{ row.gender }}</option>
                                    </select>
                                </td>

                                <td class="px-2 py-1">
                                    <div class="w-36">
                                        <DateInput
                                            v-model="row.date_of_birth"
                                            size="sm"
                                            :disabled="row.status === 'created' || isRunning"
                                            :has-error="cellHasError(row, 'date_of_birth')"
                                            input-class="bg-slate-950/70"
                                            @change="onCellEdit(row)"
                                        />
                                    </div>
                                </td>

                                <td class="px-2 py-1">
                                    <input
                                        v-model="row.address"
                                        type="text"
                                        :disabled="row.status === 'created' || isRunning"
                                        :class="['w-48 px-2 py-1 rounded-lg bg-slate-950/70 border text-xs text-white focus:outline-none', cellHasError(row, 'address') ? 'border-rose-500/70' : 'border-slate-800']"
                                        @input="onCellEdit(row)"
                                    />
                                </td>

                                <td class="px-3 py-1.5 text-right whitespace-nowrap">
                                    <Link
                                        v-if="row.status === 'created' && row.user_id"
                                        :href="route('admin.users.show', row.user_id)"
                                        class="px-2 py-1 rounded-lg text-xs font-medium bg-indigo-500/10 hover:bg-indigo-500/20 text-indigo-300 border border-indigo-500/30"
                                    >
                                        Hồ sơ
                                    </Link>
                                    <button
                                        v-else
                                        type="button"
                                        :disabled="isRunning"
                                        class="p-1 rounded-lg text-slate-400 hover:text-rose-400 hover:bg-rose-500/10 disabled:opacity-50"
                                        title="Bỏ dòng này"
                                        @click="removeRow(row.key)"
                                    >
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Footer: progress + actions -->
                <div class="p-4 border-t border-slate-800 bg-slate-950/40 space-y-3">
                    <div v-if="isRunning || progress.total > 0" class="space-y-1.5">
                        <div class="flex items-center justify-between text-xs text-slate-300">
                            <span>{{ isRunning ? progress.label : 'Tiến trình gần nhất' }}</span>
                            <span>{{ progress.done }}/{{ progress.total }} dòng ({{ progressPercent }}%)</span>
                        </div>
                        <div class="h-2.5 rounded-full bg-slate-800 overflow-hidden" role="progressbar" :aria-valuenow="progressPercent" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-emerald-400 transition-all duration-300" :style="{ width: `${progressPercent}%` }"></div>
                        </div>
                    </div>

                    <div v-if="runMessage" class="text-xs text-slate-200 bg-slate-800/60 border border-slate-700 rounded-xl px-3 py-2">{{ runMessage }}</div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="text-xs text-slate-400">
                            <span v-if="counts.invalid > 0" class="text-rose-300">Còn {{ counts.invalid }} dòng lỗi — hãy sửa trước khi import.</span>
                            <span v-else-if="passwordError" class="text-amber-300">{{ passwordError }}</span>
                            <span v-else>Sẵn sàng tạo {{ importableCount }} tài khoản (mỗi đợt {{ CHUNK_SIZE }} dòng).</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                :disabled="!canServerCheck"
                                class="px-4 py-2 rounded-xl text-sm font-medium bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 disabled:opacity-50"
                                @click="serverCheck"
                            >
                                Kiểm tra trên máy chủ
                            </button>
                            <button
                                type="button"
                                :disabled="!canSubmit"
                                class="px-5 py-2 rounded-xl text-sm font-semibold bg-indigo-600 hover:bg-indigo-500 text-white shadow-lg shadow-indigo-600/30 transition disabled:opacity-50"
                                @click="runImport"
                            >
                                Import {{ importableCount }} người dùng
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
