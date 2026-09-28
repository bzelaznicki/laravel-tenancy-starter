import { Form, Head, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import Heading from '@/components/heading';
import ChangeRoleDialog from '@/components/settings/change-role-dialog';
import InviteMemberDialog from '@/components/settings/invite-member-dialog';
import RemoveMemberDialog from '@/components/settings/remove-member-dialog';
import RevokeInvitationDialog from '@/components/settings/revoke-invitation-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatDateTime, formatExpiry } from '@/lib/format';
import { index as membersIndex } from '@/routes/memberships';
import type { Auth, Membership, PendingInvitation } from '@/types';
import { tenantRoleLabels } from '@/types';

type PageProps = {
    auth: Auth;
};

type Props = {
    memberships: Membership[];
    pendingInvitations: PendingInvitation[];
};

function secondsUntil(timestamp: string | null): number {
    if (timestamp === null) {
        return 0;
    }

    const availableAt = new Date(timestamp).getTime();

    if (Number.isNaN(availableAt)) {
        return 0;
    }

    return Math.max(0, Math.ceil((availableAt - Date.now()) / 1_000));
}

function ResendInvitationForm({
    invitation,
}: {
    invitation: PendingInvitation;
}) {
    const [remainingSeconds, setRemainingSeconds] = useState(() =>
        secondsUntil(invitation.resendAvailableAt),
    );

    useEffect(() => {
        if (secondsUntil(invitation.resendAvailableAt) === 0) {
            return;
        }

        const interval = window.setInterval(() => {
            const nextRemaining = secondsUntil(invitation.resendAvailableAt);

            setRemainingSeconds(nextRemaining);

            if (nextRemaining === 0) {
                window.clearInterval(interval);
            }
        }, 250);

        return () => window.clearInterval(interval);
    }, [invitation.resendAvailableAt]);

    return (
        <Form
            {...InvitationController.resend.form({
                invitation: invitation.id,
            })}
            options={{ preserveScroll: true }}
        >
            {({ processing }) => (
                <Button
                    type="submit"
                    size="sm"
                    variant="outline"
                    disabled={processing || remainingSeconds > 0}
                    data-test={`resend-invitation-${invitation.id}`}
                >
                    {remainingSeconds > 0
                        ? `Resend in ${remainingSeconds}s`
                        : 'Resend'}
                </Button>
            )}
        </Form>
    );
}

export default function Members({ memberships, pendingInvitations }: Props) {
    const { auth } = usePage<PageProps>().props;

    return (
        <>
            <Head title="Members" />

            <h1 className="sr-only">Members</h1>

            <div className="space-y-8">
                <div className="space-y-3">
                    <div className="flex flex-wrap items-center justify-between gap-4">
                        <Heading
                            variant="small"
                            title="Members"
                            description={`${memberships.length} member${memberships.length === 1 ? '' : 's'} · ${pendingInvitations.length} invitation${pendingInvitations.length === 1 ? '' : 's'}`}
                        />

                        <InviteMemberDialog />
                    </div>

                    {memberships.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Person</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>Joined</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {memberships.map((membership) => {
                                    const isYou =
                                        membership.user.id === auth.user?.id;
                                    const canChangeRole =
                                        membership.capabilities.assignableRoles.some(
                                            (role) => role !== membership.role,
                                        );
                                    const canRemove =
                                        membership.capabilities.canDelete;

                                    return (
                                        <TableRow key={membership.id}>
                                            <TableCell>
                                                <div className="font-medium">
                                                    {membership.user.name}
                                                    {isYou && (
                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                            you
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="text-sm text-muted-foreground">
                                                    {membership.user.email}
                                                </div>
                                            </TableCell>
                                            <TableCell>
                                                <Badge
                                                    variant={
                                                        membership.role ===
                                                        'owner'
                                                            ? 'default'
                                                            : 'secondary'
                                                    }
                                                >
                                                    {
                                                        tenantRoleLabels[
                                                            membership.role
                                                        ]
                                                    }
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDateTime(
                                                    membership.joinedAt,
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    {canChangeRole && (
                                                        <ChangeRoleDialog
                                                            membership={
                                                                membership
                                                            }
                                                        />
                                                    )}
                                                    {canRemove && (
                                                        <RemoveMemberDialog
                                                            membership={
                                                                membership
                                                            }
                                                        />
                                                    )}
                                                    {!canChangeRole &&
                                                        !canRemove && (
                                                            <span
                                                                className="text-sm text-muted-foreground"
                                                                data-test={`membership-locked-${membership.id}`}
                                                            >
                                                                — not yours to
                                                                manage
                                                            </span>
                                                        )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No members yet.
                        </p>
                    )}
                </div>

                <div className="space-y-3">
                    <Heading
                        variant="small"
                        title="Invitations"
                        description="An invitation only becomes a membership once it's accepted."
                    />

                    {pendingInvitations.length > 0 ? (
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Invited address</TableHead>
                                    <TableHead>Role</TableHead>
                                    <TableHead>State</TableHead>
                                    <TableHead className="text-right">
                                        Actions
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {pendingInvitations.map((invitation) => (
                                    <TableRow key={invitation.id}>
                                        <TableCell>
                                            <div className="font-medium">
                                                {invitation.email}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                Invited by{' '}
                                                {invitation.invitedBy?.name ??
                                                    'a former member'}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">
                                                {
                                                    tenantRoleLabels[
                                                        invitation.role
                                                    ]
                                                }
                                            </Badge>
                                        </TableCell>
                                        <TableCell className="text-muted-foreground">
                                            Sent · expires{' '}
                                            {formatExpiry(invitation.expiresAt)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-2">
                                                {invitation.capabilities
                                                    .canResend && (
                                                    <ResendInvitationForm
                                                        key={`${invitation.id}-${invitation.resendAvailableAt}`}
                                                        invitation={invitation}
                                                    />
                                                )}
                                                {invitation.capabilities
                                                    .canRevoke && (
                                                    <RevokeInvitationDialog
                                                        invitation={invitation}
                                                    />
                                                )}
                                                {!invitation.capabilities
                                                    .canResend &&
                                                    !invitation.capabilities
                                                        .canRevoke && (
                                                        <span
                                                            className="text-sm text-muted-foreground"
                                                            data-test={`invitation-locked-${invitation.id}`}
                                                        >
                                                            — not yours to
                                                            manage
                                                        </span>
                                                    )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No pending invitations yet.
                        </p>
                    )}
                </div>
            </div>
        </>
    );
}

Members.layout = {
    breadcrumbs: [
        {
            title: 'Settings',
            href: membersIndex(),
        },
    ],
};
