import { Head, Link, usePage } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes';
import { index as membersIndex } from '@/routes/memberships';
import { edit as editSecurity } from '@/routes/security';
import type { Auth, NavItem } from '@/types';

type SetupStep = {
    title: string;
    description: string;
    action: string;
    href: NavItem['href'];
};

export default function Dashboard() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const canManageMembers = auth.role === 'owner' || auth.role === 'admin';

    const steps: SetupStep[] = [
        ...(canManageMembers
            ? [
                  {
                      title: 'Invite your team',
                      description:
                          'Members work day to day in the workspace; viewers get read-only access.',
                      action: 'Invite',
                      href: membersIndex(),
                  },
              ]
            : []),
        {
            title: 'Secure your account',
            description:
                'Add two-factor authentication or a passkey so a leaked password is not enough to get in.',
            action: 'Security',
            href: editSecurity(),
        },
    ];

    return (
        <>
            <Head title="Home" />
            <div className="max-w-[660px] px-4 py-7.5 md:px-6.5">
                <h2 className="mb-1 text-[15px] font-semibold">
                    {auth.tenant ? `Set up ${auth.tenant.name}` : 'Get set up'}
                </h2>
                <p className="mb-5 text-[13px] text-muted-foreground">
                    A couple of steps before your team has something real to
                    work from.
                </p>

                <ol className="overflow-hidden rounded-lg border bg-card">
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="flex items-start gap-3.5 border-b p-4 last:border-b-0"
                        >
                            <span
                                className={cn(
                                    'flex size-[22px] shrink-0 items-center justify-center rounded-full text-[11.5px] font-semibold',
                                    index === 0
                                        ? 'bg-primary text-primary-foreground'
                                        : 'border border-input text-muted-foreground',
                                )}
                            >
                                {index + 1}
                            </span>
                            <div className="flex-1">
                                <div className="text-sm font-medium">
                                    {step.title}
                                </div>
                                <p className="mt-0.5 text-[12.5px] text-muted-foreground">
                                    {step.description}
                                </p>
                            </div>
                            <Button
                                asChild
                                size="sm"
                                variant={index === 0 ? 'default' : 'outline'}
                            >
                                <Link href={step.href}>{step.action}</Link>
                            </Button>
                        </li>
                    ))}
                </ol>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Home',
            href: dashboard(),
        },
    ],
};
