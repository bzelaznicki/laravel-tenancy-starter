import type { TenantRole } from './tenant';

export type User = {
    id: string;
    name: string;
    email: string;
    emailVerified: boolean;
};

export type Auth = {
    user: User | null;
    tenant: {
        name: string;
    } | null;
    role: TenantRole | null;
    membershipCapabilities: {
        assignableRoles: TenantRole[];
    };
};

/* @chisel-passkeys */
export type Passkey = {
    id: number;
    name: string;
    authenticator: string | null;
    created_at_diff: string;
    last_used_at_diff: string | null;
};
/* @end-chisel-passkeys */

export type TwoFactorSetupData = {
    svg: string;
    url: string;
};

export type TwoFactorSecretKey = {
    secretKey: string;
};
