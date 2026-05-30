<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\NaoAutorizadoException;
use App\Exceptions\NaoEncontradoException;
use App\Exceptions\RegraDeNegocioException;
use App\Exceptions\ValidacaoException;
use App\Helpers\UsuarioAutenticado;
use App\Helpers\Uuid;
use App\Helpers\Validator;
use App\Models\UsuarioModel;

class UsuarioService
{
    public function __construct(
        private readonly UsuarioModel $usuarioModel,
        private readonly LogService   $logService
    ) {}

    /**
     * Cria um novo usuário no sistema.
     *
     * @param  array{nome: string, email: string, senha: string, role: string} $dados
     * @return array{id: string, nome: string, email: string, role: string, mfa_ativo: int, created_at: string}
     * @throws ValidacaoException      Se os dados de entrada forem inválidos
     * @throws RegraDeNegocioException Se o e-mail já estiver cadastrado
     */
    public function criar(array $dados): array
    {
        $erros = Validator::validar($dados, [
            'nome'  => 'obrigatorio|min:3|max:150',
            'email' => 'obrigatorio|email',
            'senha' => 'obrigatorio|min:8',
            'role'  => 'obrigatorio|enum:admin,vistoriador,locatario',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        if ($this->usuarioModel->emailJaCadastrado($dados['email'])) {
            throw new RegraDeNegocioException('Este e-mail já está cadastrado');
        }

        $novoId = Uuid::gerar();
        $this->usuarioModel->inserir([
            'id'         => $novoId,
            'nome'       => $dados['nome'],
            'email'      => $dados['email'],
            'senha_hash' => password_hash($dados['senha'], PASSWORD_BCRYPT),
            'role'       => $dados['role'],
        ]);

        $novoUsuario = $this->usuarioModel->buscarPorId($novoId);

        $this->logService->registrar(
            acao:       'CREATE',
            entidade:   'usuario',
            entidadeId: $novoId,
            payload:    ['nome' => $dados['nome'], 'email' => $dados['email'], 'role' => $dados['role']],
        );

        return $novoUsuario;
    }

    /**
     * Lista usuários ativos com paginação e filtro opcional por role.
     *
     * @return array{itens: list<array>, total: int, pagina: int, itensPorPagina: int}
     */
    public function listar(int $pagina, int $itensPorPagina, string|null $role): array
    {
        $pagina         = max(1, $pagina);
        $itensPorPagina = max(1, min(100, $itensPorPagina));

        $itens = $this->usuarioModel->listar($pagina, $itensPorPagina, $role);
        $total = $this->usuarioModel->contar($role);

        return compact('itens', 'total', 'pagina', 'itensPorPagina');
    }

    /**
     * Busca um usuário ativo pelo ID.
     *
     * @return array{id: string, nome: string, email: string, role: string, mfa_ativo: int, created_at: string}
     * @throws NaoEncontradoException Se o usuário não existir ou estiver inativo
     */
    public function buscarPorId(string $id): array
    {
        $usuario = $this->usuarioModel->buscarPorId($id);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        return $usuario;
    }

    /**
     * Atualiza dados de um usuário existente.
     *
     * @param  array{nome?: string, email?: string, role?: string} $dados
     * @return array{id: string, nome: string, email: string, role: string, mfa_ativo: int, created_at: string}
     * @throws NaoEncontradoException  Se o usuário não existir
     * @throws ValidacaoException      Se os dados forem inválidos
     * @throws RegraDeNegocioException Se o e-mail já estiver em uso por outro usuário
     */
    public function atualizar(string $id, array $dados): array
    {
        if ($this->usuarioModel->buscarPorId($id) === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $regras = [];
        if (isset($dados['nome']))  $regras['nome']  = 'min:3|max:150';
        if (isset($dados['email'])) $regras['email'] = 'email';
        if (isset($dados['role']))  $regras['role']  = 'enum:admin,vistoriador,locatario';

        if (!empty($regras)) {
            $erros = Validator::validar($dados, $regras);
            if (!empty($erros)) {
                throw new ValidacaoException($erros);
            }
        }

        if (isset($dados['email']) && $this->usuarioModel->emailJaCadastrado($dados['email'], $id)) {
            throw new RegraDeNegocioException('Este e-mail já está cadastrado por outro usuário');
        }

        $camposPermitidos = ['nome', 'email', 'role'];
        $campos = array_filter(
            array_intersect_key($dados, array_flip($camposPermitidos)),
            fn($valor) => $valor !== null && $valor !== ''
        );

        if (!empty($campos)) {
            $this->usuarioModel->atualizar($id, $campos);
        }

        $usuarioAtualizado = $this->usuarioModel->buscarPorId($id);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'usuario',
            entidadeId: $id,
            payload:    $campos,
        );

        return $usuarioAtualizado;
    }

    /**
     * Desativa um usuário via soft delete.
     *
     * @throws NaoEncontradoException  Se o usuário não existir
     * @throws RegraDeNegocioException Se tentar desativar a própria conta
     */
    public function excluir(string $id): void
    {
        $usuarioAutenticado = UsuarioAutenticado::obterOuFalhar();

        if ($usuarioAutenticado['id'] === $id) {
            throw new RegraDeNegocioException('Não é possível desativar a própria conta');
        }

        $usuario = $this->usuarioModel->buscarPorId($id);

        if ($usuario === null) {
            throw new NaoEncontradoException('Usuário');
        }

        $this->usuarioModel->desativar($id);

        $this->logService->registrar(
            acao:       'DELETE',
            entidade:   'usuario',
            entidadeId: $id,
            payload:    ['nome' => $usuario['nome'], 'email' => $usuario['email']],
        );
    }

    /**
     * Retorna os dados do próprio usuário autenticado.
     *
     * @return array{id: string, nome: string, email: string, role: string, mfa_ativo: int, created_at: string}
     * @throws NaoAutorizadoException Se não houver sessão autenticada
     */
    public function obterPerfil(): array
    {
        $usuarioAutenticado = UsuarioAutenticado::obterOuFalhar();
        return $this->buscarPorId($usuarioAutenticado['id']);
    }

    /**
     * Altera a senha do próprio usuário autenticado.
     *
     * @param  array{senha_atual: string, nova_senha: string} $dados
     * @throws ValidacaoException     Se os dados forem inválidos
     * @throws NaoAutorizadoException Se a senha atual estiver incorreta
     */
    public function alterarSenha(array $dados): void
    {
        $erros = Validator::validar($dados, [
            'senha_atual' => 'obrigatorio',
            'nova_senha'  => 'obrigatorio|min:8',
        ]);

        if (!empty($erros)) {
            throw new ValidacaoException($erros);
        }

        $usuarioAutenticado = UsuarioAutenticado::obterOuFalhar();
        $usuarioComHash     = $this->usuarioModel->buscarPorEmailComCredenciais($usuarioAutenticado['email']);

        if ($usuarioComHash === null || !password_verify($dados['senha_atual'], $usuarioComHash['senha_hash'])) {
            throw new NaoAutorizadoException('Senha atual incorreta');
        }

        $this->usuarioModel->atualizar($usuarioAutenticado['id'], [
            'senha_hash' => password_hash($dados['nova_senha'], PASSWORD_BCRYPT),
        ]);

        $this->logService->registrar(
            acao:       'UPDATE',
            entidade:   'usuario',
            entidadeId: $usuarioAutenticado['id'],
            payload:    ['descricao' => 'alteracao_de_senha'],
        );
    }
}
