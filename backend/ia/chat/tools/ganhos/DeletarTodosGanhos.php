<?php
/**
 * backend/ia/chat/tools/DeletarTodosGanhos.php
 */

class DeletarTodosGanhos {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_todos_ganhos',
            'description' => 'Apaga TODOS os ganhos do usuário. Use quando o usuário pedir, solicitar ou perguntar se pode apagar/deletar/excluir/remover/limpar todos os ganhos. Perguntas como "você pode apagar meus ganhos?", "consegue deletar tudo?" são PEDIDOS — use esta tool. Exemplos: "apaga todos os ganhos", "remove minha renda", "limpa os ganhos", "vc pode apagar meus ganhos?". Use confirmado=false na primeira vez (retorna pedido de confirmação). Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo" em resposta a uma confirmação anterior.',
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
                'mensagem' => '⚠️ Tem certeza que quer apagar TODOS os seus ganhos? Essa ação não pode ser desfeita!',
            ];
        }

        $resumo = BulkDeleteHelper::obterResumo($this->conexao, 'ganhos', 'valor', $this->usuario_id);
        $total  = $resumo['total'];
        $soma   = $resumo['soma'];

        if ($total === 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Você não tem nenhum ganho cadastrado.'];
        }

        $delete = BulkDeleteHelper::apagarTudo($this->conexao, 'ganhos', $this->usuario_id);
        $ok = $delete['ok'];
        $apagados = $delete['apagados'];

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao apagar os ganhos.'];
        }

        return [
            'tipo'     => 'sucesso',
            'acao'     => 'deletar_todos_ganhos',
            'apagados' => $apagados,
            'soma'     => $soma,
        ];
    }
}
