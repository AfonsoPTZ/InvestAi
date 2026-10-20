<?php
/**
 * backend/ia/chat/tools/Conversa.php
 *
 * Tool de fallback para mensagens não financeiras.
 * Garante que o campo "acao" retorne "conversa" no contrato de saída.
 */

class Conversa {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'conversa',
            'description' => 'Use para qualquer mensagem que não seja uma operação financeira: cumprimentos, perguntas gerais, agradecimentos, bate-papo. Exemplos: "oi", "obrigado", "como você funciona?", "qual é o seu nome?", "tudo bem?".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => new stdClass(),
            ],
        ];
    }

    public function execute(array $params): array {
        return ['tipo' => 'conversa'];
    }
}
