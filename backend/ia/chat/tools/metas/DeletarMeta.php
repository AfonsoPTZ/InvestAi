<?php
/**
 * backend/ia/chat/tools/metas/DeletarMeta.php
 */

class DeletarMeta {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_meta',
            'description' => 'Remove uma meta financeira. Use quando o usuário quiser deletar, excluir, apagar ou remover uma meta. Exemplos: "apaga a meta da moto", "remove minha meta de viagem", "deleta a meta do notebook". Use nome_busca vazio ("") para a meta mais recente. SEMPRE use confirmado=false na primeira chamada. Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo" em resposta à confirmação anterior.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nome_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho do nome para identificar qual meta deletar. Use string vazia "" para a mais recente.',
                    ],
                    'confirmado' => [
                        'type'        => 'boolean',
                        'description' => 'false = localizar a meta e mostrar pedido de confirmação. true = usuário já confirmou, executar.',
                    ],
                ],
                'required'   => ['nome_busca', 'confirmado'],
            ],
        ];
    }

    public function execute(array $params): array {
        $busca      = trim($params['nome_busca'] ?? '');
        $confirmado = (bool) ($params['confirmado'] ?? false);

        $meta = $this->buscarMeta($busca);

        if (!$meta) {
            return ['tipo' => 'erro', 'mensagem' => 'Não encontrei nenhuma meta para deletar.'];
        }

        if (!$confirmado) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => "⚠️ Confirmar exclusão da meta \"{$meta['nome']}\" (R$ {$meta['valor_total']})? Essa ação não pode ser desfeita!",
            ];
        }

        $id   = (int) $meta['id'];
        $stmt = $this->conexao->prepare("UPDATE metas SET ativo = 0 WHERE id = ? AND usuario_id = ?");
        $stmt->bind_param('ii', $id, $this->usuario_id);
        $ok = $stmt->execute() && $stmt->affected_rows > 0;
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao remover a meta.'];
        }

        return [
            'tipo'        => 'sucesso',
            'acao'        => 'deletar_meta',
            'nome'        => $meta['nome'],
            'valor_total' => (float) $meta['valor_total'],
        ];
    }

    private function buscarMeta(string $busca): ?array {
        if ($busca === '') {
            $stmt = $this->conexao->prepare(
                "SELECT id, nome, valor_total FROM metas
                 WHERE usuario_id = ? AND ativo = 1 ORDER BY criado_em DESC LIMIT 1"
            );
            $stmt->bind_param('i', $this->usuario_id);
        } else {
            $like = '%' . $busca . '%';
            $stmt = $this->conexao->prepare(
                "SELECT id, nome, valor_total FROM metas
                 WHERE usuario_id = ? AND ativo = 1 AND nome LIKE ?
                 ORDER BY criado_em DESC LIMIT 1"
            );
            $stmt->bind_param('is', $this->usuario_id, $like);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
