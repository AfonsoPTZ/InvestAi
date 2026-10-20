<?php
/**
 * backend/ia/chat/tools/aportes/ConsultarAportes.php
 */

class ConsultarAportes {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'consultar_aportes',
            'description' => 'Retorna o histórico de aportes (depósitos) feitos em metas. Use quando o usuário quiser ver os aportes que fez, o histórico de contribuições ou os depósitos em uma meta específica. Exemplos: "ver aportes da meta da moto", "histórico de contribuições", "quanto já depositei na viagem?", "minhas últimas contribuições".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'meta_nome_busca' => [
                        'type'        => 'string',
                        'description' => 'Trecho do nome da meta para filtrar aportes (opcional). Omitir para ver aportes de todas as metas.',
                    ],
                ],
            ],
        ];
    }

    public function execute(array $params): array {
        $meta_busca = trim($params['meta_nome_busca'] ?? '');

        if ($meta_busca !== '') {
            $like = '%' . $meta_busca . '%';
            $stmt = $this->conexao->prepare(
                "SELECT a.valor, a.data_aporte, m.nome AS meta_nome
                 FROM aportes a
                 JOIN metas m ON a.meta_id = m.id
                 WHERE a.usuario_id = ? AND m.nome LIKE ?
                 ORDER BY a.data_aporte DESC, a.id DESC
                 LIMIT 10"
            );
            $stmt->bind_param('is', $this->usuario_id, $like);
        } else {
            $stmt = $this->conexao->prepare(
                "SELECT a.valor, a.data_aporte, m.nome AS meta_nome
                 FROM aportes a
                 JOIN metas m ON a.meta_id = m.id
                 WHERE a.usuario_id = ?
                 ORDER BY a.data_aporte DESC, a.id DESC
                 LIMIT 10"
            );
            $stmt->bind_param('i', $this->usuario_id);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        $lista = [];
        while ($row = $result->fetch_assoc()) {
            $lista[] = [
                'meta_nome'   => $row['meta_nome'],
                'valor'       => (float) $row['valor'],
                'data_aporte' => $row['data_aporte'],
            ];
        }
        $stmt->close();

        return [
            'tipo'    => 'sucesso',
            'acao'    => 'consultar_aportes',
            'aportes' => $lista,
        ];
    }
}
