import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { type BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

const AppLayout = ({
    children,
    breadcrumbs = [],
    ...props
}: AppLayoutProps) => (
    <AppLayoutTemplate
        breadcrumbs={breadcrumbs}
        {...props} // Pastikan hanya properti yang diperlukan template yang di-pass
    >
    {children}
    </AppLayoutTemplate>
);

export default AppLayout;