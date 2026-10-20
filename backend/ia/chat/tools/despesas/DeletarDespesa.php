<?php
/**
 * backend/ia/chat/tools/DeletarDespesa.php
 */

class DeletarDespesa {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_despesa',
            'description' => 'Remove uma despesa do banco. Use quando o usuário quiser deletar, excluir, apagar ou remover uma despesa específica. Exemplos: "apague minha última despesa", "deletar a despesa de netflix", "remova o gasto com aluguel". Use descricao vazia ("") para identificar a despesa mais recente. SEMPRE use confirmado=false na primeira chamada — a tool mostrará qual item será removido e pedirá confirmação. Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo" em resposta à confirmação anterior.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'descricao' => [
                        'type'        => 'string',
                        'description' => 'Trecho da descrição para identificar qual despesa deletar. Use string vazia "" para a mais recente.',
                    ],
                    'confirmado' => [
                        'type'        => 'boolean',
                        'description' => 'false = localizar a despesa e mostrar pedido de confirmação. true = usuário já confirmou, executar a deleção.',
                    ],
                ],
                'required'   => ['descricao', 'confirmado'],
            ],
        ];
    }

    public function execute(array $params): array {
        $busca      = trim($params['descricao'] ?? '');
        $confirmado = (bool) ($params['confirmado'] ?? false);

        $despesa = $this->buscarDespesa($busca);

        if (!$despesa) {
            return ['tipo' => 'erro', 'mensagem' => 'Não encontrei nenhuma despesa para deletar.'];
        }

        if (!$confirmado) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => "⚠️ Confirmar exclusão de \"{$despesa['descricao']}\" (R$ {$despesa['valor']})? Essa ação não pode ser desfeita!",
            ];
        }

        $id   = (int) $despesa['id'];
        $stmt = $this->conexao->prepare("DELETE FROM despesas WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param('ii', $id, $this->usuario_id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao deletar a despesa.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'deletar_despesa',
            'descricao' => $despesa['descricao'],
            'valor'     => (float) $despesa['valor'],
        ];
    }

    private function buscarDespesa(string $busca): ?array {
        if ($busca === '') {
            $stmt = $this->conexao->prepare(
                "SELECT id, descricao, valor FROM despesas
                 WHERE usuario_id = ? ORDER BY data_despesa DESC, id DESC LIMIT 1"
            );
            $stmt->bind_param('i', $this->usuario_id);
        } else {
            $like = '%' . $busca . '%';
            $stmt = $this->conexao->prepare(
                "SELECT id, descricao, valor FROM despesas
                 WHERE usuario_id = ? AND descricao LIKE ?
                 ORDER BY data_despesa DESC LIMIT 1"
            );
            $stmt->bind_param('is', $this->usuario_id, $like);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
