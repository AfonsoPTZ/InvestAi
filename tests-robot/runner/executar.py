"""Executa a suíte do Robot Framework com argumentos fixos e resume o output.xml.

Uso pelo terminal (dentro de tests-robot/):
    python runner/executar.py

Cada execução fica em results/<AAAAMMDD-HHMMSS>/ com output.xml, log.html,
report.html, screenshots/ e summary.json (consumido pelo dashboard /test).
"""
import json
import os
import re
import shutil
import signal
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROBOT_ROOT = Path(__file__).resolve().parent.parent
TESTS_DIR = ROBOT_ROOT / "tests"
RESULTS_DIR = Path(os.environ.get("ROBOT_RESULTS_DIR", ROBOT_ROOT / "results")).resolve()
MAX_SECONDS = int(os.environ.get("RUNNER_MAX_SECONDS", "300"))
KEEP_RUNS = int(os.environ.get("RUNNER_KEEP_RUNS", "20"))

RUN_ID_RE = re.compile(r"^\d{8}-\d{6}$")
_IMG_RE = re.compile(r'src="([^"]+\.png)"')
_METRIC_RE = re.compile(r"^\[METRICA\] ([^|]+)\|([\d.]+)\|([\d.]*)$")
_EXPECTED_RE = re.compile(r"Resultado esperado:\s*(.+)", re.IGNORECASE)
STATUS_PT = {"PASS": "aprovado", "FAIL": "falhou", "SKIP": "nao_executado", "NOT RUN": "nao_executado"}


def new_run_id():
    return datetime.now(timezone.utc).strftime("%Y%m%d-%H%M%S")


def run_dir(run_id):
    if not RUN_ID_RE.match(run_id or ""):
        raise ValueError("run id inválido")
    return RESULTS_DIR / run_id


def robot_command(output_dir):
    """Lista fixa de argumentos: nada aqui vem de entrada do usuário."""
    return [
        sys.executable, "-m", "robot",
        "--outputdir", str(output_dir),
        "--output", "output.xml", "--log", "log.html", "--report", "report.html",
        "--loglevel", "INFO", "--consolecolors", "off", "--consolemarkers", "off",
        str(TESTS_DIR),
    ]


def execute(run_id):
    """Roda o Robot com timeout e grava summary.json. Retorna o resumo."""
    out = run_dir(run_id)
    out.mkdir(parents=True, exist_ok=True)
    started = datetime.now(timezone.utc)
    error = None
    with open(out / "console.txt", "w", encoding="utf-8") as console:
        # Nova sessão de processo para conseguir matar Node/Chromium filhos no timeout.
        proc = subprocess.Popen(robot_command(out), cwd=ROBOT_ROOT, stdout=console,
                                stderr=subprocess.STDOUT, start_new_session=True)
        try:
            proc.wait(timeout=MAX_SECONDS)
        except subprocess.TimeoutExpired:
            os.killpg(proc.pid, signal.SIGKILL)
            proc.wait()
            error = f"Execução interrompida após {MAX_SECONDS}s (timeout)."
    if proc.returncode is not None and proc.returncode >= 250 and not error:
        error = f"Robot Framework terminou com erro de execução (código {proc.returncode})."
    summary = summarize(run_id, started=started, error=error)
    prune_old_runs()
    return summary


def _elapsed(item):
    return round(item.elapsed_time.total_seconds(), 3)


def _iter_keywords(body):
    for item in body:
        if getattr(item, "type", "") in ("KEYWORD", "SETUP", "TEARDOWN"):
            yield item


def _deepest_failure(kw):
    for child in _iter_keywords(getattr(kw, "body", [])):
        if child.status == "FAIL":
            return _deepest_failure(child)
    return kw


def _find_screenshot(item, out):
    for msg in getattr(item, "body", []):
        if getattr(msg, "type", "") == "MESSAGE" and msg.html:
            for src in _IMG_RE.findall(msg.message):
                path = (out / src).resolve()
                if path.is_file() and out in path.parents:
                    return path.relative_to(out).as_posix()
        found = _find_screenshot(msg, out) if hasattr(msg, "body") else None
        if found:
            return found
    return None


def _find_metrics(item):
    """Coleta as linhas '[METRICA] nome|valor|limite' registradas por 'Registrar Metrica'."""
    found = []
    for child in getattr(item, "body", []):
        if getattr(child, "type", "") == "MESSAGE":
            m = _METRIC_RE.match(child.message or "")
            if m:
                limite = float(m.group(3)) if m.group(3) else None
                valor = float(m.group(2))
                found.append({"nome": m.group(1), "valor_ms": valor, "limite_ms": limite,
                              "ok": limite is None or valor <= limite})
        elif hasattr(child, "body"):
            found.extend(_find_metrics(child))
    return found


def _test_summary(test, out):
    steps = []
    for kw in [test.setup, *test.body, test.teardown]:
        if not kw or getattr(kw, "type", "") not in ("KEYWORD", "SETUP", "TEARDOWN"):
            continue
        step = {"nome": " ".join(filter(None, [kw.name, *map(str, kw.args)]))[:200],
                "status": STATUS_PT.get(kw.status, kw.status.lower()),
                "duracao": _elapsed(kw), "tipo": kw.type.lower()}
        if kw.status == "FAIL":
            failing = _deepest_failure(kw)
            if failing is not kw:
                step["falhou_em"] = failing.name
        steps.append(step)
    expected = _EXPECTED_RE.search(test.doc or "")
    if test.status == "PASS":
        obtained = "Resultado esperado confirmado."
    else:
        obtained = test.message or "Sem mensagem registrada."
    tags = list(test.tags)
    return {
        "id": next((t for t in tags if re.fullmatch(r"T\d+", t)), test.id),
        "nome": test.name,
        "suite": test.parent.name,
        "descricao": re.sub(r"^Cenário:\s*", "", (test.doc or "").split("Resultado esperado:")[0].strip()),
        "esperado": expected.group(1).strip() if expected else None,
        "obtido": obtained,
        "status": STATUS_PT.get(test.status, test.status.lower()),
        "duracao": _elapsed(test),
        "mensagem": test.message or None,
        "tags": tags,
        "etapas": steps,
        "metricas": [m for kw in [test.setup, *test.body, test.teardown] if kw for m in _find_metrics(kw)],
        "captura": next(filter(None, (_find_screenshot(kw, out) for kw in [test.setup, *test.body, test.teardown] if kw)), None),
    }


def summarize(run_id, started=None, error=None):
    """Gera summary.json a partir do output.xml (ou registra o erro da execução)."""
    out = run_dir(run_id)
    output_xml = out / "output.xml"
    summary = {"id": run_id, "inicio": None, "fim": None, "duracao": 0, "status": "erro",
               "erro": error, "totais": {"total": 0, "aprovados": 0, "falhas": 0, "nao_executados": 0},
               "testes": [], "relatorio": False, "log": False}
    if started:
        summary["inicio"] = started.isoformat()
    if output_xml.is_file():
        from robot.api import ExecutionResult  # import tardio: só quando há resultado
        try:
            result = ExecutionResult(str(output_xml))
        except Exception as exc:  # output.xml truncado por timeout, por exemplo
            summary["erro"] = error or f"output.xml ilegível: {exc}"
        else:
            suite = result.suite
            stats = suite.statistics
            summary.update({
                "inicio": suite.start_time.astimezone().isoformat() if suite.start_time else summary["inicio"],
                "fim": suite.end_time.astimezone().isoformat() if suite.end_time else None,
                "duracao": _elapsed(suite),
                "status": "aprovado" if stats.failed == 0 and not error else "falhou",
                "totais": {"total": stats.total, "aprovados": stats.passed,
                           "falhas": stats.failed, "nao_executados": stats.skipped},
                "testes": [_test_summary(t, out) for t in suite.all_tests],
            })
    elif not error:
        summary["erro"] = "Nenhum output.xml foi gerado."
    summary["relatorio"] = (out / "report.html").is_file()
    summary["log"] = (out / "log.html").is_file()
    (out / "summary.json").write_text(json.dumps(summary, ensure_ascii=False, indent=2), encoding="utf-8")
    return summary


def load_summary(run_id):
    out = run_dir(run_id)
    path = out / "summary.json"
    if not path.is_file():
        if not (out / "output.xml").is_file():
            return None
        return summarize(run_id)  # execução feita pelo terminal com robot puro
    return json.loads(path.read_text(encoding="utf-8"))


def list_runs():
    if not RESULTS_DIR.is_dir():
        return []
    return sorted((p.name for p in RESULTS_DIR.iterdir() if p.is_dir() and RUN_ID_RE.match(p.name)), reverse=True)


def prune_old_runs():
    for old in list_runs()[KEEP_RUNS:]:
        shutil.rmtree(RESULTS_DIR / old, ignore_errors=True)


if __name__ == "__main__":
    rid = new_run_id()
    s = execute(rid)
    t = s["totais"]
    print(f"\nExecução {rid}: {s['status']} — {t['aprovados']} aprovados, {t['falhas']} falhas, "
          f"{t['nao_executados']} não executados de {t['total']}")
    if s.get("erro"):
        print(f"Erro: {s['erro']}")
    print(f"Relatório: {run_dir(rid) / 'report.html'}")
    sys.exit(0 if s["status"] == "aprovado" else 1)
