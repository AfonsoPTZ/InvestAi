<?php
/**
 * backend/ia/chat/tools/aportes/DeletarAporte.php
 */

class DeletarAporte {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_aporte',
            'description' => 'Remove um aporte registrado em uma meta. Use quando o usuário quiser deletar, excluir, apagar ou remover um aporte. Exemplos: "apaga o último aporte", "remove o aporte da meta da moto", "deleta aquele depósito que fiz". Use meta_nome_busca vazio para o aporte mais recente. SEMPRE use confirmado=false na primeira chamada. Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'meta_nome_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho do nome da meta para identificar o aporte a deletar. Use string vazia "" para o aporte mais recente.',
                    ],
                    'confirmado' => [
                        'type'        => 'boolean',
                        'description' => 'false = localizar o aporte e mostrar pedido de confirmação. true = usuário já confirmou, executar.',
                    ],
                ],
                'required'   => ['meta_nome_busca', 'confirmado'],
            ],
        ];
    }

    public function execute(array $params): array {
        $meta_busca = trim($params['meta_nome_busca'] ?? '');
        $confirmado = (bool) ($params['confirmado'] ?? false);

        $aporte = $this->buscarAporte($meta_busca);

        if (!$aporte) {
            $msg = $meta_busca !== ''
                ? "Não encontrei nenhum aporte na meta \"{$meta_busca}\"."
                : 'Não encontrei nenhum aporte para deletar.';
            return ['tipo' => 'erro', 'mensagem' => $msg];
        }

        if (!$confirmado) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => "⚠️ Confirmar exclusão do aporte de R$ {$aporte['valor']} na meta \"{$aporte['meta_nome']}\"? Essa ação não pode ser desfeita!",
            ];
        }

        $aporte_id = (int) $aporte['id'];
        $meta_id   = (int) $aporte['meta_id'];
        $valor     = (float) $aporte['valor'];

        $this->conexao->begin_transaction();
        try {
            $stmt = $this->conexao->prepare("DELETE FROM aportes WHERE id = ? AND usuario_id = ?");
            $stmt->bind_param('ii', $aporte_id, $this->usuario_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conexao->prepare(
                "UPDATE metas SET valor_guardado = GREATEST(0, valor_guardado - ?) WHERE id = ? AND usuario_id = ?"
            );
            $stmt->bind_param('dii', $valor, $meta_id, $this->usuario_id);
            $stmt->execute();
            $stmt->close();

            $this->conexao->commit();
        } catch (Exception $e) {
            $this->conexao->rollback();
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao deletar o aporte. Tente novamente.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'deletar_aporte',
            'valor'     => $valor,
            'meta_nome' => $aporte['meta_nome'],
        ];
    }

    private function buscarAporte(string $meta_busca): ?array {
        if ($meta_busca === '') {
            $stmt = $this->conexao->prepare(
                "SELECT a.id, a.valor, a.data_aporte, a.meta_id, m.nome AS meta_nome
                 FROM aportes a
                 JOIN metas m ON a.meta_id = m.id
                 WHERE a.usuario_id = ?
                 ORDER BY a.data_aporte DESC, a.id DESC LIMIT 1"
            );
            $stmt->bind_param('i', $this->usuario_id);
        } else {
            $like = '%' . $meta_busca . '%';
            $stmt = $this->conexao->prepare(
                "SELECT a.id, a.valor, a.data_aporte, a.meta_id, m.nome AS meta_nome
                 FROM aportes a
                 JOIN metas m ON a.meta_id = m.id
                 WHERE a.usuario_id = ? AND m.nome LIKE ?
                 ORDER BY a.data_aporte DESC, a.id DESC LIMIT 1"
            );
            $stmt->bind_param('is', $this->usuario_id, $like);
        }
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: null;
    }
}
