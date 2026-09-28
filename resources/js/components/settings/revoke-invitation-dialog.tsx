import { Form } from '@inertiajs/react';
import { useState } from 'react';
import InvitationController from '@/actions/App/Http/Controllers/InvitationController';
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
import type { PendingInvitation } from '@/types';

type Props = {
    invitation: PendingInvitation;
};

export default function RevokeInvitationDialog({ invitation }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    data-test={`revoke-invitation-${invitation.id}`}
                >
                    Revoke
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>
                    Revoke the invitation to {invitation.email}?
                </DialogTitle>
                <DialogDescription>
                    The link stops working immediately, even if the email is
                    already open. Sending a new invitation is the only way back.
                </DialogDescription>

                <Form
                    {...InvitationController.destroy.form({
                        invitation: invitation.id,
                    })}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setOpen(false)}
                >
                    {({ processing }) => (
                        <DialogFooter className="gap-2">
                            <DialogClose asChild>
                                <Button variant="secondary">Cancel</Button>
                            </DialogClose>

                            <Button
                                type="submit"
                                variant="destructive"
                                disabled={processing}
                                data-test="confirm-revoke-invitation-button"
                            >
                                Revoke invitation
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
