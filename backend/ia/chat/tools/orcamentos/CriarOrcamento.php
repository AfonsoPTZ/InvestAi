<?php
/**
 * backend/ia/chat/tools/CriarOrcamento.php
 */

class CriarOrcamento {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'criar_orcamento',
            'description' => 'Define ou atualiza um limite de gasto mensal para uma categoria (orçamento). Use quando o usuário quiser criar ou definir um orçamento/limite/meta de gastos. Exemplos: "crie um orçamento de 500 reais para alimentação", "quero definir 300 de limite para transporte", "estabelece um budget de 200 para lazer". Se a categoria ou valor não estiver clara, use pedir_confirmacao.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'categoria' => [
                        'type'        => 'string',
                        'description' => 'Nome da categoria de despesa para o orçamento (ex: Alimentação, Transporte, Saúde).',
                    ],
                    'valor'     => [
                        'type'        => 'number',
                        'description' => 'Limite mensal em reais (ex: 500.00).',
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
                'required'   => ['categoria', 'valor'],
            ],
        ];
    }

    public function execute(array $params): array {
        $categoria = isset($params['categoria']) ? (string) $params['categoria'] : null;
        $valor     = isset($params['valor'])     ? (float)  $params['valor']     : null;
        $mes       = isset($params['mes'])       ? (int)    $params['mes']       : $this->mes;
        $ano       = isset($params['ano'])       ? (int)    $params['ano']       : $this->ano;

        if (!$categoria || !$valor || $valor <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Faltam dados para criar o orçamento.'];
        }

        $categoria_resolvida = $this->resolverCategoria($categoria);
        if (!$categoria_resolvida) {
            return ['tipo' => 'erro', 'mensagem' => "Não encontrei a categoria \"$categoria\". Verifique o nome e tente novamente."];
        }

        $categoria_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'despesa', $categoria_resolvida);
        if (!$categoria_id) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro interno ao buscar a categoria.'];
        }

        $stmt = $this->conexao->prepare("
            INSERT INTO orcamento_categorias (usuario_id, categoria_id, limite_mensal, mes, ano)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE limite_mensal = VALUES(limite_mensal)
        ");
        $stmt->bind_param('iidii', $this->usuario_id, $categoria_id, $valor, $mes, $ano);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao salvar o orçamento no banco.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'criar_orcamento',
            'categoria' => $categoria_resolvida,
            'valor'     => $valor,
            'mes'       => $mes,
            'ano'       => $ano,
        ];
    }

    private function resolverCategoria(string $nome): ?string {
        return CategoriaResolver::resolverCategoria($this->conexao, 'despesa', $nome);
    }
}
