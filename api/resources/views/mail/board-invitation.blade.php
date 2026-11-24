<x-mail::message>
# You're invited to {{ $board }}

**{{ $inviter }}** invited you to collaborate on the board **{{ $board }}** in TeamBoard.

<x-mail::button :url="$url">
Join the board
</x-mail::button>

This invitation expires on {{ $expires }}. If you weren't expecting it, you can ignore this email.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
