@props(['url'])
<table cellpadding="0" cellspacing="0" border="0" role="presentation"><tbody><tr><td style="border-radius:5px;background:#3a5f96"><a href="{{ $url }}" style="display:inline-block;padding:11px 22px;font-size:14.5px;font-weight:500;color:#fefefe;text-decoration:none">{{ $slot }}</a></td></tr></tbody></table>
<p style="margin:16px 0 0;font-size:12.5px;line-height:1.6;color:#8d8983">Or paste this into your browser:<br><span style="color:#3a5f96;word-break:break-all">{{ $url }}</span></p>
