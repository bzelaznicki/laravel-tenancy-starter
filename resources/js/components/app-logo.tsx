import { usePage } from '@inertiajs/react';

import AppLogoIcon from '@/components/app-logo-icon';

export default function AppLogo() {
    const { name } = usePage().props;

    return (
        <>
            <AppLogoIcon className="size-[22px]! shrink-0 fill-current text-sidebar-primary" />
            <span className="truncate text-sm font-semibold text-foreground">
                {name}
            </span>
        </>
    );
}
