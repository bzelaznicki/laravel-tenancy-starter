<?php

namespace App\Notifications;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TenantInvitation extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Invitation $invitation, public string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $invitation = $this->invitation;

        $url = tenant_route(
            $invitation->tenant->host(),
            'invitations.accept',
            [
                'invitation' => $invitation->id,
                'token' => $this->token,
            ],
        );

        $role = $invitation->role->label();

        return (new MailMessage)
            ->subject("{$invitation->inviter->name} invited you to {$invitation->tenant->name}")
            ->action('Accept invitation', $url)
            ->view(['html' => 'mail.invitation', 'text' => 'mail.invitation-text'], [
                'url' => $url,
                'tenantName' => $invitation->tenant->name,
                'tenantHost' => parse_url($url, PHP_URL_HOST),
                'inviterName' => $invitation->inviter->name,
                'inviterEmail' => $invitation->inviter->email,
                'role' => $role,
                'roleArticle' => in_array($role[0], ['A', 'E', 'I', 'O', 'U'], true) ? 'an' : 'a',
                'inviteMessage' => filled($invitation->message) ? $invitation->message : null,
                'expiresAt' => $invitation->expires_at->format('j F Y, H:i').' '.$invitation->expires_at->timezoneName,
                'recipientEmail' => $invitation->email,
            ]);
    }
}
