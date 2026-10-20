<?php
/**
 * backend/ia/chat/tools/CriarDespesa.php
 */

class CriarDespesa {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'criar_despesa',
            'description' => 'Registra uma despesa/gasto do usuário no banco. Use quando o usuário disser que GASTOU, PAGOU, COMPROU, CONSUMIU algo — verbos no passado indicam que o dinheiro SAIU do bolso. Exemplos: "gastei 50 no mercado", "paguei 30 de uber", "comprei remédio por 20", "comi uma coxinha por 8 reais". Inferir categoria automaticamente: almoço/comida/mercado → Alimentação, remédio/consulta/academia → Saúde, uber/gasolina/ônibus → Transporte, netflix/spotify/cinema → Entretenimento, luz/água/internet → Utilidades Domésticas, aluguel/condomínio → Habitação, curso/faculdade → Educação, roupa/tênis → Vestuário e Acessórios. NUNCA use esta função para trabalhos/serviços PRESTADOS (freelancer, bico, trampo, venda de produto) — esses são GANHOS, use criar_ganho. Se o valor estiver ausente e não for possível inferir, use pedir_confirmacao.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'valor'     => [
                        'type'        => 'number',
                        'description' => 'Valor numérico da despesa (ex: 50.00). Obrigatório.',
                    ],
                    'descricao' => [
                        'type'        => 'string',
                        'description' => 'Descrição curta (3-6 palavras) do que foi gasto, gerada a partir do contexto. Exemplos: "gastei 25 com almoço" → "Almoço do dia", "paguei Netflix" → "Assinatura Netflix", "abasteci o carro" → "Abastecimento do carro". NUNCA copiar a mensagem inteira, NUNCA incluir valor numérico.',
                    ],
                    'categoria' => [
                        'type'        => 'string',
                        'description' => 'Nome exato da categoria de despesa, inferido pelo contexto da mensagem.',
                    ],
                    'data'      => [
                        'type'        => 'string',
                        'description' => 'Data no formato YYYY-MM-DD. Se não informada, usar a data de hoje.',
                    ],
                ],
                'required'   => ['valor', 'categoria'],
            ],
        ];
    }

    public function execute(array $params): array {
        $valor     = isset($params['valor'])     ? (float)  $params['valor']     : null;
        $descricao = isset($params['descricao']) ? (string) $params['descricao'] : 'Despesa via chat';
        $categoria = isset($params['categoria']) ? (string) $params['categoria'] : null;
        $data      = isset($params['data'])      ? (string) $params['data']      : date('Y-m-d');

        if (!$valor || $valor <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi o valor da despesa. Pode informar novamente?'];
        }

        $categoria_id = null;
        if ($categoria) {
            $cat_resolvida = CategoriaResolver::resolverCategoria($this->conexao, 'despesa', $categoria);
            if ($cat_resolvida) {
                $categoria    = $cat_resolvida;
                $categoria_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'despesa', $cat_resolvida);
            }
        }

        $stmt = $this->conexao->prepare(
            "INSERT INTO despesas (usuario_id, descricao, valor, data_despesa, fixo, categoria_id)
             VALUES (?, ?, ?, ?, 0, ?)"
        );
        $stmt->bind_param('isdsi', $this->usuario_id, $descricao, $valor, $data, $categoria_id);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao salvar a despesa.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'criar_despesa',
            'descricao' => $descricao,
            'categoria' => $categoria ?? 'sem categoria',
            'valor'     => $valor,
            'data'      => $data,
        ];
    }
}
