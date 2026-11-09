export type Availability = {
    total: number;
    available: number;
    reserved: number;
    borrowed: number;
    damaged: number;
    maintenance: number;
    lost: number;
    inactive: number;
};

export type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
};
