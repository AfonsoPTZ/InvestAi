<?php
/**
 * backend/ia/chat/tools/DeletarTodasDespesas.php
 */

class DeletarTodasDespesas {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'deletar_todas_despesas',
            'description' => 'Apaga TODAS as despesas do usuário. Use quando o usuário pedir, solicitar ou perguntar se pode apagar/deletar/excluir/remover/limpar todas as despesas. Perguntas como "você pode apagar minhas despesas?", "consegue deletar tudo?", "tem como limpar?" são PEDIDOS — use esta tool. Exemplos: "delete minhas despesas", "apaga todas as despesas", "remove tudo", "limpa minhas despesas", "vc pode apagar todas minhas despesas?", "consegue excluir tudo?". Use confirmado=false na primeira vez (retorna pedido de confirmação). Use confirmado=true somente se o usuário já disse "sim", "pode", "confirmo", "apaga mesmo" em resposta a uma confirmação anterior.',
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
        // Se ainda não confirmou, retornar pedido de confirmação
        if (!($params['confirmado'] ?? false)) {
            return [
                'tipo'     => 'precisa_confirmacao',
                'mensagem' => '⚠️ Tem certeza que quer apagar TODAS as suas despesas? Essa ação não pode ser desfeita!',
            ];
        }

        $resumo = BulkDeleteHelper::obterResumo($this->conexao, 'despesas', 'valor', $this->usuario_id);
        $total  = $resumo['total'];
        $soma   = $resumo['soma'];

        if ($total === 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Você não tem nenhuma despesa cadastrada.'];
        }

        $delete = BulkDeleteHelper::apagarTudo($this->conexao, 'despesas', $this->usuario_id);
        $ok = $delete['ok'];
        $apagadas = $delete['apagados'];

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao apagar as despesas.'];
        }

        return [
            'tipo'     => 'sucesso',
            'acao'     => 'deletar_todas_despesas',
            'apagadas' => $apagadas,
            'soma'     => $soma,
        ];
    }
}
