export const tenantRoles = ['owner', 'admin', 'member', 'viewer'] as const;

export type TenantRole = (typeof tenantRoles)[number];

export const tenantRoleLabels: Record<TenantRole, string> = {
    owner: 'Owner',
    admin: 'Admin',
    member: 'Member',
    viewer: 'Viewer',
};

export type MembershipCapabilities = {
    assignableRoles: TenantRole[];
    canDelete: boolean;
};

export type Membership = {
    id: number;
    role: TenantRole;
    joinedAt: string | null;
    user: {
        id: string;
        name: string;
        email: string;
    };
    capabilities: MembershipCapabilities;
};

export const tenantRoleBlurbs: Record<TenantRole, string> = {
    owner: "you'll have full control of the workspace, including billing and ownership.",
    admin: "you'll manage members and workspace settings.",
    member: "you'll work day to day in the workspace.",
    viewer: "you'll have read-only access to the workspace.",
};

export const invitationAcceptanceModes = [
    'register',
    'confirm',
    'sign_in',
] as const;

export type InvitationAcceptanceMode =
    (typeof invitationAcceptanceModes)[number];

export type InvitationInvite = {
    id: string;
    email: string;
    message: string | null;
    role: TenantRole;
    expiresAt: string;
    invitedBy: { name: string } | null;
    tenant: { name: string };
};

/** Third-person, for describing a role to whoever is granting it. */
export const tenantRoleDescriptions: Record<TenantRole, string> = {
    owner: 'Full control of the workspace, including billing and ownership.',
    admin: 'Manages members and workspace settings.',
    member: 'Works day to day in the workspace.',
    viewer: 'Read-only access to the workspace.',
};

export type InvitationCapabilities = {
    canRevoke: boolean;
    canResend: boolean;
};

export type PendingInvitation = {
    id: string;
    email: string;
    role: TenantRole;
    invitedAt: string | null;
    expiresAt: string;
    resendAvailableAt: string | null;
    invitedBy: { name: string } | null;
    capabilities: InvitationCapabilities;
};
