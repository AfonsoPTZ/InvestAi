<?php
/**
 * backend/ia/chat/tools/comum/BulkDeleteHelper.php
 */

class BulkDeleteHelper {

    public static function obterResumo(SqliteConnection $conexao, string $tabela, string $colunaValor, int $usuario_id): array {
        $stmt = $conexao->prepare(
            "SELECT COUNT(*) AS total, COALESCE(SUM($colunaValor), 0) AS soma
             FROM $tabela WHERE usuario_id = ?"
        );
        $stmt->bind_param('i', $usuario_id);
        $stmt->execute();
        $info = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return [
            'total' => (int)   ($info['total'] ?? 0),
            'soma'  => (float) ($info['soma'] ?? 0),
        ];
    }

    public static function apagarTudo(SqliteConnection $conexao, string $tabela, int $usuario_id): array {
        $stmt = $conexao->prepare("DELETE FROM $tabela WHERE usuario_id = ?");
        $stmt->bind_param('i', $usuario_id);
        $ok = $stmt->execute();
        $apagados = $stmt->affected_rows;
        $stmt->close();

        return [
            'ok' => $ok,
            'apagados' => $apagados,
        ];
    }

    public static function nomeMes(int $mes): string {
        $nomes = ['', 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho',
                  'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];
        return $nomes[$mes] ?? (string) $mes;
    }
}