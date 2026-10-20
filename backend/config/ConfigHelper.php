<?php
/**
 * ConfigHelper.php - Gerencia carregamento de variáveis de ambiente
 * 
 * Carrega .env uma única vez e fornece acesso centralizado a configurações.
 * Evita duplicação de parsing de .env em múltiplos arquivos.
 */

class ConfigHelper
{
    private static $loaded = false;

    /**
     * Carrega variáveis de .env para $_ENV
     * Safe to call multiple times (idempotent)
     */
    public static function load($envPath = null)
    {
        if (self::$loaded) {
            return;
        }

        if ($envPath === null) {
            $envPath = dirname(__DIR__, 2) . '/.env';
        }

        if (!file_exists($envPath)) {
            return;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parse KEY=VALUE
            if (strpos($line, '=') !== false) {
                [$key, $value] = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes if present
                if (strlen($value) > 1 && $value[0] === '"' && $value[-1] === '"') {
                    $value = substr($value, 1, -1);
                }
                
                // Variáveis do ambiente de execução têm prioridade sobre o .env.
                if (getenv($key) === false) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }

        self::$loaded = true;
    }

    /**
     * Obtém valor de ambiente com fallback padrão
     */
    public static function get($key, $default = null)
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        $value = getenv($key);
        return $value !== false ? $value : $default;
    }
}
?>
