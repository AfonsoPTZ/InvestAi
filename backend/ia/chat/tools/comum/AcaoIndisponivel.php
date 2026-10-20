<?php
/**
 * backend/ia/chat/tools/comum/AcaoIndisponivel.php
 *
 * Tool acionada quando o usuário pede algo fora do escopo do app.
 * O ChatAgent faz short-circuit ao receber esta tool e retorna mensagem fixa.
 */

class AcaoIndisponivel {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'acao_indisponivel',
            'description' => 'Use SOMENTE quando o usuário fizer um pedido explícito de funcionalidade que não existe no sistema: exportar dados, sub-categorias de orçamento, integração com bancos, relatórios avançados. NÃO use para criação de metas financeiras (use criar_meta) nem para saudações, agradecimentos, perguntas gerais ou bate-papo — esses casos usam a tool "conversa".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        return ['tipo' => 'indisponivel'];
    }
}
