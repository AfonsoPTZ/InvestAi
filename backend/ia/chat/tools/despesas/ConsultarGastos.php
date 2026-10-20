<?php
/**
 * backend/ia/chat/tools/ConsultarGastos.php
 */

class ConsultarGastos {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'consultar_gastos',
            'description' => 'Retorna o extrato de despesas do mês agrupado por categoria. Use quando o usuário perguntar quanto gastou, pedir para ver as despesas, extrato ou gastos do mês. Exemplos: "quanto gastei esse mês?", "minhas despesas de abril", "ver meus gastos", "extrato de despesas".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        $stmt = $this->conexao->prepare("
            SELECT c.nome AS categoria, SUM(d.valor) AS total
            FROM despesas d
            JOIN categorias c ON d.categoria_id = c.id
            WHERE d.usuario_id = ?
              AND MONTH(d.data_despesa) = ?
              AND YEAR(d.data_despesa)  = ?
            GROUP BY c.id, c.nome
            ORDER BY total DESC
        ");
        $stmt->bind_param('iii', $this->usuario_id, $this->mes, $this->ano);
        $stmt->execute();
        $result = $stmt->get_result();
        $lista  = [];
        while ($row = $result->fetch_assoc()) {
            $lista[] = ['categoria' => $row['categoria'], 'total' => (float) $row['total']];
        }
        $stmt->close();

        return [
            'tipo'   => 'sucesso',
            'acao'   => 'consultar_gastos',
            'gastos' => $lista,
            'mes'    => $this->mes,
            'ano'    => $this->ano,
        ];
    }
}
