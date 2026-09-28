export type AccountContact = {
    id: string;
    contact_id: string;
    name: string;
    email: string;
    role_title: string | null;
    stakeholder_type: StakeholderType;
    is_primary: boolean;
    created_at: string;
    updated_at: string;
};

export const stakeholderTypes = [
    'Champion',
    'DecisionMaker',
    'Admin',
    'TechnicalContact',
    'BillingContact',
    'User',
    'Detractor',
    'Other',
] as const;

export type StakeholderType = (typeof stakeholderTypes)[number];
