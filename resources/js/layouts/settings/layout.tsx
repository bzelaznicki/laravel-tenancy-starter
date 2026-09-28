import { Link } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { index as membersIndex } from '@/routes/memberships';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const settingsTabs: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Members',
        href: membersIndex(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <>
            <nav
                className="flex gap-5 overflow-x-auto border-b border-border px-4 pt-3.5 md:px-6.5"
                aria-label="Settings"
            >
                {settingsTabs.map((tab) => {
                    const isActive = isCurrentOrParentUrl(tab.href);

                    return (
                        <Link
                            key={toUrl(tab.href)}
                            href={tab.href}
                            aria-current={isActive ? 'page' : undefined}
                            className={cn(
                                'px-0.5 pb-3 text-[13.5px] whitespace-nowrap text-muted-foreground hover:text-foreground',
                                isActive &&
                                    'font-medium text-foreground shadow-[inset_0_-2px_0_var(--primary)]',
                            )}
                        >
                            {tab.title}
                        </Link>
                    );
                })}
            </nav>

            <div className="px-4 py-6 md:px-6.5">
                <section className="max-w-4xl space-y-12">{children}</section>
            </div>
        </>
    );
}
