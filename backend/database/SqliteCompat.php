<?php
// backend/database/SqliteCompat.php — Camada de compatibilidade mysqli -> SQLite (PDO)
//
// Expõe a mesma API do mysqli usada em todo o projeto (prepare/bind_param/execute/
// get_result/fetch_assoc/insert_id/affected_rows/begin_transaction...) sobre um
// arquivo SQLite, para que as 70+ páginas de API não precisem ser reescritas.
// Também traduz os poucos trechos de SQL exclusivos do MySQL (NOW(), MONTH(),
// DATE_FORMAT(), ON DUPLICATE KEY UPDATE...) para o dialeto do SQLite.

if (!class_exists('mysqli_sql_exception', false)) {
    class mysqli_sql_exception extends RuntimeException
    {
        public function getSqlState(): string
        {
            return 'HY000';
        }
    }
}

if (!defined('MYSQLI_ASSOC')) {
    define('MYSQLI_ASSOC', 1);
    define('MYSQLI_NUM', 2);
    define('MYSQLI_BOTH', 3);
}

class SqlDialect
{
    /** Alvo do ON CONFLICT para cada tabela que usa ON DUPLICATE KEY UPDATE. */
    private const CONFLICT_TARGETS = [
        'noticias_financeiras' => 'url',
        'noticias_ai'          => 'usuario_id, noticia_hash',
        'orcamento_categorias' => 'usuario_id, categoria_id, mes, ano',
        'sugestoes_economia'   => 'usuario_id, categoria_nome, mes, ano',
    ];

    private const NOW = "datetime('now','localtime')";
    private const TODAY = "date('now','localtime')";

    public static function translate(string $sql): string
    {
        // Intervalos: DATE_SUB(NOW(), INTERVAL 24 HOUR) / NOW() - INTERVAL 7 DAY
        $interval = function (array $m): string {
            $unit = strtolower($m[2]);
            if (substr($unit, -1) !== 's') {
                $unit .= 's';
            }
            return "datetime('now','localtime','-{$m[1]} {$unit}')";
        };
        $sql = preg_replace_callback('/DATE_SUB\(\s*NOW\(\)\s*,\s*INTERVAL\s+(\d+)\s+(\w+)\s*\)/i', $interval, $sql);
        $sql = preg_replace_callback('/NOW\(\)\s*-\s*INTERVAL\s+(\d+)\s+(\w+)/i', $interval, $sql);

        // DATE_FORMAT(expr, 'fmt') -> strftime('fmt', expr)  (argumentos invertidos)
        $sql = preg_replace(
            "/DATE_FORMAT\(\s*((?:[^(),]|\((?:[^()]|\([^()]*\))*\))+?)\s*,\s*('[^']*')\s*\)/i",
            'strftime($2, $1)',
            $sql
        );

        $sql = preg_replace('/\bNOW\(\)/i', self::NOW, $sql);
        $sql = preg_replace('/\bCURRENT_TIMESTAMP\b/i', self::NOW, $sql);
        $sql = preg_replace('/\bCURDATE\(\)/i', self::TODAY, $sql);

        $sql = preg_replace('/\bMONTH\(([^()]+)\)/i', "CAST(strftime('%m', $1) AS INTEGER)", $sql);
        $sql = preg_replace('/\bYEAR\(([^()]+)\)/i', "CAST(strftime('%Y', $1) AS INTEGER)", $sql);

        $sql = preg_replace('/\bGREATEST\(/i', 'MAX(', $sql);
        $sql = preg_replace('/\bLEAST\(/i', 'MIN(', $sql);
        $sql = preg_replace('/\bINSERT\s+IGNORE\b/i', 'INSERT OR IGNORE', $sql);

        return self::translateUpsert($sql);
    }

    private static function translateUpsert(string $sql): string
    {
        if (!preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $sql)) {
            return $sql;
        }

        $table = null;
        if (preg_match('/INSERT\s+INTO\s+`?(\w+)`?/i', $sql, $m)) {
            $table = strtolower($m[1]);
        }
        if ($table === null || !isset(self::CONFLICT_TARGETS[$table])) {
            throw new mysqli_sql_exception("ON DUPLICATE KEY UPDATE sem alvo de conflito mapeado para a tabela '{$table}'.");
        }

        $parts = preg_split('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $sql, 2);
        $update = preg_replace('/\bVALUES\(\s*`?(\w+)`?\s*\)/i', 'excluded.$1', $parts[1]);

        return $parts[0] . 'ON CONFLICT(' . self::CONFLICT_TARGETS[$table] . ') DO UPDATE SET' . $update;
    }
}

class SqliteResult implements IteratorAggregate
{
    public int $num_rows;
    public int $field_count;
    private array $rows;
    private int $pos = 0;

    public function __construct(array $rows)
    {
        $this->rows = $rows;
        $this->num_rows = count($rows);
        $this->field_count = $rows ? count($rows[0]) : 0;
    }

    public function fetch_assoc(): ?array
    {
        return $this->rows[$this->pos++] ?? null;
    }

    public function fetch_row(): ?array
    {
        $row = $this->fetch_assoc();
        return $row === null ? null : array_values($row);
    }

    public function fetch_array(int $mode = MYSQLI_BOTH): ?array
    {
        $row = $this->fetch_assoc();
        if ($row === null) {
            return null;
        }
        return match ($mode) {
            MYSQLI_ASSOC => $row,
            MYSQLI_NUM   => array_values($row),
            default      => $row + array_values($row),
        };
    }

    public function fetch_object(): ?object
    {
        $row = $this->fetch_assoc();
        return $row === null ? null : (object) $row;
    }

    public function fetch_all(int $mode = MYSQLI_NUM): array
    {
        $rows = array_slice($this->rows, $this->pos);
        $this->pos = $this->num_rows;
        return match ($mode) {
            MYSQLI_ASSOC => $rows,
            MYSQLI_NUM   => array_map('array_values', $rows),
            default      => array_map(fn($r) => $r + array_values($r), $rows),
        };
    }

    public function data_seek(int $offset): bool
    {
        $this->pos = max(0, min($offset, $this->num_rows));
        return true;
    }

    public function free(): void {}
    public function close(): void {}
    public function free_result(): void {}

    public function getIterator(): Iterator
    {
        return new ArrayIterator($this->rows);
    }
}

class SqliteStatement
{
    public int $affected_rows = -1;
    public int $insert_id = 0;
    public int $num_rows = 0;
    public string $error = '';
    public int $errno = 0;

    private PDOStatement $stmt;
    private SqliteConnection $conn;
    private bool $isInsert;
    private array $types = [];
    private array $params = [];
    private ?SqliteResult $result = null;

    public function __construct(SqliteConnection $conn, PDOStatement $stmt, string $sql)
    {
        $this->conn = $conn;
        $this->stmt = $stmt;
        $this->isInsert = (bool) preg_match('/^\s*(INSERT|REPLACE)\b/i', $sql);
    }

    public function bind_param(string $types, &...$vars): bool
    {
        $this->types = str_split($types);
        $this->params = [];
        foreach ($vars as $i => &$var) {
            $this->params[$i] = &$var;
        }
        return true;
    }

    public function execute(?array $params = null): bool
    {
        $values = $params !== null ? array_values($params) : $this->params;
        $types = $params !== null ? [] : $this->types;

        try {
            foreach (array_values($values) as $i => $value) {
                $this->stmt->bindValue($i + 1, $value, self::pdoType($types[$i] ?? 's', $value));
            }
            $this->stmt->execute();

            if ($this->stmt->columnCount() > 0) {
                $rows = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
                $this->stmt->closeCursor();
                foreach ($rows as &$row) {
                    foreach ($row as &$cell) {
                        // Remove ruído de ponto flutuante (ex.: SUM de REAL = 1349.6500000000001)
                        if (is_float($cell)) {
                            $cell = round($cell, 8);
                        }
                    }
                }
                unset($row, $cell);
                $this->result = new SqliteResult($rows);
                $this->num_rows = $this->result->num_rows;
                $this->affected_rows = -1;
            } else {
                $this->result = null;
                $this->affected_rows = $this->stmt->rowCount();
                if ($this->isInsert) {
                    $this->insert_id = (int) $this->conn->pdo()->lastInsertId();
                    $this->conn->insert_id = $this->insert_id;
                }
            }
            $this->conn->affected_rows = $this->affected_rows;
            $this->error = '';
            $this->errno = 0;
            return true;
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = SqliteConnection::errno($e);
            throw SqliteConnection::wrap($e);
        }
    }

    public function get_result(): SqliteResult|false
    {
        return $this->result ?? false;
    }

    public function store_result(): bool
    {
        return true;
    }

    public function free_result(): void {}

    public function close(): bool
    {
        return true;
    }

    private static function pdoType(string $type, mixed $value): int
    {
        if ($value === null) {
            return PDO::PARAM_NULL;
        }
        if ($type === 'i' && (is_int($value) || is_bool($value) || (is_string($value) && preg_match('/^-?\d+$/', $value)))) {
            return PDO::PARAM_INT;
        }
        return PDO::PARAM_STR;
    }
}

class SqliteConnection
{
    public int $insert_id = 0;
    public int $affected_rows = 0;
    public string $error = '';
    public int $errno = 0;
    public ?string $connect_error = null;
    public int $connect_errno = 0;

    private PDO $pdo;

    public function __construct(string $path)
    {
        try {
            $this->pdo = new PDO('sqlite:' . $path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        } catch (PDOException $e) {
            throw self::wrap($e);
        }
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prepare(string $sql): SqliteStatement
    {
        $sql = SqlDialect::translate($sql);
        try {
            return new SqliteStatement($this, $this->pdo->prepare($sql), $sql);
        } catch (PDOException $e) {
            $this->error = $e->getMessage();
            $this->errno = self::errno($e);
            throw self::wrap($e);
        }
    }

    public function query(string $sql): SqliteResult|bool
    {
        $stmt = $this->prepare($sql);
        $stmt->execute();
        return $stmt->get_result() ?: true;
    }

    public function exec(string $sql): void
    {
        try {
            $this->pdo->exec($sql);
        } catch (PDOException $e) {
            throw self::wrap($e);
        }
    }

    public function begin_transaction(): bool
    {
        return $this->pdo->inTransaction() ? true : $this->pdo->beginTransaction();
    }

    public function commit(): bool
    {
        return $this->pdo->inTransaction() ? $this->pdo->commit() : true;
    }

    public function rollback(): bool
    {
        return $this->pdo->inTransaction() ? $this->pdo->rollBack() : true;
    }

    public function real_escape_string(string $value): string
    {
        return str_replace("'", "''", $value);
    }

    public function set_charset(string $charset): bool
    {
        return true;
    }

    public function ping(): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public static function errno(PDOException $e): int
    {
        // SQLSTATE 23000 = violação de integridade; mysqli usava 1062 para chave duplicada.
        if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
            return 1062;
        }
        return (int) ($e->errorInfo[1] ?? 0);
    }

    public static function wrap(PDOException $e): mysqli_sql_exception
    {
        return new mysqli_sql_exception($e->getMessage(), self::errno($e), $e);
    }
}
