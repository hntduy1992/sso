/**
 * Date utility helpers for dd/MM/yyyy format conversions.
 */

export const padZero = (n: number | string, len = 2): string => {
    return String(n).padStart(len, '0');
};

/**
 * Converts ISO/DB format (YYYY-MM-DD or ISO string) to display format (dd/MM/yyyy).
 * Returns empty string or fallback if invalid.
 */
export const formatToDisplayDate = (value?: string | Date | null, fallback = ''): string => {
    if (!value) return fallback;

    if (value instanceof Date) {
        if (Number.isNaN(value.getTime())) return fallback;
        return `${padZero(value.getDate())}/${padZero(value.getMonth() + 1)}/${value.getFullYear()}`;
    }

    const str = String(value).trim();
    if (!str) return fallback;

    // Matches YYYY-MM-DD or YYYY/MM/DD
    const isoMatch = str.match(/^(\d{4})[-/](\d{1,2})[-/](\d{1,2})/);
    if (isoMatch) {
        const y = isoMatch[1];
        const m = padZero(isoMatch[2]);
        const d = padZero(isoMatch[3]);
        return `${d}/${m}/${y}`;
    }

    // Already in dd/MM/yyyy or dd-MM-yyyy
    const dmyMatch = str.match(/^(\d{1,2})[-/](\d{1,2})[-/](\d{4})/);
    if (dmyMatch) {
        const d = padZero(dmyMatch[1]);
        const m = padZero(dmyMatch[2]);
        const y = dmyMatch[3];
        return `${d}/${m}/${y}`;
    }

    // Try parsing as Date object
    const date = new Date(str);
    if (!Number.isNaN(date.getTime())) {
        return `${padZero(date.getDate())}/${padZero(date.getMonth() + 1)}/${date.getFullYear()}`;
    }

    return fallback;
};

/**
 * Converts display format (dd/MM/yyyy) to model/database format (YYYY-MM-DD).
 * Returns empty string if invalid.
 */
export const parseDisplayToIso = (displayValue?: string | null): string => {
    if (!displayValue) return '';
    const text = displayValue.trim();

    // dd/MM/yyyy or dd.MM.yyyy or dd-MM-yyyy
    const match = text.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$/);
    if (!match) return '';

    const day = Number(match[1]);
    const month = Number(match[2]);
    const year = Number(match[3]);

    if (month < 1 || month > 12) return '';
    if (year < 1000 || year > 9999) return '';

    const maxDays = new Date(year, month, 0).getDate();
    if (day < 1 || day > maxDays) return '';

    return `${year}-${padZero(month)}-${padZero(day)}`;
};

/**
 * Check if a display string represents a valid real date (dd/MM/yyyy).
 */
export const isValidDisplayDate = (displayValue?: string | null): boolean => {
    return Boolean(parseDisplayToIso(displayValue));
};
