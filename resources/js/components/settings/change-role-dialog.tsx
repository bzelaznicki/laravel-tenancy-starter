import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MembershipController from '@/actions/App/Http/Controllers/MembershipController';
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { Membership, TenantRole } from '@/types';
import { tenantRoleLabels } from '@/types';

type Props = {
    membership: Membership;
};

export default function ChangeRoleDialog({ membership }: Props) {
    const [open, setOpen] = useState(false);
    const [role, setRole] = useState<TenantRole>(membership.role);

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                setOpen(next);

                if (next) {
                    setRole(membership.role);
                }
            }}
        >
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    data-test={`change-role-${membership.id}`}
                >
                    Change role
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Change {membership.user.name}&apos;s role
                </DialogTitle>
                <DialogDescription>
                    Currently {tenantRoleLabels[membership.role]}. A role change
                    takes effect on their next request.
                </DialogDescription>

                <Form
                    {...MembershipController.update.form({
                        membership: membership.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                    className="space-y-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Select
                                name="role"
                                value={role}
                                onValueChange={(value) =>
                                    setRole(value as TenantRole)
                                }
                            >
                                <SelectTrigger className="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {membership.capabilities.assignableRoles.map(
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

                            <InputError message={errors.role} />

                            <DialogFooter className="gap-2">
                                <DialogClose asChild>
                                    <Button variant="secondary">Cancel</Button>
                                </DialogClose>

                                <Button
                                    type="submit"
                                    disabled={
                                        processing || role === membership.role
                                    }
                                    data-test="confirm-change-role-button"
                                >
                                    Save role
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
