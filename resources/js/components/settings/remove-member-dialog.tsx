import { Form } from '@inertiajs/react';
import { useState } from 'react';
import MembershipController from '@/actions/App/Http/Controllers/MembershipController';
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
import type { Membership } from '@/types';

type Props = {
    membership: Membership;
};

export default function RemoveMemberDialog({ membership }: Props) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button
                    size="sm"
                    variant="outline"
                    data-test={`remove-member-${membership.id}`}
                >
                    Remove
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogTitle>Remove {membership.user.name}?</DialogTitle>
                <DialogDescription>
                    They lose access immediately. This can&apos;t be undone.
                </DialogDescription>

                <Form
                    {...MembershipController.destroy.form({
                        membership: membership.id,
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
                                data-test="confirm-remove-member-button"
                            >
                                Remove member
                            </Button>
                        </DialogFooter>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
