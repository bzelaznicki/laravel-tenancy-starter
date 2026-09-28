import { Form, Head, usePage } from '@inertiajs/react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupText,
    InputGroupInput,
} from '@/components/ui/input-group';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { redirect } from '@/routes/tenant';

export default function FindTenant() {
    const { appDomain } = usePage().props;

    return (
        <>
            <Head title="Find your workspace" />

            <Form {...redirect.form()} className="flex flex-col gap-6">
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="subdomain">
                                    Workspace address
                                </Label>
                                <InputGroup>
                                    <InputGroupAddon>
                                        <InputGroupText>
                                            https://
                                        </InputGroupText>
                                    </InputGroupAddon>
                                    <InputGroupInput
                                        placeholder="acme"
                                        className="pl-0.5!"
                                        aria-invalid={Boolean(errors.subdomain)}
                                        name="subdomain"
                                        id="subdomain"
                                    />
                                    <InputGroupAddon align="inline-end">
                                        <InputGroupText>
                                            .{appDomain}
                                        </InputGroupText>
                                    </InputGroupAddon>
                                </InputGroup>
                                <InputError message={errors.subdomain} />
                            </div>

                            <Button
                                type="submit"
                                className="mt-4 w-full"
                                disabled={processing}
                                data-test="find-tenant-button"
                            >
                                {processing && <Spinner />}
                                Go to your workspace
                            </Button>
                        </div>

                        <div className="border-t pt-5 text-[12.5px] leading-relaxed text-muted-foreground">
                            Workspaces are invite-only, so there is nothing to
                            browse or request here. Starting fresh instead?{' '}
                            <TextLink href={register()}>
                                Create a workspace
                            </TextLink>
                            .
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

FindTenant.layout = {
    title: 'Find your workspace',
    description: 'Enter your workspace address to log in.',
};
