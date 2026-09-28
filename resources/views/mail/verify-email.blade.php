<x-mail.layout heading="Confirm your email address">
<x-mail.paragraph>Confirm <strong style="font-weight:600">{{ $email }}</strong> to finish setting up your {{ config('app.name') }} account.</x-mail.paragraph>
<x-mail.button :url="$url">Confirm address</x-mail.button>
<p style="margin:18px 0 0;font-size:13px;line-height:1.65;color:#5f5c57">The link expires in {{ $expiresInMinutes }} minutes.</p>
<x-slot:footer>Didn't sign up? You can ignore this email.</x-slot:footer>
</x-mail.layout>
