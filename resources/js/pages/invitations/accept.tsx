import { Form, Head, Link, setLayoutProps } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatDateTime } from '@/lib/format';
import { login } from '@/routes';
import { store as acceptInvitation } from '@/routes/invitations/accept';
import type { InvitationAcceptanceMode, InvitationInvite } from '@/types';
import { tenantRoleBlurbs, tenantRoleLabels } from '@/types';

type Props = {
    invitation: InvitationInvite;
    token: string;
    mode: InvitationAcceptanceMode;
    passwordRules: string;
};

function SummaryRow({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-4 border-t border-border py-2.5 text-sm">
            <span className="text-muted-foreground">{label}</span>
            <span className="font-medium">{value}</span>
        </div>
    );
}

function InvitationMessage({ message }: { message: string }) {
    return (
        <blockquote className="border-l-2 border-border py-0.5 pl-3 text-sm text-muted-foreground">
            {message}
        </blockquote>
    );
}

export default function AcceptInvitation({
    invitation,
    token,
    mode,
    passwordRules,
}: Props) {
    const roleLabel = tenantRoleLabels[invitation.role];
    const tenantName = invitation.tenant.name;

    setLayoutProps(
        mode === 'register'
            ? {
                  title: invitation.invitedBy
                      ? `${invitation.invitedBy.name} invited you to ${tenantName}`
                      : `You have been invited to join ${tenantName}`,
                  description: `Create your account to join as ${roleLabel} — ${tenantRoleBlurbs[invitation.role]}`,
              }
            : {
                  title: `Join ${tenantName} as ${roleLabel}`,
                  description: invitation.invitedBy
                      ? `${invitation.invitedBy.name} invited ${invitation.email} to ${tenantName}.`
                      : `${invitation.email} was invited to ${tenantName}.`,
              },
    );

    return (
        <>
            <Head title="Workspace invitation" />

            <div className="space-y-6">
                {invitation.message && (
                    <InvitationMessage message={invitation.message} />
                )}

                {mode === 'register' && (
                    <Form
                        {...acceptInvitation.form(invitation.id)}
                        resetOnSuccess={['password', 'password_confirmation']}
                        disableWhileProcessing
                        className="flex flex-col gap-6"
                    >
                        {({ processing, errors }) => (
                            <div className="grid gap-6">
                                <input
                                    type="hidden"
                                    name="token"
                                    value={token}
                                />

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={invitation.email}
                                        readOnly
                                        tabIndex={-1}
                                        aria-readonly="true"
                                        autoComplete="email"
                                        className="bg-muted text-muted-foreground"
                                    />
                                    <FieldDescription>
                                        Fixed by the invitation.
                                    </FieldDescription>
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="name">Full name</Label>
                                    <Input
                                        id="name"
                                        type="text"
                                        name="name"
                                        required
                                        autoFocus
                                        tabIndex={1}
                                        autoComplete="name"
                                        placeholder="Full name"
                                        data-test="invitation-name"
                                    />
                                    <InputError message={errors.name} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password">Password</Label>
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        tabIndex={2}
                                        autoComplete="new-password"
                                        placeholder="Password"
                                        passwordrules={passwordRules}
                                        data-test="invitation-password"
                                    />
                                    <InputError message={errors.password} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="password_confirmation">
                                        Confirm password
                                    </Label>
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        required
                                        tabIndex={3}
                                        autoComplete="new-password"
                                        placeholder="Confirm password"
                                        passwordrules={passwordRules}
                                        data-test="invitation-password-confirmation"
                                    />
                                    <InputError
                                        message={errors.password_confirmation}
                                    />
                                </div>

                                <Button
                                    type="submit"
                                    className="w-full"
                                    tabIndex={4}
                                    data-test="accept-invitation-button"
                                >
                                    {processing && <Spinner />}
                                    Accept and create account
                                </Button>
                            </div>
                        )}
                    </Form>
                )}

                {mode === 'confirm' && (
                    <>
                        <div>
                            <SummaryRow
                                label="Signed in as"
                                value={invitation.email}
                            />
                            <SummaryRow label="Joining" value={tenantName} />
                            <SummaryRow label="Role" value={roleLabel} />
                        </div>

                        <Form
                            {...acceptInvitation.form(invitation.id)}
                            disableWhileProcessing
                        >
                            {({ processing }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="token"
                                        value={token}
                                    />
                                    <Button
                                        type="submit"
                                        className="w-full"
                                        data-test="accept-invitation-button"
                                    >
                                        {processing && <Spinner />}
                                        Accept invitation
                                    </Button>
                                </>
                            )}
                        </Form>

                        <p className="text-sm text-muted-foreground">
                            Accepting adds {tenantName} to your existing
                            account. Nothing about your other workspaces
                            changes.
                        </p>
                    </>
                )}

                {mode === 'sign_in' && (
                    <>
                        <div>
                            <SummaryRow
                                label="Invitation for"
                                value={invitation.email}
                            />
                            <SummaryRow label="Joining" value={tenantName} />
                            <SummaryRow label="Role" value={roleLabel} />
                        </div>

                        <Button
                            asChild
                            className="w-full"
                            data-test="sign-in-to-accept-button"
                        >
                            <Link href={login()}>Sign in to accept</Link>
                        </Button>

                        <p className="text-sm text-muted-foreground">
                            {invitation.email} already has an account. Sign in
                            as {invitation.email} to accept — the invitation
                            will not attach to any other account.
                        </p>
                    </>
                )}

                <div className="flex items-center justify-between gap-4 text-xs text-muted-foreground">
                    <span>Expires {formatDateTime(invitation.expiresAt)}.</span>
                    <Badge variant="outline">{roleLabel}</Badge>
                </div>
            </div>
        </>
    );
}
