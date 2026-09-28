@php($strong = 'font-weight:600;color:#1c1b19')
<x-mail.layout :heading="'Join '.$tenantName.' on '.config('app.name')">
<x-mail.paragraph>{{ $inviterName }} ({{ $inviterEmail }}) invited you to the <strong style="font-weight:600">{{ $tenantName }}</strong> workspace as {{ $roleArticle }} <strong style="font-weight:600">{{ $role }}</strong>.</x-mail.paragraph>
@if ($inviteMessage !== null)
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 0 20px"><tbody><tr><td style="padding:2px 0 2px 16px;border-left:3px solid #d5d1c9;font-size:14.5px;line-height:1.65;color:#3d3a36;font-style:italic">“{{ $inviteMessage }}”<div style="font-style:normal;font-size:12.5px;color:#8d8983;padding-top:7px">{{ $inviterName }}</div></td></tr></tbody></table>
@endif
<x-mail.button :url="$url">Accept invitation</x-mail.button>
<x-slot:aside>
<x-mail.note>The link goes to <strong style="{{ $strong }}">{{ $tenantHost }}</strong> — the workspace's own address, which is where you'll sign in from now on. It expires on <strong style="{{ $strong }}">{{ $expiresAt }}</strong>. If you weren't expecting it, ignore this message — you won't join the workspace unless you accept.</x-mail.note>
</x-slot:aside>
<x-slot:footer>{{ config('app.name') }} · sent to {{ $recipientEmail }} because someone invited you to a workspace.</x-slot:footer>
</x-mail.layout>
