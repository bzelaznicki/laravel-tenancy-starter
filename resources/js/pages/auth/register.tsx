import { Form, Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Field, FieldDescription } from '@/components/ui/field';
import { Input } from '@/components/ui/input';
import {
    InputGroup,
    InputGroupAddon,
    InputGroupText,
    InputGroupInput,
} from '@/components/ui/input-group';

import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register';
import { find as findTenant } from '@/routes/tenant';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    const { appDomain } = usePage().props;
    const [subdomain, setSubdomain] = useState<string>('');
    const [subdomainEdited, setSubdomainEdited] = useState<boolean>(false);
    const registrationRoute = store();
    const registrationUrl = new URL(registrationRoute.url, 'https://localhost');
    const registrationAction = {
        ...registrationRoute,
        url: `${registrationUrl.pathname}${registrationUrl.search}`,
    };

    const suggestSubdomain = (organization: string): string =>
        organization
            .toLowerCase()
            .trim()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');

    return (
        <>
            <Head title="Create your workspace" />
            <Form
                action={registrationAction}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">Full name</Label>
                                <Input
                                    id="name"
                                    type="text"
                                    required
                                    autoFocus
                                    tabIndex={1}
                                    autoComplete="name"
                                    name="name"
                                    placeholder="Full name"
                                />
                                <InputError
                                    message={errors.name}
                                    className="mt-2"
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="email">Work email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    required
                                    tabIndex={2}
                                    autoComplete="email"
                                    name="email"
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <PasswordInput
                                    id="password"
                                    required
                                    tabIndex={3}
                                    autoComplete="new-password"
                                    name="password"
                                    placeholder="Password"
                                    passwordrules={passwordRules}
                                />
                                <InputError message={errors.password} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="password_confirmation">
                                    Confirm password
                                </Label>
                                <PasswordInput
                                    id="password_confirmation"
                                    required
                                    tabIndex={4}
                                    autoComplete="new-password"
                                    name="password_confirmation"
                                    placeholder="Confirm password"
                                    passwordrules={passwordRules}
                                />
                                <InputError
                                    message={errors.password_confirmation}
                                />
                            </div>

                            <div className="grid gap-2">
                                <Field>
                                    <Label htmlFor="organization">
                                        Organization
                                    </Label>
                                    <Input
                                        id="organization"
                                        type="text"
                                        required
                                        tabIndex={5}
                                        autoComplete="organization"
                                        name="organization"
                                        placeholder="Organization name"
                                        onChange={(e) => {
                                            if (!subdomainEdited) {
                                                setSubdomain(
                                                    suggestSubdomain(
                                                        e.target.value,
                                                    ),
                                                );
                                            }
                                        }}
                                    />
                                    <InputError message={errors.organization} />
                                </Field>
                            </div>
                            <div className="grid gap-2">
                                <Field>
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
                                            aria-invalid={Boolean(
                                                errors.subdomain,
                                            )}
                                            name="subdomain"
                                            id="subdomain"
                                            required
                                            tabIndex={6}
                                            value={subdomain}
                                            onChange={(e) => {
                                                setSubdomainEdited(true);
                                                setSubdomain(e.target.value);
                                            }}
                                        />
                                        <InputGroupAddon align="inline-end">
                                            <InputGroupText>
                                                .{appDomain}
                                            </InputGroupText>
                                        </InputGroupAddon>
                                    </InputGroup>
                                    <FieldDescription>
                                        This is where your team signs in. It
                                        becomes part of every invitation link
                                        and cannot be changed later.
                                    </FieldDescription>
                                </Field>
                                <InputError message={errors.subdomain} />
                            </div>
                            <Button
                                type="submit"
                                className="mt-2 w-full"
                                tabIndex={7}
                                data-test="register-user-button"
                            >
                                {processing && <Spinner />}
                                Create workspace
                            </Button>
                        </div>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have one?{' '}
                            <TextLink href={findTenant()} tabIndex={8}>
                                Find your workspace
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create your workspace',
    description:
        'One workspace per company. You will be its owner, and you can invite the rest of the team straight afterwards.',
};
