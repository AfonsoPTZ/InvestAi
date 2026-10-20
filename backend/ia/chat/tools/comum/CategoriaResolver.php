<?php
/**
 * backend/ia/chat/tools/comum/CategoriaResolver.php
 */

class CategoriaResolver {

    public static function resolverCategoria(SqliteConnection $conexao, string $tipo, string $nome): ?string {
        $norm = self::normalizar($nome);
        $stmt = $conexao->prepare(
            "SELECT nome FROM categorias WHERE tipo = ? ORDER BY nome"
        );
        $stmt->bind_param('s', $tipo);
        $stmt->execute();
        $result = $stmt->get_result();
        $cats   = [];
        while ($row = $result->fetch_assoc()) $cats[] = $row['nome'];
        $stmt->close();

        foreach ($cats as $cat) {
            if (self::normalizar($cat) === $norm) return $cat;
        }
        foreach ($cats as $cat) {
            if (str_contains(self::normalizar($cat), $norm)) return $cat;
        }
        foreach ($cats as $cat) {
            if (str_contains($norm, self::normalizar($cat))) return $cat;
        }
        $tokens = preg_split('/\s+/', $norm);
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 4) continue;
            foreach ($cats as $cat) {
                if (str_contains(self::normalizar($cat), $token)) return $cat;
            }
        }
        return null;
    }

    public static function buscarIdCategoria(SqliteConnection $conexao, string $tipo, string $nome): ?int {
        $stmt = $conexao->prepare(
            "SELECT id FROM categorias WHERE nome = ? AND tipo = ? LIMIT 1"
        );
        $stmt->bind_param('ss', $nome, $tipo);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ? (int) $row['id'] : null;
    }

    public static function normalizar(string $texto): string {
        $texto = mb_strtolower($texto, 'UTF-8');
        $de    = ['á','à','â','ã','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','ô','õ','ö','ú','ù','û','ü','ç','ñ'];
        $para  = ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','n'];
        return str_replace($de, $para, $texto);
    }
}