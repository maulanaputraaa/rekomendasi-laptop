import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { LayoutGrid, Users } from 'lucide-react';
import { usePage } from '@inertiajs/react';

export function AppSidebar() {
    const { auth } = usePage<{ auth: { user?: { role: string } } }>().props;

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: auth.user?.role === 'admin' ? '/Admin/DashboardAdmin' : '/dashboard',
            icon: LayoutGrid,
        },
        // Tambahkan menu User Management hanya untuk admin
        ...(auth.user?.role === 'admin' ? [
            {
                title: 'User Management',
                href: '/Admin/UsersList',
                icon: Users,
            }
        ] : [])
    ];

    const footerNavItems: NavItem[] = [
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}