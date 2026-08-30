<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Um único e-mail serve aos dois fluxos descritos no plano — "convite" (usuário
 * recém-criado, ainda sem senha) e "redefinição" (usuário existente) — porque
 * ambos usam o mesmo broker de reset do Laravel. O texto muda conforme
 * `primeiro_acesso`; o mecanismo, não.
 */
class DefinirSenhaNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $convite = (bool) $notifiable->primeiro_acesso;

        $url = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], absolute: false));

        $expira = config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject($convite
                ? 'Seu acesso ao MarinaFlow'
                : 'Redefinição de senha — MarinaFlow')
            ->greeting('Olá, ' . $notifiable->name)
            ->line($convite
                ? 'Uma conta foi criada para você no MarinaFlow. Use o botão abaixo para definir sua senha e ativar o acesso.'
                : 'Recebemos um pedido de redefinição de senha para esta conta.')
            ->action($convite ? 'Definir minha senha' : 'Redefinir senha', url($url))
            ->line("Este link expira em {$expira} minutos e só pode ser usado uma vez.")
            ->line($convite
                ? 'Se você não esperava este convite, ignore este e-mail.'
                : 'Se não foi você quem pediu, ignore este e-mail — sua senha continua a mesma.');
    }
}
