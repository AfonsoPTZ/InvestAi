<?php
/**
 * backend/ia/chat/tools/aportes/EditarAporte.php
 */

class EditarAporte {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'editar_aporte',
            'description' => 'Corrige ou altera o valor/data de um aporte já registrado em uma meta. Use quando o usuário quiser corrigir um aporte que acabou de fazer ou um aporte passado. Exemplos: "na verdade foi 250", "corrige o aporte da moto para 300", "o aporte era 500 não 200", "errei o valor do aporte". Use meta_nome_busca vazio para o aporte mais recente.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'meta_nome_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho do nome da meta para localizar o aporte (ex: "moto", "viagem"). Use string vazia "" para o aporte mais recente de qualquer meta.',
                    ],
                    'novo_valor' => [
                        'type'        => 'number',
                        'description' => 'Novo valor do aporte. Obrigatório.',
                    ],
                    'nova_data' => [
                        'type'        => 'string',
                        'description' => 'Nova data no formato YYYY-MM-DD (opcional).',
                    ],
                ],
                'required'   => ['meta_nome_busca', 'novo_valor'],
            ],
        ];
    }

    public function execute(array $params): array {
        $meta_busca = trim($params['meta_nome_busca'] ?? '');
        $novo_valor = isset($params['novo_valor']) ? (float) $params['novo_valor'] : null;
        $nova_data  = isset($params['nova_data'])  ? (string) $params['nova_data'] : null;

        if (!$novo_valor || $novo_valor <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi o novo valor do aporte. Pode informar novamente?'];
        }

        $aporte = $this->buscarAporte($meta_busca);
        if (!$aporte) {
            $msg = $meta_busca !== ''
                ? "Não encontrei nenhum aporte na meta \"{$meta_busca}\"."
                : 'Não encontrei nenhum aporte para editar.';
            return ['tipo' => 'erro', 'mensagem' => $msg];
        }

        $valor_antigo = (float) $aporte['valor'];
        $delta        = $novo_valor - $valor_antigo;
        $data_final   = ($nova_data && preg_match('/^\d{4}-\d{2}-\d{2}$/', $nova_data))
                        ? $nova_data
                        : $aporte['data_aporte'];
        $aporte_id    = (int) $aporte['id'];
        $meta_id      = (int) $aporte['meta_id'];

        $this->conexao->begin_transaction();
        try {
            $stmt = $this->conexao->prepare(
                "UPDATE aportes SET valor = ?, data_aporte = ? WHERE id = ? AND usuario_id = ?"
            );
            $stmt->bind_param('dsii', $novo_valor, $data_final, $aporte_id, $this->usuario_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conexao->prepare(
                "UPDATE metas SET valor_guardado = GREATEST(0, valor_guardado + ?) WHERE id = ? AND usuario_id = ?"
            );
            $stmt->bind_param('dii', $delta, $meta_id, $this->usuario_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $this->conexao->prepare("SELECT valor_guardado FROM metas WHERE id = ?");
            $stmt->bind_param('i', $meta_id);
            $stmt->execute();
            $novo_guardado = (float) ($stmt->get_result()->fetch_row()[0] ?? 0);
            $stmt->close();

            $this->conexao->commit();
        } catch (Exception $e) {
            $this->conexao->rollback();
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao editar o aporte. Tente novamente.'];
        }

        return [
            'tipo'               => 'sucesso',
            'acao'               => 'editar_aporte',
            'valor'              => $novo_valor,
            'meta_nome'          => $aporte['meta_nome'],
            'novo_valor_guardado'=> $novo_guardado,
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
