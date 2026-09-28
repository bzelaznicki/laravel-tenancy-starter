import { Form, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type { Auth, TenantRole } from '@/types';
import { tenantRoleDescriptions, tenantRoleLabels } from '@/types';

type PageProps = {
    auth: Auth;
};

/**
 * Member is the role most invitations are for, so prefer it when the actor may
 * grant it and fall back to the least privileged role they can.
 */
function defaultRole(assignableRoles: TenantRole[]): TenantRole {
    return assignableRoles.includes('member')
        ? 'member'
        : assignableRoles[assignableRoles.length - 1];
}

export default function InviteMemberDialog() {
    const { auth } = usePage<PageProps>().props;
    const assignableRoles = auth.membershipCapabilities.assignableRoles;
    const [open, setOpen] = useState(false);
    const [email, setEmail] = useState('');
    const [role, setRole] = useState<TenantRole>(defaultRole(assignableRoles));

    if (assignableRoles.length === 0) {
        return null;
    }

    const emailLooksValid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.trim());

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setEmail('');
                    setRole(defaultRole(assignableRoles));
                }
            }}
        >
            <DialogTrigger asChild>
                <Button size="sm" data-test="invite-member">
                    Invite people
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Invite someone to {auth.tenant?.name}</DialogTitle>
                <DialogDescription>
                    They get an email with a link that only works once and
                    expires in 7 days. Nobody joins until they accept.
                </DialogDescription>

                <Form
                    {...InvitationController.store.form()}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="invite-email">
                                    Email address
                                </Label>
                                <Input
                                    id="invite-email"
                                    type="email"
                                    name="email"
                                    required
                                    autoFocus
                                    autoComplete="off"
                                    placeholder="name@example.com"
                                    value={email}
                                    onChange={(event) =>
                                        setEmail(event.target.value)
                                    }
                                    aria-invalid={Boolean(errors.email)}
                                    data-test="invite-email"
                                />
                                <FieldDescription>
                                    One person per invitation.
                                </FieldDescription>
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="invite-role">
                                    Role on acceptance
                                </Label>
                                <Select
                                    name="role"
                                    value={role}
                                    onValueChange={(value) =>
                                        setRole(value as TenantRole)
                                    }
                                >
                                    <SelectTrigger
                                        id="invite-role"
                                        className="w-full"
                                        data-test="invite-role"
                                    >
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {assignableRoles.map(
                                            (assignableRole) => (
                                                <SelectItem
                                                    key={assignableRole}
                                                    value={assignableRole}
                                                >
                                                    {
                                                        tenantRoleLabels[
                                                            assignableRole
                                                        ]
                                                    }
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <FieldDescription>
                                    {tenantRoleDescriptions[role]}
                                </FieldDescription>
                                <InputError message={errors.role} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="invite-message">
                                    Note in the email{' '}
                                    <span className="font-normal text-muted-foreground">
                                        optional
                                    </span>
                                </Label>
                                <Textarea
                                    id="invite-message"
                                    name="message"
                                    rows={3}
                                    maxLength={255}
                                    placeholder="Say why you're inviting them — it appears above the button."
                                    data-test="invite-message"
                                />
                                <InputError message={errors.message} />
                            </div>

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={processing || !emailLooksValid}
                                    data-test="confirm-invite-button"
                                >
                                    Send invitation
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
