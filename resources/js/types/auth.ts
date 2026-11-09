export type User = {
    id: number;
    name: string;
    username: string;
    email: string | null;
    member_id: number;
    status: 'active' | 'inactive';
    must_change_password: boolean;
    roles: string[];
    avatar?: string;
    email_verified_at?: string | null;
    created_at?: string;
    updated_at?: string;
    [key: string]: unknown;
};

export type Auth = {
    user: User;
};
