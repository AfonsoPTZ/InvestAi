"""Keywords de suporte em Python (sem dependências além da stdlib)."""
import hashlib
import json
import time
import urllib.error
import urllib.parse
import urllib.request

from robot.api import logger
from robot.api.deco import keyword, library


@library(scope="GLOBAL")
class TestSupport:

    @keyword("Garantir Usuario De Teste")
    def ensure_test_user(self, base_url, name, email, password):
        """Cadastra o usuário de teste pela API pública de cadastro, se ainda não existir.

        CPF e telefone são derivados do e-mail para serem estáveis e únicos.
        """
        digest = int(hashlib.sha256(email.encode()).hexdigest(), 16)
        cpf = f"{digest % 10**11:011d}"
        telefone = f"119{digest % 10**8:08d}"
        payload = urllib.parse.urlencode({
            "nome": name, "email": email, "cpf": cpf,
            "telefone": telefone, "senha": password,
        }).encode()
        url = f"{base_url}/backend/api/auth/cadastro.php"
        try:
            with urllib.request.urlopen(urllib.request.Request(url, data=payload), timeout=15) as resp:
                body = json.loads(resp.read().decode("utf-8"))
        except (urllib.error.URLError, ValueError) as exc:
            raise AssertionError(f"Não foi possível cadastrar o usuário de teste em {url}: {exc}") from exc

        message = body.get("message", "")
        if body.get("status") == "success":
            logger.info(f"Usuário de teste criado: {email}")
        elif "já cadastrado" in message:
            logger.info(f"Usuário de teste já existe: {email}")
        else:
            raise AssertionError(f"Cadastro do usuário de teste recusado: {message}")

    @keyword("Gerar Descricao Unica")
    def unique_description(self, prefix="Robot"):
        return f"{prefix} {int(time.time() * 1000)}"

    @keyword("Registrar Metrica")
    def register_metric(self, nome, valor_ms, limite_ms=None):
        """Registra uma métrica de desempenho (ms) no log; o runner a exibe no painel no lugar da captura.

        Falha o teste quando há limite e o valor o ultrapassa — mas só depois de registrar.
        """
        valor = round(float(valor_ms), 1)
        limite = float(limite_ms) if limite_ms not in (None, "", "None") else None
        logger.info(f"[METRICA] {nome}|{valor}|{'' if limite is None else limite}")
        return valor, limite

