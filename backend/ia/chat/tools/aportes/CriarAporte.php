<?php
/**
 * backend/ia/chat/tools/aportes/CriarAporte.php
 */

class CriarAporte {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'criar_aporte',
            'description' => 'Registra um aporte (depósito/contribuição) em uma meta financeira já existente. Use quando o usuário quiser DEPOSITAR, CONTRIBUIR, GUARDAR ou ADICIONAR dinheiro EM uma meta. Exemplos: "adicionei 100 reais na meta da moto", "guardei 50 para a viagem", "coloquei 200 na meta do notebook", "fiz um aporte de 300 na reserva de emergência". NUNCA use para criar uma nova meta — use criar_meta. Se não ficar claro qual meta o usuário quer aportar, use pedir_confirmacao.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'meta_nome_busca' => [
                        'type'        => 'string',
                        'description' => 'Nome ou parte do nome da meta onde o aporte será registrado. Exemplo: "moto", "viagem", "notebook". Use o que o usuário mencionou.',
                    ],
                    'valor' => [
                        'type'        => 'number',
                        'description' => 'Valor do aporte (ex: 100.00). Obrigatório.',
                    ],
                    'data_aporte' => [
                        'type'        => 'string',
                        'description' => 'Data do aporte no formato YYYY-MM-DD. Se não informada, usar a data de hoje.',
                    ],
                ],
                'required'   => ['meta_nome_busca', 'valor'],
            ],
        ];
    }

    public function execute(array $params): array {
        $meta_busca  = isset($params['meta_nome_busca']) ? trim((string) $params['meta_nome_busca']) : null;
        $valor       = isset($params['valor'])           ? (float)       $params['valor']            : null;
        $data_aporte = isset($params['data_aporte'])     ? (string)      $params['data_aporte']      : date('Y-m-d');

        if (!$meta_busca) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi em qual meta você quer fazer o aporte.'];
        }

        if (!$valor || $valor <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi o valor do aporte. Pode informar novamente?'];
        }

        // Valida formato de data
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_aporte)) {
            $data_aporte = date('Y-m-d');
        }

        // Busca metas ativas do usuário pelo nome (LIKE fuzzy)
        $termo = '%' . $meta_busca . '%';
        $stmt = $this->conexao->prepare(
            "SELECT id, nome FROM metas
             WHERE usuario_id = ? AND ativo = 1 AND nome LIKE ?
             ORDER BY criado_em DESC"
        );
        $stmt->bind_param('is', $this->usuario_id, $termo);
        $stmt->execute();
        $resultado = $stmt->get_result();
        $metas = $resultado->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (empty($metas)) {
            return [
                'tipo'     => 'erro',
                'mensagem' => "Não encontrei nenhuma meta com o nome \"{$meta_busca}\". Verifique o nome da meta e tente novamente.",
            ];
        }

        if (count($metas) > 1) {
            $nomes = implode(', ', array_map(fn($m) => "\"{$m['nome']}\"", $metas));
            return [
                'tipo'               => 'precisa_confirmacao',
                'acao'               => 'pedir_confirmacao',
                'mensagem'           => "Encontrei mais de uma meta: {$nomes}. Em qual delas você quer fazer o aporte?",
                'precisa_confirmacao'=> true,
            ];
        }

        $meta = $metas[0];
        $meta_id   = (int) $meta['id'];
        $meta_nome = $meta['nome'];

        // Transação: insere aporte + atualiza valor_guardado da meta
        $this->conexao->begin_transaction();

        try {
            $stmt_aporte = $this->conexao->prepare(
                "INSERT INTO aportes (usuario_id, meta_id, valor, data_aporte)
                 VALUES (?, ?, ?, ?)"
            );
            $stmt_aporte->bind_param('iids', $this->usuario_id, $meta_id, $valor, $data_aporte);
            $stmt_aporte->execute();
            $stmt_aporte->close();

            $stmt_meta = $this->conexao->prepare(
                "UPDATE metas SET valor_guardado = valor_guardado + ? WHERE id = ? AND usuario_id = ?"
            );
            $stmt_meta->bind_param('dii', $valor, $meta_id, $this->usuario_id);
            $stmt_meta->execute();
            $stmt_meta->close();

            // Busca novo valor_guardado
            $stmt_saldo = $this->conexao->prepare(
                "SELECT valor_guardado FROM metas WHERE id = ?"
            );
            $stmt_saldo->bind_param('i', $meta_id);
            $stmt_saldo->execute();
            $novo_valor = $stmt_saldo->get_result()->fetch_row()[0] ?? 0;
            $stmt_saldo->close();

            $this->conexao->commit();
        } catch (Exception $e) {
            $this->conexao->rollback();
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao registrar o aporte. Tente novamente.'];
        }

        return [
            'tipo'               => 'sucesso',
            'acao'               => 'criar_aporte',
            'valor'              => $valor,
            'meta_nome'          => $meta_nome,
            'novo_valor_guardado'=> (float) $novo_valor,
            'data_aporte'        => $data_aporte,
        ];
    }
}
