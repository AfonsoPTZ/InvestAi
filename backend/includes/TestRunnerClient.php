<?php
// backend/includes/TestRunnerClient.php — Cliente do executor interno do Robot Framework
// O executor (tests-robot/runner/server.py) só é alcançável pela rede interna e exige token.
// Nenhum dado do usuário vira comando: aqui só existem rotas fixas.

require_once __DIR__ . '/../config/ConfigHelper.php';

class TestRunnerClient
{
    const RUN_ID_PATTERN = '/^\d{8}-\d{6}$/';
    const FILE_PATTERN   = '/^(report\.html|log\.html|screenshots\/[A-Za-z0-9_-]+\.png)$/';

    public static function isConfigured(): bool
    {
        ConfigHelper::load();
        return ConfigHelper::get('TEST_RUNNER_URL', '') !== '' && ConfigHelper::get('TEST_RUNNER_TOKEN', '') !== '';
    }

    /**
     * Faz uma requisição ao executor.
     * @return array{code:int, body:string, type:string}|null  null se o executor estiver inacessível
     */
    public static function request(string $method, string $path, int $timeout = 10): ?array
    {
        $baseUrl = rtrim(ConfigHelper::get('TEST_RUNNER_URL', ''), '/');
        $token   = ConfigHelper::get('TEST_RUNNER_TOKEN', '');

        $ch = curl_init($baseUrl . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_HTTPHEADER     => ['X-Runner-Token: ' . $token, 'Content-Length: 0'],
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($body === false || $code === 0) {
            error_log('[TestRunnerClient] Executor inacessível: ' . $err);
            return null;
        }
        return ['code' => $code, 'body' => $body, 'type' => $type];
    }

    /** Responde JSON padrão do projeto para executor ausente/inacessível. */
    public static function respondUnavailable(): void
    {
        http_response_code(503);
        echo json_encode([
            'status'  => 'error',
            'message' => self::isConfigured()
                ? 'O executor de testes não respondeu. Verifique se o serviço robot-runner está em execução.'
                : 'Executor de testes não configurado neste ambiente.',
        ]);
        exit;
    }
}
?>
