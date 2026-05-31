<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\UsuarioModel;

class NotificationService
{
    public function __construct(
        private readonly UsuarioModel $usuarioModel
    ) {}

    /**
     * Envia notificação por e-mail para um usuário específico.
     *
     * @param string $usuarioId UUID do destinatário
     * @param string $assunto   Assunto do e-mail
     * @param string $mensagem  Corpo do e-mail em texto plano
     */
    public function enviar(string $usuarioId, string $assunto, string $mensagem): void
    {
        $usuario = $this->usuarioModel->buscarPorId($usuarioId);
        if ($usuario === null) {
            return;
        }

        $this->disparar($usuario['email'], $assunto, $mensagem);
    }

    /**
     * Envia notificação para todos os admins ativos.
     *
     * @param string $assunto  Assunto do e-mail
     * @param string $mensagem Corpo do e-mail em texto plano
     */
    public function notificarAdmins(string $assunto, string $mensagem): void
    {
        $admins = $this->usuarioModel->buscarPorRole('admin');
        foreach ($admins as $admin) {
            $this->disparar($admin['email'], $assunto, $mensagem);
        }
    }

    private function disparar(string $destinatario, string $assunto, string $mensagem): void
    {
        $remetente = 'noreply@' . ($_SERVER['HTTP_HOST'] ?? 'onecheck.local');
        $cabecalhos = implode("\r\n", [
            "From: One Check <{$remetente}>",
            'Content-Type: text/plain; charset=utf-8',
            'X-Mailer: OneCheck-API',
        ]);

        @mail($destinatario, $assunto, $mensagem, $cabecalhos);
    }
}
