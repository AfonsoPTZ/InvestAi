<?php
/**
 * backend/ia/chat/tools/metas/CriarMeta.php
 */

class CriarMeta {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'criar_meta',
            'description' => 'Registra uma meta financeira do usuário. Use quando o usuário quiser CRIAR, DEFINIR ou ESTABELECER uma meta ou objetivo financeiro futuro — algo que quer comprar, juntar dinheiro, economizar para. Exemplos: "quero comprar uma moto até dezembro", "meta de juntar 5 mil para viajar", "quero guardar dinheiro para um notebook", "criar meta de emergência de 10 mil". NUNCA use para registrar um depósito ou aporte em meta existente — esses são criar_aporte. Se o valor estiver ausente e não for possível inferir, use pedir_confirmacao.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'nome' => [
                        'type'        => 'string',
                        'description' => 'Nome curto e descritivo da meta (3-50 caracteres). Exemplos: "Comprar moto", "Viagem para Europa", "Notebook novo", "Reserva de emergência". Gere a partir do contexto, não copie a mensagem inteira.',
                    ],
                    'valor_total' => [
                        'type'        => 'number',
                        'description' => 'Valor total que o usuário quer atingir (ex: 8000.00). Obrigatório.',
                    ],
                    'prazo' => [
                        'type'        => 'string',
                        'description' => 'Data limite no formato YYYY-MM-DD. Infira a partir do contexto se mencionado (ex: "até dezembro" → último dia de dezembro do ano atual). Omitir se não informado.',
                    ],
                ],
                'required'   => ['nome', 'valor_total'],
            ],
        ];
    }

    public function execute(array $params): array {
        $nome        = isset($params['nome'])        ? trim((string) $params['nome'])        : null;
        $valor_total = isset($params['valor_total']) ? (float)       $params['valor_total']  : null;
        $prazo       = isset($params['prazo'])       ? (string)      $params['prazo']        : null;

        if (!$nome || mb_strlen($nome) < 3) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi o nome da meta. Pode informar novamente?'];
        }

        if (!$valor_total || $valor_total <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Não entendi o valor da meta. Pode informar novamente?'];
        }

        // Valida formato de prazo se fornecido
        if ($prazo && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) {
            $prazo = null;
        }

        $stmt = $this->conexao->prepare(
            "INSERT INTO metas (usuario_id, nome, valor_total, prazo)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->bind_param('isds', $this->usuario_id, $nome, $valor_total, $prazo);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao salvar a meta.'];
        }

        return [
            'tipo'        => 'sucesso',
            'acao'        => 'criar_meta',
            'nome'        => $nome,
            'valor_total' => $valor_total,
            'prazo'       => $prazo,
        ];
    }
}
