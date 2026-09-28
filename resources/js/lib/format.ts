const accountTimestampFormatter = new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
});

export function formatDateTime(value: string | null | undefined) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return accountTimestampFormatter.format(date);
}

const relativeDayFormatter = new Intl.RelativeTimeFormat(undefined, {
    numeric: 'auto',
});

/**
 * Renders a future deadline the way the invitations table reads it —
 * "in 7 days", "tomorrow", "today".
 */
export function formatExpiry(value: string | null | undefined) {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    const days = Math.round((date.getTime() - Date.now()) / 86_400_000);

    return relativeDayFormatter.format(days, 'day');
}
