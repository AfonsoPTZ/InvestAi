<?php
/**
 * backend/ia/chat/tools/EditarDespesa.php
 */

class EditarDespesa {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'editar_despesa',
            'description' => 'Altera valor, descrição, categoria ou data de uma despesa existente. Use quando o usuário quiser editar, alterar, mudar, corrigir ou atualizar uma despesa. Exemplos: "edite minha despesa de aluguel para 1200", "muda o valor da academia para 180", "corrige a descrição da última despesa". Informe descricao_busca para localizar a despesa e pelo menos um campo a alterar.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'descricao_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho da descrição para localizar a despesa (ex: "aluguel", "netflix"). Use string vazia para a despesa mais recente.',
                    ],
                    'novo_valor'      => [
                        'type'        => 'number',
                        'description' => 'Novo valor da despesa (opcional).',
                    ],
                    'nova_descricao'  => [
                        'type'        => 'string',
                        'description' => 'Nova descrição da despesa (opcional).',
                    ],
                    'nova_categoria'  => [
                        'type'        => 'string',
                        'description' => 'Nova categoria da despesa (opcional).',
                    ],
                    'nova_data'       => [
                        'type'        => 'string',
                        'description' => 'Nova data no formato YYYY-MM-DD (opcional).',
                    ],
                ],
                'required'   => ['descricao_busca'],
            ],
        ];
    }

    public function execute(array $params): array {
        $busca      = $params['descricao_busca']  ?? '';
        $novo_valor = isset($params['novo_valor'])     ? (float)  $params['novo_valor']     : null;
        $nova_desc  = isset($params['nova_descricao']) ? (string) $params['nova_descricao'] : null;
        $nova_cat   = isset($params['nova_categoria']) ? (string) $params['nova_categoria'] : null;
        $nova_data  = isset($params['nova_data'])      ? (string) $params['nova_data']      : null;

        if ($busca === '' && $novo_valor === null && $nova_desc === null && $nova_cat === null && $nova_data === null) {
            return ['tipo' => 'erro', 'mensagem' => 'Informe qual despesa deseja editar e o que alterar.'];
        }

        $like = '%' . $busca . '%';
        $stmt = $this->conexao->prepare(
            "SELECT id, descricao, valor, data_despesa, categoria_id
             FROM despesas WHERE usuario_id = ? AND descricao LIKE ?
             ORDER BY data_despesa DESC, id DESC LIMIT 1"
        );
        $stmt->bind_param('is', $this->usuario_id, $like);
        $stmt->execute();
        $despesa = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$despesa) {
            return ['tipo' => 'erro', 'mensagem' => "Não encontrei despesa com \"$busca\" para editar."];
        }

        $novo_cat_id = $despesa['categoria_id'];
        if ($nova_cat !== null) {
            $cat_resolvida = CategoriaResolver::resolverCategoria($this->conexao, 'despesa', $nova_cat);
            if ($cat_resolvida) {
                $novo_cat_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'despesa', $cat_resolvida);
                $nova_cat    = $cat_resolvida;
            }
        }

        $val_final  = $novo_valor ?? (float)  $despesa['valor'];
        $desc_final = $nova_desc  ?? (string) $despesa['descricao'];
        $data_final = $nova_data  ?? (string) $despesa['data_despesa'];
        $id         = (int) $despesa['id'];

        $stmt = $this->conexao->prepare(
            "UPDATE despesas SET descricao = ?, valor = ?, data_despesa = ?, categoria_id = ?
             WHERE id = ? AND usuario_id = ?"
        );
        $stmt->bind_param('sdsiii', $desc_final, $val_final, $data_final, $novo_cat_id, $id, $this->usuario_id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao atualizar a despesa.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'editar_despesa',
            'descricao' => $desc_final,
            'valor'     => $val_final,
            'data'      => $data_final,
        ];
    }
}
