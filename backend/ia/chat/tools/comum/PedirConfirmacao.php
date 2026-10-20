<?php
/**
 * backend/ia/chat/tools/PedirConfirmacao.php
 *
 * Tool especial: não executa nenhuma ação no banco.
 * O ChatAgent faz short-circuit antes de chamar execute() —
 * esta implementação existe apenas por completude do contrato.
 */

class PedirConfirmacao {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'pedir_confirmacao',
            'description' => 'Use quando: (1) um parâmetro obrigatório estiver genuinamente ausente e impossível de inferir; (2) a intenção for ambígua (ex: não dá pra saber se é despesa ou ganho); (3) quiser confirmar antes de executar quando há incerteza razoável. A pergunta deve ser ESPECÍFICA e contextual — nunca genérica. Exemplos de uso: "quero adicionar uma despesa" (sem valor) → "Qual foi o valor da despesa? 💸"; "25 reais" (sem contexto) → "R$25 em quê? Pode me dizer a categoria?"; para confirmação: "Só pra confirmar — despesa de R$80 em Saúde (academia), certo?". Exemplos de quando NÃO usar: "gastei 25 no almoço" → criar_despesa direto (Alimentação, R$25); "paguei 150 de academia" → criar_despesa direto (Saúde, R$150); "recebi 3 mil de salário" → criar_ganho direto. Nunca invente valores nem categorias improváveis — prefira perguntar.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'pergunta' => [
                        'type'        => 'string',
                        'description' => 'Pergunta clara, objetiva e específica para obter o dado faltante. Exemplos: "Qual foi o valor da despesa no mercado? 🛒", "Qual categoria se encaixa melhor nessa despesa?".',
                    ],
                ],
                'required'   => ['pergunta'],
            ],
        ];
    }

    public function execute(array $params): array {
        return [
            'tipo'    => 'confirmacao',
            'pergunta' => $params['pergunta'] ?? 'Pode me dar mais detalhes? 🤔',
        ];
    }
}
