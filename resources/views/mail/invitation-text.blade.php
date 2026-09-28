{!! $inviterName !!} ({!! $inviterEmail !!}) invited you to {!! $tenantName !!} on {!! config('app.name') !!}.
@if ($inviteMessage !== null)

{!! $inviterName !!} wrote:

  "{!! $inviteMessage !!}"
@endif

You'll join as {!! $roleArticle !!} {!! $role !!}.

Accept: {!! $url !!}

That's the workspace's own address — sign in there from now on.
Expires {!! $expiresAt !!}.
If you weren't expecting this, ignore the message.
