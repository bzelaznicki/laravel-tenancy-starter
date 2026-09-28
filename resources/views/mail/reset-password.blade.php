<x-mail.layout heading="Reset your password">
<x-mail.paragraph>Someone asked to reset the password for <strong style="font-weight:600">{{ $email }}</strong>. If that was you, set a new one now.</x-mail.paragraph>
<x-mail.button :url="$url">Set a new password</x-mail.button>
<p style="margin:18px 0 0;font-size:13px;line-height:1.65;color:#5f5c57">The link expires in {{ $expiresInMinutes }} minutes and can be used once. If you didn't ask for this, nothing has changed and you can ignore the email — but tell your workspace owner if it keeps arriving.</p>
<x-slot:footer>{{ config('app.name') }} · sent to {{ $email }} because a password reset was requested.</x-slot:footer>
</x-mail.layout>
