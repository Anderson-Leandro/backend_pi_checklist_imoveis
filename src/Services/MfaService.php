<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\Uuid;
use App\Models\UsuarioModel;
use OTPHP\TOTP;

class MfaService
{
    private const NOME_APP = 'One Check';

    public function __construct(
        private readonly UsuarioModel $usuarioModel,
        private readonly LogService   $logService
    ) {}

    public function configurar(string $usuarioId, string $email): array
    {
        $usuario = $this->usuarioModel->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $totp = TOTP::generate();
        $totp->setLabel($email);
        $totp->setIssuer(self::NOME_APP);

        $this->usuarioModel->atualizarMfaSecret($usuarioId, $totp->getSecret());

        return ['otpauth_uri' => $totp->getProvisioningUri()];
    }

    public function ativar(string $usuarioId, string $codigo): void
    {
        $secret = $this->usuarioModel->buscarMfaSecretPorId($usuarioId);

        if ($secret === null) {
            throw new NaoEncontradoException('Configuração MFA');
        }

        if (!$this->verificar($secret, $codigo)) {
            throw new RegraDeNegocioException('Código MFA inválido', 401);
        }

        $this->usuarioModel->ativarMfa($usuarioId);

        $this->logService->registrarComUsuario(
            acao:       'MFA_ATIVADO',
            entidade:   'usuario',
            entidadeId: $usuarioId,
            payload:    [],
            usuarioId:  $usuarioId,
        );
    }

    /**
     * Habilita o MFA para um usuário (não limpa o secret existente).
     * Se já havia secret, o usuário poderá usar o mesmo app autenticador.
     * Se não havia secret, o próximo login exigirá a configuração do TOTP.
     *
     * @throws NaoEncontradoException Se o usuário não existir
     */
    public function habilitar(string $usuarioId): void
    {
        $usuario = $this->usuarioModel->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $this->usuarioModel->ativarMfa($usuarioId);

        $this->logService->registrar(
            acao:       'MFA_HABILITADO',
            entidade:   'usuario',
            entidadeId: $usuarioId,
        );
    }

    /**
     * Desativa o MFA e apaga o secret TOTP do usuário.
     * Na próxima ativação o usuário precisará reconfigurar o autenticador.
     *
     * @throws ValidacaoException     Se o UUID for inválido
     * @throws NaoEncontradoException Se o usuário não existir
     */
    public function desativar(string $usuarioId): void
    {
        if (!Uuid::valido($usuarioId)) {
            throw new ValidacaoException(['usuario_id' => 'O campo usuario_id deve ser um UUID v4 válido.']);
        }

        $usuario = $this->usuarioModel->buscarPorId($usuarioId);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $this->usuarioModel->desativarMfa($usuarioId);

        $this->logService->registrar(
            acao:       'MFA_DESATIVADO',
            entidade:   'usuario',
            entidadeId: $usuarioId,
        );
    }

    public function verificar(string $secret, string $codigo): bool
    {
        $totp = TOTP::createFromSecret($secret);
        return $totp->verify($codigo, null, 1);
    }
}
