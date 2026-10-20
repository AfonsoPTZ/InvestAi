<?php
/**
 * backend/ia/chat/tools/metas/EditarMeta.php
 */

class EditarMeta {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'editar_meta',
            'description' => 'Altera nome, valor total ou prazo de uma meta financeira existente. Use quando o usuário quiser editar, alterar, mudar, corrigir ou atualizar uma meta. Exemplos: "muda o valor da meta da moto para 10 mil", "altera o prazo da meta da viagem para março", "corrige o nome da minha meta", "errei, a meta são 9 mil". Informe nome_busca para localizar a meta (string vazia = mais recente) e pelo menos um campo a alterar.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nome_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho do nome para localizar a meta (ex: "moto", "viagem"). Use string vazia para a meta mais recente.',
                    ],
                    'novo_nome' => [
                        'type'        => 'string',
                        'description' => 'Novo nome da meta (opcional).',
                    ],
                    'novo_valor_total' => [
                        'type'        => 'number',
                        'description' => 'Novo valor total da meta (opcional).',
                    ],
                    'novo_prazo' => [
                        'type'        => 'string',
                        'description' => 'Novo prazo no formato YYYY-MM-DD (opcional).',
                    ],
                ],
                'required'   => ['nome_busca'],
            ],
        ];
    }

    public function execute(array $params): array {
        $busca           = $params['nome_busca']       ?? '';
        $novo_nome       = isset($params['novo_nome'])        ? trim((string) $params['novo_nome'])        : null;
        $novo_valor      = isset($params['novo_valor_total'])  ? (float)       $params['novo_valor_total']  : null;
        $novo_prazo      = isset($params['novo_prazo'])        ? (string)      $params['novo_prazo']        : null;

        if ($busca === '' && $novo_nome === null && $novo_valor === null && $novo_prazo === null) {
            return ['tipo' => 'erro', 'mensagem' => 'Informe qual meta deseja editar e o que alterar.'];
        }

        $like = '%' . $busca . '%';
        $stmt = $this->conexao->prepare(
            "SELECT id, nome, valor_total, prazo
             FROM metas WHERE usuario_id = ? AND ativo = 1 AND nome LIKE ?
             ORDER BY criado_em DESC LIMIT 1"
        );
        $stmt->bind_param('is', $this->usuario_id, $like);
        $stmt->execute();
        $meta = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$meta) {
            return ['tipo' => 'erro', 'mensagem' => "Não encontrei meta com \"$busca\" para editar."];
        }

        $nome_final  = $novo_nome  ?? (string) $meta['nome'];
        $valor_final = $novo_valor ?? (float)  $meta['valor_total'];
        $prazo_final = $novo_prazo ?? $meta['prazo'];
        $id          = (int) $meta['id'];

        // Valida prazo se fornecido
        if ($prazo_final !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo_final)) {
            $prazo_final = $meta['prazo'];
        }

        $stmt = $this->conexao->prepare(
            "UPDATE metas SET nome = ?, valor_total = ?, prazo = ?
             WHERE id = ? AND usuario_id = ?"
        );
        $stmt->bind_param('sdsii', $nome_final, $valor_final, $prazo_final, $id, $this->usuario_id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao atualizar a meta.'];
        }

        return [
            'tipo'        => 'sucesso',
            'acao'        => 'editar_meta',
            'nome'        => $nome_final,
            'valor_total' => $valor_final,
            'prazo'       => $prazo_final,
        ];
    }
}
