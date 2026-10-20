<?php
/**
 * backend/ia/chat/tools/DeletarGanho.php
 */

class DeletarGanho {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_ganho',
            'description' => 'Remove um ganho/receita do banco. Use quando o usuário quiser deletar, excluir, apagar ou remover um ganho específico. Exemplos: "deletar meu ganho de freelance", "apaga o salário do mês passado", "remova a receita de aluguel". SEMPRE use confirmado=false na primeira chamada — a tool mostrará qual item será removido e pedirá confirmação. Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo" em resposta à confirmação anterior.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'descricao' => [
                        'type'        => 'string',
                        'description' => 'Trecho da descrição para identificar qual ganho deletar. Obrigatório — não é possível deletar sem especificar.',
                    ],
                    'confirmado' => [
                        'type'        => 'boolean',
                        'description' => 'false = localizar o ganho e mostrar pedido de confirmação. true = usuário já confirmou, executar a deleção.',
                    ],
                ],
                'required'   => ['descricao', 'confirmado'],
            ],
        ];
    }

    public function execute(array $params): array {
        $busca      = trim($params['descricao'] ?? '');
        $confirmado = (bool) ($params['confirmado'] ?? false);

        if ($busca === '') {
            return ['tipo' => 'erro', 'mensagem' => 'Informe qual ganho deseja deletar.'];
        }

        $ganho = $this->buscarGanho($busca);

        if (!$ganho) {
            return ['tipo' => 'erro', 'mensagem' => 'Não encontrei nenhum ganho para deletar.'];
        }

        if (!$confirmado) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => "⚠️ Confirmar exclusão de \"{$ganho['descricao']}\" (R$ {$ganho['valor']})? Essa ação não pode ser desfeita!",
            ];
        }

        $id   = (int) $ganho['id'];
        $stmt = $this->conexao->prepare("DELETE FROM ganhos WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param('ii', $id, $this->usuario_id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao deletar o ganho.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'deletar_ganho',
            'descricao' => $ganho['descricao'],
            'valor'     => (float) $ganho['valor'],
        ];
    }

    private function buscarGanho(string $busca): ?array {
        $like = '%' . $busca . '%';
        $stmt = $this->conexao->prepare(
            "SELECT id, descricao, valor FROM ganhos
             WHERE usuario_id = ? AND descricao LIKE ?
             ORDER BY data_ganho DESC LIMIT 1"
        );
        $stmt->bind_param('is', $this->usuario_id, $like);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
