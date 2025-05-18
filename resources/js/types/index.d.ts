import { LucideIcon } from 'lucide-react';
import type { Config } from 'ziggy-js';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    ziggy: Config & { location: string };
    sidebarOpen: boolean;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown;
}

export interface Laptop {
    id: number;
    brand?: {
    name: string;
    };
    series: string;
    model: string;
    cpu: string;
    ram: string;
    gpu: string;
    price: number;
}

export interface LaptopWithRating extends Laptop {
    average_rating: number | string;
}

export interface BreadcrumbItem {
    label: string;
    href?: string;
    active?: boolean;
}

export interface Review {
    id: number;
    responder_name: string;
    rating: number;
    review?: string;
    created_at: string;
}