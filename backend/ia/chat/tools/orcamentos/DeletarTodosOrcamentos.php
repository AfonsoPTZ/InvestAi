<?php
/**
 * backend/ia/chat/tools/DeletarTodosOrcamentos.php
 */

class DeletarTodosOrcamentos {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_todos_orcamentos',
            'description' => 'Apaga TODOS os orçamentos do usuário. Use quando o usuário pedir, solicitar ou perguntar se pode apagar, excluir, remover ou limpar todos os orçamentos. Exemplos: "apaga todos os orçamentos", "remove meus limites", "limpa todos os budgets", "você pode apagar meus orçamentos?". Use confirmado=false na primeira vez (retorna pedido de confirmação). Use confirmado=true somente se o usuário já confirmou a ação.',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'confirmado' => [
                        'type'        => 'boolean',
                        'description' => 'false = mostrar aviso pedindo confirmação ao usuário. true = usuário já confirmou, executar a deleção.',
                    ],
                ],
                'required'   => ['confirmado'],
            ],
        ];
    }

    public function execute(array $params): array {
        if (!($params['confirmado'] ?? false)) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => '⚠️ Tem certeza que quer apagar TODOS os seus orçamentos? Essa ação não pode ser desfeita!',
            ];
        }

        $resumo = BulkDeleteHelper::obterResumo($this->conexao, 'orcamento_categorias', 'limite_mensal', $this->usuario_id);
        $total  = $resumo['total'];
        $soma   = $resumo['soma'];

        if ($total === 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Você não tem nenhum orçamento cadastrado.'];
        }

        $delete = BulkDeleteHelper::apagarTudo($this->conexao, 'orcamento_categorias', $this->usuario_id);
        $ok = $delete['ok'];
        $apagados = $delete['apagados'];

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao apagar os orçamentos.'];
        }

        return [
            'tipo'     => 'sucesso',
            'acao'     => 'deletar_todos_orcamentos',
            'apagados' => $apagados,
            'soma'     => $soma,
        ];
    }
}