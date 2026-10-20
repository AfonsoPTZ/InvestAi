<?php
/**
 * backend/ia/chat/tools/DeletarOrcamento.php
 */

class DeletarOrcamento {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_orcamento',
            'description' => 'Remove um orçamento/limite de gasto. Use quando o usuário quiser deletar, excluir, apagar ou remover um orçamento. Exemplos: "remova meu orçamento de transporte", "deletar o limite de alimentação", "apaga o budget de lazer".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'categoria' => [
                        'type'        => 'string',
                        'description' => 'Nome da categoria de despesa do orçamento a remover.',
                    ],
                    'mes'       => [
                        'type'        => 'integer',
                        'description' => 'Mês do orçamento (1-12). Se não informado, usar o mês atual.',
                    ],
                    'ano'       => [
                        'type'        => 'integer',
                        'description' => 'Ano do orçamento. Se não informado, usar o ano atual.',
                    ],
                ],
                'required'   => ['categoria'],
            ],
        ];
    }

    public function execute(array $params): array {
        $categoria = isset($params['categoria']) ? (string) $params['categoria'] : null;
        $mes       = isset($params['mes'])       ? (int)    $params['mes']       : $this->mes;
        $ano       = isset($params['ano'])       ? (int)    $params['ano']       : $this->ano;

        if (!$categoria) {
            return ['tipo' => 'erro', 'mensagem' => 'Informe a categoria do orçamento que deseja remover.'];
        }

        $categoria_resolvida = $this->resolverCategoria($categoria);
        if (!$categoria_resolvida) {
            return ['tipo' => 'erro', 'mensagem' => "Categoria \"$categoria\" não encontrada."];
        }

        $categoria_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'despesa', $categoria_resolvida);
        if (!$categoria_id) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro interno ao buscar a categoria.'];
        }

        $stmt = $this->conexao->prepare(
            "DELETE FROM orcamento_categorias
             WHERE usuario_id = ? AND categoria_id = ? AND mes = ? AND ano = ?"
        );
        $stmt->bind_param('iiii', $this->usuario_id, $categoria_id, $mes, $ano);
        $ok           = $stmt->execute();
        $rows_deleted = $stmt->affected_rows;
        $stmt->close();

        if (!$ok || $rows_deleted === 0) {
            $mes_nome = BulkDeleteHelper::nomeMes($mes);
            return ['tipo' => 'erro', 'mensagem' => "Não encontrei orçamento de \"$categoria_resolvida\" em {$mes_nome}/{$ano}."];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'deletar_orcamento',
            'categoria' => $categoria_resolvida,
            'mes'       => $mes,
            'ano'       => $ano,
        ];
    }

    private function resolverCategoria(string $nome): ?string {
        return CategoriaResolver::resolverCategoria($this->conexao, 'despesa', $nome);
    }
}
