<?php
/**
 * backend/ia/chat/tools/metas/DeletarTodasMetas.php
 */

class DeletarTodasMetas {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_todas_metas',
            'description' => 'Apaga TODAS as metas do usuário. Use quando o usuário pedir para apagar/deletar/excluir/remover/limpar todas as metas. Exemplos: "apague todas as minhas metas", "delete todas as metas", "remove todas", "limpa minhas metas". Use confirmado=false na primeira vez (retorna pedido de confirmação). Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo" em resposta a uma confirmação anterior.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'confirmado' => [
                        'type'        => 'boolean',
                        'description' => 'false = mostrar aviso pedindo confirmação. true = usuário já confirmou, executar.',
                    ],
                ],
                'required'   => ['confirmado'],
            ],
        ];
    }

    public function execute(array $params): array {
        if (!($params['confirmado'] ?? false)) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => '⚠️ Tem certeza que quer apagar TODAS as suas metas? Essa ação não pode ser desfeita!',
            ];
        }

        $resumo = BulkDeleteHelper::obterResumo($this->conexao, 'metas', 'valor_total', $this->usuario_id);
        $total  = $resumo['total'];

        if ($total === 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Você não tem nenhuma meta cadastrada.'];
        }

        $stmt = $this->conexao->prepare("UPDATE metas SET ativo = 0 WHERE usuario_id = ? AND ativo = 1");
        $stmt->bind_param('i', $this->usuario_id);
        $ok       = $stmt->execute();
        $apagadas = $stmt->affected_rows;
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao apagar as metas.'];
        }

        return [
            'tipo'     => 'sucesso',
            'acao'     => 'deletar_todas_metas',
            'apagadas' => $apagadas,
        ];
    }
}
