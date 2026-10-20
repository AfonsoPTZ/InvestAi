<?php
/**
 * backend/ia/chat/tools/CriarGanho.php
 */

class CriarGanho {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'criar_ganho',
            'description' => 'Registra uma receita/ganho/renda do usuário. Use quando o usuário indicar que RECEBEU dinheiro, GANHOU, fez um trabalho/serviço/freelance, ou tem renda de qualquer fonte. Palavras-chave: "recebi", "ganhei", "entrou", "me pagaram", "fiz um freelancer/serviço/trampo", "vendi", "salário", "freela", "bico", "renda extra". Exemplos: "recebi meu salário de 4500", "ganhei 800 de freela", "fiz um freelancer de 100 reais", "vendi um produto por 200", "me pagaram 300 pelo serviço". ATENÇÃO: "fiz um freelancer/serviço/trabalho" É GANHO, não despesa — o usuário prestou um serviço e recebeu. Se o valor estiver ausente, use pedir_confirmacao.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'valor'     => [
                        'type'        => 'number',
                        'description' => 'Valor numérico do ganho (ex: 4500.00). Obrigatório.',
                    ],
                    'descricao' => [
                        'type'        => 'string',
                        'description' => 'Descrição curta do ganho, gerada pelo contexto. Exemplos: "recebi meu salário" → "Salário mensal", "ganhei freela de design" → "Trabalho freelance design". NUNCA incluir valor numérico.',
                    ],
                    'data'      => [
                        'type'        => 'string',
                        'description' => 'Data no formato YYYY-MM-DD. Se não informada, usar a data de hoje.',
                    ],
                    'categoria' => [
                        'type'        => 'string',
                        'description' => 'Categoria do ganho (opcional). Exemplos: Salário, Freelance, Investimentos, Outros.',
                    ],
                ],
                'required'   => ['valor'],
            ],
        ];
    }

    public function execute(array $params): array {
        $valor     = isset($params['valor'])     ? (float)  $params['valor']     : null;
        $descricao = isset($params['descricao']) ? (string) $params['descricao'] : 'Ganho via chat';
        $data      = isset($params['data'])      ? (string) $params['data']      : date('Y-m-d');
        $categoria = isset($params['categoria']) ? (string) $params['categoria'] : null;

        if (!$valor || $valor <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi o valor do ganho. Pode informar novamente?'];
        }

        $categoria_id = null;
        if ($categoria) {
            $cat_resolvida = CategoriaResolver::resolverCategoria($this->conexao, 'ganho', $categoria);
            if ($cat_resolvida) {
                $categoria    = $cat_resolvida;
                $categoria_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'ganho', $cat_resolvida);
            }
        }

        $stmt = $this->conexao->prepare(
            "INSERT INTO ganhos (usuario_id, descricao, valor, data_ganho, fixo, categoria_id)
             VALUES (?, ?, ?, ?, 0, ?)"
        );
        $stmt->bind_param('isdsi', $this->usuario_id, $descricao, $valor, $data, $categoria_id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao salvar o ganho.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'criar_ganho',
            'descricao' => $descricao,
            'categoria' => $categoria ?? 'sem categoria',
            'valor'     => $valor,
            'data'      => $data,
        ];
    }
}
