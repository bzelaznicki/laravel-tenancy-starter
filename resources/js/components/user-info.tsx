import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

export function UserInfo({
    user,
    showEmail = false,
    subtitle,
}: {
    user: User;
    showEmail?: boolean;
    subtitle?: string | null;
}) {
    const getInitials = useInitials();
    const secondLine = showEmail ? user.email : subtitle;

    return (
        <>
            <Avatar className="size-7 overflow-hidden rounded-full">
                <AvatarFallback className="bg-sidebar-accent text-[11px] font-semibold text-foreground">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left leading-tight">
                <span className="truncate text-[13px] font-medium text-foreground">
                    {user.name}
                </span>
                {secondLine && (
                    <span className="truncate text-[11.5px] text-subtle-foreground">
                        {secondLine}
                    </span>
                )}
            </div>
        </>
    );
}
