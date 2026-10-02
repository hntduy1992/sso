export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'user';
    status: 'active' | 'suspended';
    created_at?: string;
    updated_at?: string;
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
