export type ImportRowStatus = 'pending' | 'valid' | 'invalid' | 'created';

export interface ImportRow {
    key: string;
    name: string;
    email: string;
    full_name: string;
    phone_number: string;
    contact_email: string;
    gender: string;
    date_of_birth: string;
    address: string;
    status: ImportRowStatus;
    errors: Record<string, string>;
    user_id: number | null;
}

export type ImportField = 'name' | 'email' | 'full_name' | 'phone_number' | 'contact_email' | 'gender' | 'date_of_birth' | 'address';

export const IMPORT_FIELDS: ImportField[] = [
    'name',
    'email',
    'full_name',
    'phone_number',
    'contact_email',
    'gender',
    'date_of_birth',
    'address',
];

export const TEMPLATE_HEADERS: Record<ImportField, string> = {
    name: 'Tên đăng nhập*',
    email: 'Email*',
    full_name: 'Họ và tên',
    phone_number: 'Số điện thoại',
    contact_email: 'Email liên hệ',
    gender: 'Giới tính (Nam/Nữ/Khác)',
    date_of_birth: 'Ngày sinh (dd/mm/yyyy)',
    address: 'Địa chỉ',
};

/** Header aliases (lowercase, no diacritics/punctuation) → field. */
const HEADER_ALIASES: Record<string, ImportField> = {
    name: 'name',
    username: 'name',
    'ten dang nhap': 'name',
    'ten hien thi': 'name',
    email: 'email',
    'email dang nhap': 'email',
    'full name': 'full_name',
    fullname: 'full_name',
    'ho va ten': 'full_name',
    'ho ten': 'full_name',
    phone: 'phone_number',
    'phone number': 'phone_number',
    'so dien thoai': 'phone_number',
    sdt: 'phone_number',
    'contact email': 'contact_email',
    'email lien he': 'contact_email',
    'email phu': 'contact_email',
    gender: 'gender',
    'gioi tinh': 'gender',
    'date of birth': 'date_of_birth',
    dob: 'date_of_birth',
    'ngay sinh': 'date_of_birth',
    address: 'address',
    'dia chi': 'address',
};

const removeDiacritics = (value: string): string =>
    value
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D');

export const resolveHeader = (header: unknown): ImportField | null => {
    const normalized = removeDiacritics(String(header ?? ''))
        .toLowerCase()
        .replace(/\(.*?\)/g, '')
        .replace(/[*_\-:]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();

    return HEADER_ALIASES[normalized] ?? null;
};

export const normalizeGender = (value: string): string => {
    const normalized = removeDiacritics(value).toLowerCase().trim();
    if (['nam', 'male', 'm'].includes(normalized)) return 'male';
    if (['nu', 'female', 'f'].includes(normalized)) return 'female';
    if (['khac', 'other', 'o'].includes(normalized)) return 'other';
    return value.trim();
};

const pad = (n: number): string => String(n).padStart(2, '0');

/** Accepts dd/mm/yyyy, yyyy-mm-dd or an Excel serial number and returns yyyy-mm-dd (or the raw value if unparseable). */
export const normalizeDate = (value: string): string => {
    const text = value.trim();
    if (text === '') return '';

    let match = text.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/);
    if (match) return `${match[3]}-${pad(Number(match[2]))}-${pad(Number(match[1]))}`;

    match = text.match(/^(\d{4})[/.-](\d{1,2})[/.-](\d{1,2})$/);
    if (match) return `${match[1]}-${pad(Number(match[2]))}-${pad(Number(match[3]))}`;

    if (/^\d{4,6}(\.\d+)?$/.test(text)) {
        const date = new Date(Math.round((Number(text) - 25569) * 86400 * 1000));
        if (!Number.isNaN(date.getTime())) {
            return `${date.getUTCFullYear()}-${pad(date.getUTCMonth() + 1)}-${pad(date.getUTCDate())}`;
        }
    }

    return text;
};

const EMAIL_REGEX = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
const PHONE_REGEX = /^[0-9+\-\s().]{8,20}$/;

const isRealDate = (value: string): boolean => {
    const match = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return false;
    const date = new Date(Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3])));
    return date.getUTCFullYear() === Number(match[1])
        && date.getUTCMonth() === Number(match[2]) - 1
        && date.getUTCDate() === Number(match[3]);
};

/** Client-side validation mirroring NewUserValidationRules on the backend (unique-in-DB is checked server-side). */
export const validateRow = (row: ImportRow): Record<string, string> => {
    const errors: Record<string, string> = {};

    if (!row.name.trim()) errors.name = 'Tên đăng nhập là bắt buộc.';
    else if (row.name.length > 255) errors.name = 'Tên hiển thị không được vượt quá 255 ký tự.';

    if (!row.email.trim()) errors.email = 'Email đăng nhập là bắt buộc.';
    else if (!EMAIL_REGEX.test(row.email.trim())) errors.email = 'Email đăng nhập không đúng định dạng.';

    if (row.full_name.length > 255) errors.full_name = 'Họ và tên không được vượt quá 255 ký tự.';

    if (row.phone_number.trim() && !PHONE_REGEX.test(row.phone_number.trim())) {
        errors.phone_number = 'Số điện thoại không hợp lệ.';
    }

    if (row.contact_email.trim() && !EMAIL_REGEX.test(row.contact_email.trim())) {
        errors.contact_email = 'Email liên hệ không đúng định dạng.';
    }

    if (row.gender.trim() && !['male', 'female', 'other'].includes(row.gender.trim())) {
        errors.gender = 'Giới tính phải là Nam, Nữ hoặc Khác.';
    }

    if (row.date_of_birth.trim()) {
        if (!isRealDate(row.date_of_birth.trim())) {
            errors.date_of_birth = 'Ngày sinh không hợp lệ (định dạng dd/mm/yyyy).';
        } else if (new Date(row.date_of_birth.trim()) >= new Date(new Date().toDateString())) {
            errors.date_of_birth = 'Ngày sinh phải trước ngày hôm nay.';
        }
    }

    if (row.address.length > 500) errors.address = 'Địa chỉ không được vượt quá 500 ký tự.';

    return errors;
};

/** Flags rows whose email repeats an earlier row in the list. */
export const findDuplicateEmails = (rows: ImportRow[]): Set<string> => {
    const seen = new Set<string>();
    const duplicateKeys = new Set<string>();

    for (const row of rows) {
        if (row.status === 'created') continue;
        const email = row.email.trim().toLowerCase();
        if (!email) continue;
        if (seen.has(email)) duplicateKeys.add(row.key);
        seen.add(email);
    }

    return duplicateKeys;
};

let rowCounter = 0;

export const createEmptyRow = (): ImportRow => ({
    key: `r${Date.now().toString(36)}${(rowCounter++).toString(36)}`,
    name: '',
    email: '',
    full_name: '',
    phone_number: '',
    contact_email: '',
    gender: '',
    date_of_birth: '',
    address: '',
    status: 'pending',
    errors: {},
    user_id: null,
});
