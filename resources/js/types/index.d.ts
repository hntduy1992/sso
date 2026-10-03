export interface UserPositionSummary {
    id: number;
    department_id: number;
    is_primary: boolean;
    department?: {
        id: number;
        name: string;
        code: string;
        type: string;
    };
    position_type?: {
        id: number;
        name: string;
        code: string;
        level: number;
    };
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    status: 'active' | 'suspended';
    created_at?: string;
    updated_at?: string;
    active_positions?: UserPositionSummary[];
    profile?: {
        full_name?: string | null;
        avatar_path?: string | null;
    } | null;
}

export interface FlashMessages {
    success?: string | null;
    error?: string | null;
}

export type PageProps<T extends Record<string, unknown> = Record<string, unknown>> = T & {
    auth: {
        user: User | null;
    };
    flash: FlashMessages;
    errors: Record<string, string>;
};
