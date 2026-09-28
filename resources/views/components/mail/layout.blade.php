@props(['heading', 'footer' => null])
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light dark">
<title>{{ $heading }}</title>
</head>
<body style="margin:0;padding:0;background:#eeece8">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background:#eeece8;font-family:'Instrument Sans',Helvetica,Arial,sans-serif"><tbody><tr><td align="center" style="padding:26px 20px">
<table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width:600px;max-width:100%;background:#fefefe;border:1px solid #e7e4de;border-radius:8px"><tbody>
<tr><td style="padding:22px 32px;border-bottom:1px solid #e7e4de"><table cellpadding="0" cellspacing="0" border="0" role="presentation"><tbody><tr><td style="padding-right:9px"><div style="width:22px;height:22px;border-radius:5px;background:#1c1b19"></div></td><td style="font-size:15px;font-weight:600;color:#1c1b19">{{ config('app.name') }}</td></tr></tbody></table></td></tr>
<tr><td style="padding:30px 32px 26px"><h1 style="margin:0 0 12px;font-size:20px;font-weight:600;color:#1c1b19;letter-spacing:-.01em">{{ $heading }}</h1>
{{ $slot }}
</td></tr>
@isset($aside)
<tr><td style="padding:0 32px 26px">{{ $aside }}</td></tr>
@endisset
@if ($footer)
<tr><td style="padding:18px 32px;border-top:1px solid #e7e4de;background:#faf8f5;border-radius:0 0 8px 8px;font-size:12px;line-height:1.6;color:#8d8983">{{ $footer }}</td></tr>
@endif
</tbody></table></td></tr></tbody></table>
</body>
</html>
