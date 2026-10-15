"""Variáveis do Robot Framework lidas do ambiente (com fallback para tests-robot/.env).

Todas as URLs, credenciais e flags ficam fora do código: defina-as no .env
(veja .env.example) ou como variáveis de ambiente do processo.
"""
import os
from pathlib import Path

ROBOT_ROOT = Path(__file__).resolve().parent.parent


def _load_dotenv(path):
    """Lê KEY=VALUE de um .env sem sobrescrever o que já veio do ambiente."""
    if not path.is_file():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        key, value = key.strip(), value.strip().strip('"').strip("'")
        os.environ.setdefault(key, value)


_load_dotenv(ROBOT_ROOT / ".env")


def _flag(name, default="false"):
    return os.environ.get(name, default).strip().lower() in ("1", "true", "yes", "on")


ROBOT_ENV = os.environ.get("ROBOT_ENV", "local").strip().lower()
IS_PRODUCTION = ROBOT_ENV in ("production", "producao", "prod")

BASE_URL = os.environ.get("ROBOT_BASE_URL", "http://localhost:8080").rstrip("/")
HOME_URL = f"{BASE_URL}/frontend/"
LOGIN_URL = f"{BASE_URL}/frontend/login.php"
DASHBOARD_URL = f"{BASE_URL}/frontend/public/pages/user/dashboard.php"
DESPESAS_URL = f"{BASE_URL}/frontend/public/pages/user/despesas.php"
API_DESPESAS_READ_URL = f"{BASE_URL}/backend/api/despesas/read.php"

USER_NAME = os.environ.get("ROBOT_USER_NAME", "Usuario Teste Robot")
USER_EMAIL = os.environ.get("ROBOT_USER_EMAIL", "")
USER_PASSWORD = os.environ.get("ROBOT_USER_PASSWORD", "")

# Criação do usuário de teste e testes de escrita nunca rodam em produção.
CREATE_TEST_USER = _flag("ROBOT_CREATE_TEST_USER") and not IS_PRODUCTION
ALLOW_WRITE = _flag("ROBOT_ALLOW_WRITE") and not IS_PRODUCTION

BROWSER = os.environ.get("ROBOT_BROWSER", "chromium")
HEADLESS = _flag("ROBOT_HEADLESS", "true")
TIMEOUT = os.environ.get("ROBOT_TIMEOUT", "10s")
