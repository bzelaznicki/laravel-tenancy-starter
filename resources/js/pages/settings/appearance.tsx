import { Head } from '@inertiajs/react';
import AppearanceTabs from '@/components/appearance-tabs';
import Heading from '@/components/heading';
import { edit as editAppearance } from '@/routes/appearance';

export default function Appearance() {
    return (
        <>
            <Head title="Appearance" />

            <h1 className="sr-only">Appearance</h1>

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Theme"
                    description="Light, dark, or follow your system setting."
                />
                <AppearanceTabs />
            </div>
        </>
    );
}

Appearance.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: editAppearance(),
        },
    ],
};
