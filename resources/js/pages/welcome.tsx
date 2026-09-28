import { Head, Link, usePage } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import { Button } from '@/components/ui/button';
import { dashboard, register } from '@/routes';
import { find as findTenant } from '@/routes/tenant';

const principles = [
    {
        title: 'One workspace per company',
        description:
            'Every team gets its own subdomain and signs in there. Data never crosses between workspaces.',
    },
    {
        title: 'Roles you control',
        description:
            'Owners, admins, members and viewers. Invite teammates by email and change their access at any time.',
    },
    {
        title: 'Secure by default',
        description:
            'Email verification, two-factor authentication and passkeys come built in.',
    },
];

export default function Welcome() {
    const { auth, name } = usePage().props;

    return (
        <>
            <Head title="Welcome" />
            <div className="flex min-h-screen flex-col bg-background text-foreground">
                <header className="flex items-center gap-2.5 border-b px-6 py-4.5 md:px-10">
                    <AppLogoIcon className="size-[22px] fill-current text-foreground" />
                    <span className="text-sm font-semibold">{name}</span>
                    <nav className="ml-auto flex items-center gap-5 text-[13.5px]">
                        {auth.user ? (
                            <Button asChild>
                                <Link href={dashboard()}>Dashboard</Link>
                            </Button>
                        ) : (
                            <>
                                <Link
                                    href={findTenant()}
                                    className="text-muted-foreground hover:text-foreground"
                                >
                                    Log in
                                </Link>
                                <Button asChild>
                                    <Link href={register()}>Start free</Link>
                                </Button>
                            </>
                        )}
                    </nav>
                </header>

                <main className="flex-1">
                    <section className="max-w-[560px] px-6 pt-18 pb-15 md:px-10">
                        <div className="mb-3.5 text-[11.5px] font-medium text-muted-foreground">
                            Multi-tenant Laravel starter kit
                        </div>
                        <h1 className="mb-4.5 text-4xl leading-[1.08] font-semibold tracking-[-0.025em] md:text-[44px]">
                            A workspace for your whole team.
                        </h1>
                        <p className="mb-6.5 text-[15.5px] leading-relaxed text-muted-foreground">
                            Create a workspace, invite your team and get to
                            work. Replace this page with your product&apos;s
                            homepage.
                        </p>
                        {!auth.user && (
                            <Button asChild size="lg">
                                <Link href={register()}>
                                    Create your workspace
                                </Link>
                            </Button>
                        )}
                    </section>

                    <section className="grid gap-7.5 border-t px-6 pt-9 pb-10 md:grid-cols-3 md:px-10">
                        {principles.map((principle) => (
                            <div key={principle.title}>
                                <div className="mb-1.5 text-[15px] font-semibold">
                                    {principle.title}
                                </div>
                                <p className="text-[13.5px] leading-relaxed text-muted-foreground">
                                    {principle.description}
                                </p>
                            </div>
                        ))}
                    </section>
                </main>
            </div>
        </>
    );
}
