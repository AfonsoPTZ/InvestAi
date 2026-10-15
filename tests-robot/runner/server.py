"""Executor HTTP interno do Robot Framework (apenas para a rede interna do docker-compose).

Não aceita comandos, caminhos ou argumentos do cliente: só dispara a suíte fixa
de executar.py, uma execução por vez, e serve arquivos de resultado validados.
Toda requisição precisa do cabeçalho X-Runner-Token igual a RUNNER_TOKEN.

Rotas:
    GET  /status                    estado atual + histórico resumido + última execução
    POST /run                       inicia uma execução (409 se já houver uma em andamento)
    GET  /runs/<id>/summary         resumo completo de uma execução
    GET  /runs/<id>/<arquivo>       report.html, log.html ou screenshots/<nome>.png
"""
import hmac
import json
import os
import re
import sys
import threading
import traceback
from datetime import datetime, timezone
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

import executar

TOKEN = os.environ.get("RUNNER_TOKEN", "")
PORT = int(os.environ.get("RUNNER_PORT", "8270"))
HISTORY_SIZE = 10

FILE_RE = re.compile(r"^/runs/(\d{8}-\d{6})/(report\.html|log\.html|screenshots/[A-Za-z0-9_-]+\.png)$")
SUMMARY_RE = re.compile(r"^/runs/(\d{8}-\d{6})/summary$")
CONTENT_TYPES = {".html": "text/html; charset=utf-8", ".png": "image/png"}

_lock = threading.Lock()
_current = {"id": None, "inicio": None}
_last_error = {"mensagem": None}


def _run_in_background(run_id):
    try:
        executar.execute(run_id)
    except Exception:
        traceback.print_exc()  # detalhes ficam só no log do container
        _last_error["mensagem"] = "Falha interna do executor ao rodar os testes."
    finally:
        _current.update(id=None, inicio=None)
        _lock.release()


def _history():
    items = []
    for rid in executar.list_runs()[:HISTORY_SIZE]:
        if rid == _current["id"]:
            continue
        try:
            summary = executar.load_summary(rid)
        except Exception:
            summary = None
        if summary:
            items.append({k: summary.get(k) for k in ("id", "inicio", "fim", "duracao", "status", "totais", "erro")})
    return items


class Handler(BaseHTTPRequestHandler):
    server_version = "RobotRunner"
    sys_version = ""

    def log_message(self, fmt, *args):
        sys.stderr.write("[runner] " + (fmt % args) + "\n")

    def _authorized(self):
        sent = self.headers.get("X-Runner-Token", "")
        return bool(TOKEN) and hmac.compare_digest(sent.encode(), TOKEN.encode())

    def _json(self, code, payload):
        body = json.dumps(payload, ensure_ascii=False).encode("utf-8")
        self.send_response(code)
        self.send_header("Content-Type", "application/json; charset=utf-8")
        self.send_header("Content-Length", str(len(body)))
        self.send_header("Cache-Control", "no-store")
        self.end_headers()
        self.wfile.write(body)

    def do_GET(self):
        if not self._authorized():
            return self._json(401, {"erro": "não autorizado"})
        path = self.path.split("?", 1)[0]

        if path == "/status":
            history = _history()
            latest = executar.load_summary(history[0]["id"]) if history else None
            return self._json(200, {
                "em_execucao": _current["id"] is not None,
                "execucao_atual": dict(_current) if _current["id"] else None,
                "ultimo_erro_executor": _last_error["mensagem"],
                "ultima": latest,
                "historico": history,
            })

        match = SUMMARY_RE.match(path)
        if match:
            summary = executar.load_summary(match.group(1))
            return self._json(200, summary) if summary else self._json(404, {"erro": "execução não encontrada"})

        match = FILE_RE.match(path)
        if match:
            base = executar.run_dir(match.group(1)).resolve()
            target = (base / match.group(2)).resolve()
            if base not in target.parents or not target.is_file():
                return self._json(404, {"erro": "arquivo não encontrado"})
            data = target.read_bytes()
            self.send_response(200)
            self.send_header("Content-Type", CONTENT_TYPES[target.suffix])
            self.send_header("Content-Length", str(len(data)))
            self.end_headers()
            self.wfile.write(data)
            return None

        return self._json(404, {"erro": "rota não encontrada"})

    def do_POST(self):
        if not self._authorized():
            return self._json(401, {"erro": "não autorizado"})
        if self.path != "/run":
            return self._json(404, {"erro": "rota não encontrada"})
        if not _lock.acquire(blocking=False):
            return self._json(409, {"erro": "Já existe uma execução em andamento.", "execucao_atual": dict(_current)})
        run_id = executar.new_run_id()
        _current.update(id=run_id, inicio=datetime.now(timezone.utc).isoformat())
        _last_error["mensagem"] = None
        threading.Thread(target=_run_in_background, args=(run_id,), daemon=True).start()
        return self._json(202, {"id": run_id, "inicio": _current["inicio"]})


def main():
    if len(TOKEN) < 16:
        sys.exit("RUNNER_TOKEN ausente ou curto demais (mínimo 16 caracteres).")
    executar.RESULTS_DIR.mkdir(parents=True, exist_ok=True)
    print(f"[runner] ouvindo na porta {PORT}", flush=True)
    ThreadingHTTPServer(("0.0.0.0", PORT), Handler).serve_forever()


if __name__ == "__main__":
    main()
