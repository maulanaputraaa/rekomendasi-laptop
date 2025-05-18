export interface Brand {
    id: number;
    name: string;

}
export interface LaptopWithRating extends Laptop {
    average_rating: number | string;
    brand?: Brand;
}
export interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    created_at: string;
}

export interface Laptop {
    id: number;
    brand_id: number;
    brand?: Brand;
    series: string;
    model: string;
    cpu: string;
    ram: string;
    storage: string;
    gpu: string;
    price: number;
    review_count: number;
    rating: number;
    created_at: string;
}

export interface Review {
    id: number;
    responder_name: string;
    rating: number;
    review?: string;
    created_at: string;
}

export interface LaptopWithRating extends Laptop {
    average_rating: number | string;
}

export interface BreadcrumbItem {
    title: string;
    href?: string;
    active?: boolean;
}

export interface Auth {
    user: {
        id: number;
        name: string;
        email: string;
        role: string;
    } | null;
}


export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    sidebarOpen: boolean;
    [key: string]: unknown;
};

export type NavItem = {
    title: string;
    href: string;
    icon?: React.ElementType;
};

export interface PaginationType {
    url: string | null;
    label: string;
    active: boolean;
}

export interface StatCardProps {
    title: string;
    value: string | number;
    icon: React.ElementType;
    trend: string;
    color: string;
}

export interface LaptopCardProps {
    laptop: Laptop;
    onDelete: (id: number) => void;
}

export interface FlashProps {
    success?: string;
    error?: string;
}

export interface Props {
    users: User[];
    flash?: FlashProps;
    laptop: Laptop & {
        reviews: Review[];
        average_rating: number;
    };
    query: string;
    results: (Laptop & { average_rating?: number })[];
    stats: {
        total_laptops: number;
        total_users: number;
        total_brands: number;
        avg_price: number;
    };
}