import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    return (
        <header className="flex min-h-[4.5rem] shrink-0 items-center gap-3 border-b border-border px-4 md:px-6.5">
            <SidebarTrigger className="-ml-1 text-muted-foreground" />
            {breadcrumbs.length === 1 ? (
                <h1 className="text-[21px] font-semibold tracking-[-0.01em]">
                    {breadcrumbs[0].title}
                </h1>
            ) : (
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            )}
        </header>
    );
}
