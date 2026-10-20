<?php
// backend/database/Database.php — Abre (e cria, se preciso) o banco SQLite do projeto

require_once __DIR__ . '/../config/ConfigHelper.php';
require_once __DIR__ . '/SqliteCompat.php';

class Database
{
    private static ?SqliteConnection $conn = null;

    /**
     * Caminho do arquivo SQLite. Prioridade:
     *  1. DB_SQLITE_PATH (absoluto, ou relativo à raiz do projeto)
     *  2. Vercel: /tmp (único diretório gravável do ambiente serverless)
     *  3. backend/database/data/investai.sqlite
     */
    public static function path(): string
    {
        ConfigHelper::load();
        $root = dirname(__DIR__, 2);

        $custom = ConfigHelper::get('DB_SQLITE_PATH');
        if ($custom) {
            $isAbsolute = preg_match('#^([A-Za-z]:)?[\\\\/]#', $custom) === 1;
            return $isAbsolute ? $custom : $root . '/' . $custom;
        }

        if (ConfigHelper::get('VERCEL')) {
            return rtrim(sys_get_temp_dir(), '/\\') . '/investai.sqlite';
        }

        return __DIR__ . '/data/investai.sqlite';
    }

    public static function connect(): SqliteConnection
    {
        if (self::$conn !== null) {
            return self::$conn;
        }

        $path = self::path();
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Não foi possível criar o diretório do banco: {$dir}");
        }

        $conn = new SqliteConnection($path);
        self::migrate($conn);

        return self::$conn = $conn;
    }

    /** Cria as tabelas na primeira execução (banco vazio). */
    private static function migrate(SqliteConnection $conn): void
    {
        $existe = $conn->pdo()
            ->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'usuarios'")
            ->fetchColumn();
        if ($existe) {
            return;
        }

        $conn->exec(file_get_contents(__DIR__ . '/schema_sqlite.sql'));

        $seed = filter_var(ConfigHelper::get('DB_SEED', 'false'), FILTER_VALIDATE_BOOLEAN);
        if ($seed) {
            $conn->exec(file_get_contents(__DIR__ . '/seed_sqlite.sql'));
        }
    }
}
