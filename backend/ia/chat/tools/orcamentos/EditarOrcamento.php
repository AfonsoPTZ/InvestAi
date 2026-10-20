<?php
/**
 * backend/ia/chat/tools/EditarOrcamento.php
 */

class EditarOrcamento {

    public function __construct(
        private SqliteConnection $conexao,
        private int    $usuario_id,
        private int    $mes,
        private int    $ano
    ) {}

    public function getDefinition(): array {
        return [
            'name'        => 'editar_orcamento',
            'description' => 'Altera o limite de um orçamento já existente. Use quando o usuário quiser mudar, atualizar ou redefinir o limite de um orçamento. Exemplos: "muda meu orçamento de alimentação para 600", "aumenta o limite de transporte para 400", "atualiza o budget de saúde para 250".',
            'parameters'  => [
                'type'       => 'object',
                'properties' => [
                    'categoria' => [
                        'type'        => 'string',
                        'description' => 'Nome da categoria de despesa do orçamento a editar.',
                    ],
                    'limite'    => [
                        'type'        => 'number',
                        'description' => 'Novo limite mensal em reais.',
                    ],
                    'mes'       => [
                        'type'        => 'integer',
                        'description' => 'Mês do orçamento (1-12). Se não informado, usar o mês atual.',
                    ],
                    'ano'       => [
                        'type'        => 'integer',
                        'description' => 'Ano do orçamento. Se não informado, usar o ano atual.',
                    ],
                ],
                'required'   => ['categoria', 'limite'],
            ],
        ];
    }

    public function execute(array $params): array {
        $categoria = isset($params['categoria']) ? (string) $params['categoria'] : null;
        $limite    = isset($params['limite'])    ? (float)  $params['limite']    : null;
        $mes       = isset($params['mes'])       ? (int)    $params['mes']       : $this->mes;
        $ano       = isset($params['ano'])       ? (int)    $params['ano']       : $this->ano;

        if (!$categoria || !$limite || $limite <= 0) {
            return ['tipo' => 'erro', 'mensagem' => 'Informe a categoria e o novo limite do orçamento.'];
        }

        $categoria_resolvida = $this->resolverCategoria($categoria);
        if (!$categoria_resolvida) {
            return ['tipo' => 'erro', 'mensagem' => "Categoria \"$categoria\" não encontrada."];
        }

        $categoria_id = CategoriaResolver::buscarIdCategoria($this->conexao, 'despesa', $categoria_resolvida);
        if (!$categoria_id) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro interno ao buscar a categoria.'];
        }

        $stmt = $this->conexao->prepare(
            "SELECT id FROM orcamento_categorias
             WHERE usuario_id = ? AND categoria_id = ? AND mes = ? AND ano = ?"
        );
        $stmt->bind_param('iiii', $this->usuario_id, $categoria_id, $mes, $ano);
        $stmt->execute();
        $existe = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$existe) {
            $mes_nome = $this->nomeMes($mes);
            return ['tipo' => 'erro', 'mensagem' => "Não há orçamento de \"$categoria_resolvida\" em {$mes_nome}/{$ano} para editar."];
        }

        $stmt = $this->conexao->prepare(
            "UPDATE orcamento_categorias SET limite_mensal = ?
             WHERE usuario_id = ? AND categoria_id = ? AND mes = ? AND ano = ?"
        );
        $stmt->bind_param('diiii', $limite, $this->usuario_id, $categoria_id, $mes, $ano);
        $ok = $stmt->execute();
        $stmt->close();

        if (!$ok) {
            return ['tipo' => 'erro', 'mensagem' => 'Erro ao atualizar o orçamento.'];
        }

        return [
            'tipo'      => 'sucesso',
            'acao'      => 'editar_orcamento',
            'categoria' => $categoria_resolvida,
            'limite'    => $limite,
            'mes'       => $mes,
            'ano'       => $ano,
        ];
    }

    private function resolverCategoria(string $nome): ?string {
        return CategoriaResolver::resolverCategoria($this->conexao, 'despesa', $nome);
    }

    private function nomeMes(int $mes): string {
        $nomes = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return $nomes[$mes] ?? (string) $mes;
    }
}
