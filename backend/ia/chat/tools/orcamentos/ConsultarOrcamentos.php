<?php
/**
 * backend/ia/chat/tools/ConsultarOrcamentos.php
 */

class ConsultarOrcamentos {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'consultar_orcamentos',
            'description' => 'Retorna os orçamentos/limites de gasto definidos pelo usuário para o mês. Use quando o usuário perguntar sobre orçamentos, limites ou metas de gastos. Exemplos: "quais meus orçamentos?", "ver meus limites de gasto", "quanto tenho de orçamento para alimentação?".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        $stmt = $this->conexao->prepare("
            SELECT c.nome AS categoria, oc.limite_mensal AS limite
            FROM orcamento_categorias oc
            JOIN categorias c ON oc.categoria_id = c.id
            WHERE oc.usuario_id = ? AND oc.mes = ? AND oc.ano = ?
            ORDER BY c.nome
        ");
        $stmt->bind_param('iii', $this->usuario_id, $this->mes, $this->ano);
        $stmt->execute();
        $result = $stmt->get_result();
        $lista  = [];
        while ($row = $result->fetch_assoc()) {
            $lista[] = ['categoria' => $row['categoria'], 'limite' => (float) $row['limite']];
        }
        $stmt->close();

        return [
            'tipo'       => 'sucesso',
            'acao'       => 'consultar_orcamentos',
            'orcamentos' => $lista,
            'mes'        => $this->mes,
            'ano'        => $this->ano,
        ];
    }
}
