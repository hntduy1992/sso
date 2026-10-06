<script setup lang="ts">
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { formatToDisplayDate, parseDisplayToIso, padZero } from '@/utils/date';

interface Props {
    modelValue?: string | null;
    placeholder?: string;
    id?: string;
    name?: string;
    disabled?: boolean;
    readonly?: boolean;
    required?: boolean;
    hasError?: boolean;
    min?: string; // YYYY-MM-DD or dd/MM/yyyy
    max?: string; // YYYY-MM-DD or dd/MM/yyyy
    size?: 'sm' | 'md' | 'lg';
    inputClass?: string;
}

const props = withDefaults(defineProps<Props>(), {
    modelValue: '',
    placeholder: 'dd/MM/yyyy',
    id: undefined,
    name: undefined,
    disabled: false,
    readonly: false,
    required: false,
    hasError: false,
    min: undefined,
    max: undefined,
    size: 'md',
    inputClass: '',
});

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
    (e: 'change', value: string): void;
    (e: 'blur', event: FocusEvent): void;
}>();

// Wrapper and popover refs
const containerRef = ref<HTMLElement | null>(null);
const popoverRef = ref<HTMLElement | null>(null);
const isOpen = ref(false);

// Floating coordinates for Teleported popover
const popoverStyle = ref<{ top: string; left: string }>({ top: '0px', left: '0px' });

// Display text in input field (dd/MM/yyyy)
const displayText = ref('');

// Sync from modelValue prop to displayText
watch(
    () => props.modelValue,
    (newVal) => {
        const formatted = formatToDisplayDate(newVal);
        if (formatted !== displayText.value) {
            displayText.value = formatted;
        }
    },
    { immediate: true }
);

// Calendar viewing state
const viewYear = ref<number>(new Date().getFullYear());
const viewMonth = ref<number>(new Date().getMonth()); // 0-11

const syncViewDateFromCurrentValue = () => {
    if (props.modelValue) {
        const iso = props.modelValue.includes('/') ? parseDisplayToIso(props.modelValue) : props.modelValue;
        const match = iso.match(/^(\d{4})-(\d{2})-(\d{2})/);
        if (match) {
            viewYear.value = Number(match[1]);
            viewMonth.value = Number(match[2]) - 1;
            return;
        }
    }
    const today = new Date();
    viewYear.value = today.getFullYear();
    viewMonth.value = today.getMonth();
};

const updatePopoverPosition = () => {
    if (!containerRef.value) return;
    const rect = containerRef.value.getBoundingClientRect();
    const popoverWidth = 320;
    const popoverHeight = 350;

    let top = rect.bottom + 6;
    let left = rect.left;

    // Flip to top if overflowing bottom and space exists above
    if (top + popoverHeight > window.innerHeight && rect.top - popoverHeight > 10) {
        top = rect.top - popoverHeight - 6;
    }

    // Keep within horizontal screen bounds
    if (left + popoverWidth > window.innerWidth) {
        left = Math.max(10, window.innerWidth - popoverWidth - 16);
    }
    if (left < 10) {
        left = 10;
    }

    popoverStyle.value = {
        top: `${top}px`,
        left: `${left}px`,
    };
};

const togglePicker = async () => {
    if (props.disabled || props.readonly) return;
    if (!isOpen.value) {
        syncViewDateFromCurrentValue();
        updatePopoverPosition();
        isOpen.value = true;
        await nextTick();
        updatePopoverPosition();
    } else {
        isOpen.value = false;
    }
};

const closePicker = () => {
    isOpen.value = false;
};

// Masking and manual input
const handleInput = (event: Event) => {
    const input = event.target as HTMLInputElement;
    const raw = input.value;

    // Filter only digits and slashes
    const cleaned = raw.replace(/[^\d/]/g, '');

    // Auto slash insertion if user is typing numbers
    const digitsOnly = cleaned.replace(/\D/g, '').slice(0, 8);
    let formatted = '';
    if (digitsOnly.length > 0) {
        formatted = digitsOnly.slice(0, 2);
        if (digitsOnly.length >= 3) {
            formatted += '/' + digitsOnly.slice(2, 4);
        }
        if (digitsOnly.length >= 5) {
            formatted += '/' + digitsOnly.slice(4, 8);
        }
    }

    displayText.value = formatted;
    input.value = formatted;

    if (formatted === '') {
        emit('update:modelValue', '');
        emit('change', '');
        return;
    }

    // If complete date (dd/MM/yyyy), emit ISO value
    if (formatted.length === 10) {
        const iso = parseDisplayToIso(formatted);
        if (iso) {
            emit('update:modelValue', iso);
            emit('change', iso);
        }
    }
};

const handleBlur = (event: FocusEvent) => {
    emit('blur', event);

    // Validate on blur
    if (!displayText.value) {
        emit('update:modelValue', '');
        emit('change', '');
        return;
    }

    const iso = parseDisplayToIso(displayText.value);
    if (iso) {
        emit('update:modelValue', iso);
        emit('change', iso);
    } else {
        // If invalid incomplete string on blur, revert to current valid modelValue
        displayText.value = formatToDisplayDate(props.modelValue);
    }
};

const clearValue = (event?: MouseEvent) => {
    event?.stopPropagation();
    displayText.value = '';
    emit('update:modelValue', '');
    emit('change', '');
};

// Calendar navigation
const prevMonth = () => {
    if (viewMonth.value === 0) {
        viewMonth.value = 11;
        viewYear.value -= 1;
    } else {
        viewMonth.value -= 1;
    }
};

const nextMonth = () => {
    if (viewMonth.value === 11) {
        viewMonth.value = 0;
        viewYear.value += 1;
    } else {
        viewMonth.value += 1;
    }
};

const monthNames = [
    'Tháng 1',
    'Tháng 2',
    'Tháng 3',
    'Tháng 4',
    'Tháng 5',
    'Tháng 6',
    'Tháng 7',
    'Tháng 8',
    'Tháng 9',
    'Tháng 10',
    'Tháng 11',
    'Tháng 12',
];

const dayNames = ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN'];

// Available years for dropdown (1920 to current + 15)
const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear();
    const start = 1920;
    const end = currentYear + 15;
    const list: number[] = [];
    for (let y = end; y >= start; y--) {
        list.push(y);
    }
    return list;
});

interface CalendarCell {
    day: number;
    month: number; // 0-11
    year: number;
    isCurrentMonth: boolean;
    isoDate: string;
    isToday: boolean;
    isSelected: boolean;
    isDisabled: boolean;
}

const minIso = computed(() => (props.min ? (props.min.includes('/') ? parseDisplayToIso(props.min) : props.min) : ''));
const maxIso = computed(() => (props.max ? (props.max.includes('/') ? parseDisplayToIso(props.max) : props.max) : ''));

const calendarCells = computed<CalendarCell[]>(() => {
    const year = viewYear.value;
    const month = viewMonth.value;

    const firstDay = new Date(year, month, 1);
    // JS getDay(): 0 is Sunday, 1 is Monday. Monday = 0, Sunday = 6
    const startDayIndex = (firstDay.getDay() + 6) % 7;

    const daysInCurrentMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const todayIso = new Date().toISOString().split('T')[0];
    const currentSelectedIso = props.modelValue
        ? (props.modelValue.includes('/') ? parseDisplayToIso(props.modelValue) : props.modelValue)
        : '';

    const cells: CalendarCell[] = [];

    // Previous month padding days
    for (let i = startDayIndex - 1; i >= 0; i--) {
        const d = daysInPrevMonth - i;
        const m = month === 0 ? 11 : month - 1;
        const y = month === 0 ? year - 1 : year;
        const iso = `${y}-${padZero(m + 1)}-${padZero(d)}`;
        cells.push({
            day: d,
            month: m,
            year: y,
            isCurrentMonth: false,
            isoDate: iso,
            isToday: iso === todayIso,
            isSelected: iso === currentSelectedIso,
            isDisabled: (Boolean(minIso.value) && iso < minIso.value) || (Boolean(maxIso.value) && iso > maxIso.value),
        });
    }

    // Current month days
    for (let d = 1; d <= daysInCurrentMonth; d++) {
        const iso = `${year}-${padZero(month + 1)}-${padZero(d)}`;
        cells.push({
            day: d,
            month,
            year,
            isCurrentMonth: true,
            isoDate: iso,
            isToday: iso === todayIso,
            isSelected: iso === currentSelectedIso,
            isDisabled: (Boolean(minIso.value) && iso < minIso.value) || (Boolean(maxIso.value) && iso > maxIso.value),
        });
    }

    // Next month padding days to complete full 6 weeks (42 cells) or 5 weeks (35 cells)
    const totalSlots = cells.length > 35 ? 42 : 35;
    const remaining = totalSlots - cells.length;
    for (let d = 1; d <= remaining; d++) {
        const m = month === 11 ? 0 : month + 1;
        const y = month === 11 ? year + 1 : year;
        const iso = `${y}-${padZero(m + 1)}-${padZero(d)}`;
        cells.push({
            day: d,
            month: m,
            year: y,
            isCurrentMonth: false,
            isoDate: iso,
            isToday: iso === todayIso,
            isSelected: iso === currentSelectedIso,
            isDisabled: (Boolean(minIso.value) && iso < minIso.value) || (Boolean(maxIso.value) && iso > maxIso.value),
        });
    }

    return cells;
});

const selectDate = (cell: CalendarCell) => {
    if (cell.isDisabled) return;
    displayText.value = formatToDisplayDate(cell.isoDate);
    emit('update:modelValue', cell.isoDate);
    emit('change', cell.isoDate);
    closePicker();
};

const selectToday = () => {
    const today = new Date();
    const iso = `${today.getFullYear()}-${padZero(today.getMonth() + 1)}-${padZero(today.getDate())}`;
    if ((minIso.value && iso < minIso.value) || (maxIso.value && iso > maxIso.value)) return;

    displayText.value = formatToDisplayDate(iso);
    emit('update:modelValue', iso);
    emit('change', iso);
    closePicker();
};

// Click outside & scroll handling
const handleClickOutside = (event: MouseEvent) => {
    const target = event.target as Node;
    if (
        containerRef.value &&
        !containerRef.value.contains(target) &&
        popoverRef.value &&
        !popoverRef.value.contains(target)
    ) {
        closePicker();
    }
};

const handleKeyDown = (event: KeyboardEvent) => {
    if (event.key === 'Escape' && isOpen.value) {
        closePicker();
    }
};

const handleScrollOrResize = () => {
    if (isOpen.value) {
        updatePopoverPosition();
    }
};

onMounted(() => {
    document.addEventListener('click', handleClickOutside);
    document.addEventListener('keydown', handleKeyDown);
    window.addEventListener('scroll', handleScrollOrResize, true);
    window.addEventListener('resize', handleScrollOrResize);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside);
    document.removeEventListener('keydown', handleKeyDown);
    window.removeEventListener('scroll', handleScrollOrResize, true);
    window.removeEventListener('resize', handleScrollOrResize);
});

// Size classes
const sizeClasses = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'py-1 pl-2.5 pr-14 text-xs rounded-lg';
        case 'lg':
            return 'py-2.5 pl-4 pr-16 text-sm rounded-xl';
        default:
            return 'py-2 pl-3 pr-16 text-sm rounded-xl';
    }
});

const borderClasses = computed(() => {
    if (props.hasError) {
        return 'border-rose-500/70 text-rose-100 focus:border-rose-500 focus:ring-1 focus:ring-rose-500/30';
    }
    return 'border-slate-800 text-slate-100 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500/30';
});
</script>

<template>
    <div ref="containerRef" class="relative w-full">
        <!-- Input Container -->
        <div class="relative flex items-center w-full">
            <input
                :id="id"
                :name="name"
                type="text"
                :value="displayText"
                :placeholder="placeholder"
                :disabled="disabled"
                :readonly="readonly"
                :required="required"
                inputmode="numeric"
                autocomplete="off"
                :class="[
                    'w-full bg-slate-950 border transition outline-none placeholder:text-slate-500 font-mono tracking-wide',
                    sizeClasses,
                    borderClasses,
                    disabled ? 'opacity-50 cursor-not-allowed bg-slate-900/50' : 'cursor-text',
                    inputClass,
                ]"
                @input="handleInput"
                @blur="handleBlur"
                @keydown.enter.prevent="closePicker"
            />

            <!-- Action buttons (Clear & Calendar icon) -->
            <div class="absolute right-1.5 flex items-center gap-0.5 text-slate-400">
                <button
                    v-if="displayText && !disabled && !readonly"
                    type="button"
                    tabindex="-1"
                    title="Xóa ngày"
                    class="p-1 rounded-md text-slate-500 hover:text-slate-300 hover:bg-slate-800 transition"
                    @click="clearValue"
                >
                    <svg class="w-3.5 h-3.5" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </button>

                <button
                    type="button"
                    tabindex="-1"
                    :disabled="disabled || readonly"
                    title="Chọn ngày từ lịch"
                    :class="[
                        'p-1.5 rounded-lg text-slate-400 hover:text-indigo-400 hover:bg-slate-800/80 transition',
                        disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
                        isOpen ? 'text-indigo-400 bg-indigo-500/10' : ''
                    ]"
                    @click="togglePicker"
                >
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Teleported Calendar Popup to avoid overflow/clipping bugs -->
        <Teleport to="body">
            <div
                v-if="isOpen"
                ref="popoverRef"
                :style="{ top: popoverStyle.top, left: popoverStyle.left }"
                class="fixed z-[9999] w-72 sm:w-80 p-4 rounded-2xl bg-slate-900 border border-slate-800 shadow-2xl backdrop-blur-2xl text-slate-200 animate-in fade-in zoom-in-95 duration-150 select-none"
            >
                <!-- Header: Month & Year navigation + Fast selectors -->
                <div class="flex items-center justify-between gap-1 mb-3">
                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                        title="Tháng trước"
                        @click="prevMonth"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                        </svg>
                    </button>

                    <div class="flex items-center gap-1.5 font-medium text-sm">
                        <!-- Quick Month Select -->
                        <select
                            v-model="viewMonth"
                            class="bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
                        >
                            <option v-for="(name, idx) in monthNames" :key="idx" :value="idx">
                                {{ name }}
                            </option>
                        </select>

                        <!-- Quick Year Select -->
                        <select
                            v-model="viewYear"
                            class="bg-slate-950 border border-slate-800 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:border-indigo-500 cursor-pointer"
                        >
                            <option v-for="y in yearOptions" :key="y" :value="y">
                                {{ y }}
                            </option>
                        </select>
                    </div>

                    <button
                        type="button"
                        class="p-1.5 rounded-lg text-slate-400 hover:text-white hover:bg-slate-800 transition"
                        title="Tháng sau"
                        @click="nextMonth"
                    >
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </button>
                </div>

                <!-- Days of Week Header -->
                <div class="grid grid-cols-7 gap-1 text-center text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-1">
                    <span v-for="d in dayNames" :key="d" class="py-1">
                        {{ d }}
                    </span>
                </div>

                <!-- Days Grid -->
                <div class="grid grid-cols-7 gap-1 text-xs">
                    <button
                        v-for="(cell, idx) in calendarCells"
                        :key="idx"
                        type="button"
                        :disabled="cell.isDisabled"
                        :class="[
                            'h-8 flex items-center justify-center rounded-xl text-center font-medium transition select-none',
                            cell.isDisabled ? 'opacity-25 cursor-not-allowed' : 'cursor-pointer',
                            cell.isSelected
                                ? 'bg-indigo-600 text-white font-bold shadow-md shadow-indigo-500/40 ring-1 ring-indigo-400'
                                : cell.isCurrentMonth
                                    ? cell.isToday
                                        ? 'bg-indigo-500/10 text-indigo-400 font-bold border border-indigo-500/40 hover:bg-indigo-500/20'
                                        : 'text-slate-200 hover:bg-slate-800 hover:text-white'
                                    : 'text-slate-600 hover:bg-slate-800/50 hover:text-slate-400',
                        ]"
                        @click="selectDate(cell)"
                    >
                        {{ cell.day }}
                    </button>
                </div>

                <!-- Footer: Quick actions -->
                <div class="mt-3 pt-2.5 border-t border-slate-800/80 flex items-center justify-between text-xs">
                    <button
                        type="button"
                        class="text-indigo-400 hover:text-indigo-300 font-medium py-1 px-2 rounded-lg hover:bg-indigo-500/10 transition"
                        @click="selectToday"
                    >
                        Hôm nay
                    </button>

                    <div class="flex items-center gap-1.5">
                        <button
                            v-if="displayText"
                            type="button"
                            class="text-slate-400 hover:text-rose-400 py-1 px-2 rounded-lg hover:bg-rose-500/10 transition"
                            @click="clearValue"
                        >
                            Xóa
                        </button>
                        <button
                            type="button"
                            class="text-slate-400 hover:text-white py-1 px-2 rounded-lg hover:bg-slate-800 transition"
                            @click="closePicker"
                        >
                            Đóng
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>
