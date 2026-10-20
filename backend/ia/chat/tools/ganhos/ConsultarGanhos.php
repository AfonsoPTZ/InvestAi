<?php
/**
 * backend/ia/chat/tools/ConsultarGanhos.php
 */

class ConsultarGanhos {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'consultar_ganhos',
            'description' => 'Retorna as receitas e ganhos do mês agrupados por categoria, com total. Use quando o usuário quiser ver seus ganhos, receitas ou quanto recebeu no mês. Exemplos: "meus ganhos desse mês", "quanto recebi em abril?", "ver minhas receitas", "extrato de ganhos".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        $stmt = $this->conexao->prepare("
            SELECT COALESCE(c.nome, 'Sem categoria') AS categoria,
                   SUM(g.valor) AS total
            FROM ganhos g
            LEFT JOIN categorias c ON g.categoria_id = c.id
            WHERE g.usuario_id = ?
              AND MONTH(g.data_ganho) = ?
              AND YEAR(g.data_ganho)  = ?
            GROUP BY g.categoria_id, c.nome
            ORDER BY total DESC
        ");
        $stmt->bind_param('iii', $this->usuario_id, $this->mes, $this->ano);
        $stmt->execute();
        $result = $stmt->get_result();
        $lista  = [];
        $total  = 0.0;
        while ($row = $result->fetch_assoc()) {
            $lista[] = ['categoria' => $row['categoria'], 'total' => (float) $row['total']];
            $total  += (float) $row['total'];
        }
        $stmt->close();

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'consultar_ganhos',
            'ganhos'    => $lista,
            'total_mes' => $total,
            'mes'       => $this->mes,
            'ano'       => $this->ano,
        ];
    }
}
