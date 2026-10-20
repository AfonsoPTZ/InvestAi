<?php
/**
 * backend/ia/chat/tools/EditarGanho.php
 */

class EditarGanho {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'editar_ganho',
            'description' => 'Altera valor, descrição, categoria ou data de um ganho/receita existente. Use quando o usuário quiser editar, alterar, mudar ou corrigir um ganho. Exemplos: "muda meu salário para 5000", "edita o valor do freelance para 900", "corrige o ganho de salário", "muda a data do freela para dia 15".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'descricao_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho da descrição para localizar o ganho (ex: "salário", "freelance"). Use string vazia para o ganho mais recente.',
                    ],
                    'novo_valor'      => [
                        'type'        => 'number',
                        'description' => 'Novo valor do ganho (opcional).',
                    ],
                    'nova_descricao'  => [
                        'type'        => 'string',
                        'description' => 'Nova descrição do ganho (opcional).',
                    ],
                    'nova_categoria'  => [
                        'type'        => 'string',
                        'description' => 'Nova categoria do ganho (opcional). Exemplos: Salário, Freelance, Investimentos, Outros.',
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
            return ['tipo' => 'erro', 'mensagem' => 'Informe qual ganho deseja editar e o que alterar.'];
        }

        $like = '%' . $busca . '%';
        $stmt = $this->conexao->prepare(
            "SELECT id, descricao, valor, data_ganho, categoria_id
             FROM ganhos WHERE usuario_id = ? AND descricao LIKE ?
             ORDER BY data_ganho DESC, id DESC LIMIT 1"
        );
        $stmt->bind_param('is', $this->usuario_id, $like);
        $stmt->execute();
        $ganho = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$ganho) {
            return ['tipo' => 'erro', 'mensagem' => "Não encontrei ganho com \"$busca\" para editar."];
        }

        $novo_cat_id = $ganho['categoria_id'];
        if ($nova_cat !== null) {
            $cat_resolvida = CategoriaResolver::resolverCategoria($this->conexao, 'ganho', $nova_cat);
            if ($cat_resolvida) {
                $novo_cat_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'ganho', $cat_resolvida);
                $nova_cat    = $cat_resolvida;
            }
        }

        $val_final  = $novo_valor ?? (float)  $ganho['valor'];
        $desc_final = $nova_desc  ?? (string) $ganho['descricao'];
        $data_final = $nova_data  ?? (string) $ganho['data_ganho'];
        $id         = (int) $ganho['id'];

        $stmt = $this->conexao->prepare(
            "UPDATE ganhos SET descricao = ?, valor = ?, data_ganho = ?, categoria_id = ?
             WHERE id = ? AND usuario_id = ?"
        );
        $stmt->bind_param('sdsiii', $desc_final, $val_final, $data_final, $novo_cat_id, $id, $this->usuario_id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao atualizar o ganho.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'editar_ganho',
            'descricao' => $desc_final,
            'valor'     => $val_final,
            'data'      => $data_final,
        ];
    }
}
