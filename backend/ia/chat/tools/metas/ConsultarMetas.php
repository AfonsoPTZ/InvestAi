<?php
/**
 * backend/ia/chat/tools/metas/ConsultarMetas.php
 */

class ConsultarMetas {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'consultar_metas',
            'description' => 'Retorna as metas financeiras ativas do usuário com o progresso de cada uma. Use quando o usuário perguntar sobre suas metas, objetivos financeiros ou quiser ver quanto já guardou. Exemplos: "quais são minhas metas?", "quanto já guardei para a moto?", "ver minhas metas", "como estão meus objetivos?", "progresso das metas".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        $stmt = $this->conexao->prepare(
            "SELECT nome, valor_total, valor_guardado, prazo
             FROM metas
             WHERE usuario_id = ? AND ativo = 1
             ORDER BY prazo IS NULL ASC, prazo ASC, criado_em DESC"
        );
        $stmt->bind_param('i', $this->usuario_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $lista = [];
        while ($row = $result->fetch_assoc()) {
            $total    = (float) $row['valor_total'];
            $guardado = (float) $row['valor_guardado'];
            $progresso = $total > 0 ? round(($guardado / $total) * 100, 1) : 0;

            $lista[] = [
                'nome'           => $row['nome'],
                'valor_total'    => $total,
                'valor_guardado' => $guardado,
                'prazo'          => $row['prazo'],
                'progresso'      => $progresso,
            ];
        }
        $stmt->close();

        return [
            'tipo'  => 'sucesso',
            'acao'  => 'consultar_metas',
            'metas' => $lista,
        ];
    }
}
