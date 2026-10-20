<?php
/**
 * backend/ia/chat/tools/ResumoDashboard.php
 */

class ResumoDashboard {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'resumo_dashboard',
            'description' => 'Retorna o resumo financeiro geral do mês: total de ganhos, total de despesas, saldo e top 3 categorias de gasto. Use quando o usuário pedir um resumo, visão geral das finanças, balanço ou saldo do mês. Exemplos: "me dê um resumo das minhas finanças", "como estão minhas finanças?", "resumo do mês", "qual meu saldo?".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        $stmt = $this->conexao->prepare(
            "SELECT COALESCE(SUM(valor), 0) AS total FROM ganhos
             WHERE usuario_id = ? AND MONTH(data_ganho) = ? AND YEAR(data_ganho) = ?"
        );
        $stmt->bind_param('iii', $this->usuario_id, $this->mes, $this->ano);
        $stmt->execute();
        $total_ganhos = (float) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $this->conexao->prepare(
            "SELECT COALESCE(SUM(valor), 0) AS total FROM despesas
             WHERE usuario_id = ? AND MONTH(data_despesa) = ? AND YEAR(data_despesa) = ?"
        );
        $stmt->bind_param('iii', $this->usuario_id, $this->mes, $this->ano);
        $stmt->execute();
        $total_despesas = (float) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        $stmt = $this->conexao->prepare("
            SELECT COALESCE(c.nome, 'Sem categoria') AS categoria,
                   SUM(d.valor) AS total
            FROM despesas d
            LEFT JOIN categorias c ON d.categoria_id = c.id
            WHERE d.usuario_id = ? AND MONTH(d.data_despesa) = ? AND YEAR(d.data_despesa) = ?
            GROUP BY d.categoria_id, c.nome
            ORDER BY total DESC
            LIMIT 3
        ");
        $stmt->bind_param('iii', $this->usuario_id, $this->mes, $this->ano);
        $stmt->execute();
        $result         = $stmt->get_result();
        $top_categorias = [];
        while ($row = $result->fetch_assoc()) {
            $top_categorias[] = ['categoria' => $row['categoria'], 'total' => (float) $row['total']];
        }
        $stmt->close();

        return [
            'tipo'           => 'sucesso',
            'acao'           => 'resumo_dashboard',
            'total_ganhos'   => $total_ganhos,
            'total_despesas' => $total_despesas,
            'saldo'          => $total_ganhos - $total_despesas,
            'top_categorias' => $top_categorias,
            'mes'            => $this->mes,
            'ano'            => $this->ano,
        ];
    }
}
