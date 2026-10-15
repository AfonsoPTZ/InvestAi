// frontend/api/admin/testes.js — Dashboard de testes automatizados (Robot Framework)

const TestesAdmin = (() => {
    const API = `${BASE_PATH}/backend/api/testes`;
    const POLL_MS = 3000;

    const STATUS = {
        aprovado:      { label: 'Aprovado',       icon: 'bi-check-circle-fill', cls: 'ok' },
        falhou:        { label: 'Falhou',         icon: 'bi-x-circle-fill',     cls: 'fail' },
        nao_executado: { label: 'Não executado',  icon: 'bi-dash-circle-fill',  cls: 'skip' },
        erro:          { label: 'Erro na execução', icon: 'bi-exclamation-triangle-fill', cls: 'fail' },
    };

    let execucaoExibida = null;   // resumo atualmente mostrado nos cards/lista
    let selecaoManual = false;    // true quando o admin abriu uma execução do histórico
    let pollTimer = null;
    let emExecucao = false;
    let modal = null;

    const $ = (id) => document.getElementById(id);

    function esc(texto) {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    }

    function badge(status) {
        const s = STATUS[status] || { label: status, icon: 'bi-question-circle', cls: 'neutro' };
        return `<span class="badge-teste ${s.cls}"><i class="bi ${s.icon}" aria-hidden="true"></i>${esc(s.label)}</span>`;
    }

    function duracao(seg) {
        if (seg == null) return '—';
        if (seg < 60) return `${seg.toFixed(1).replace('.', ',')} s`;
        const m = Math.floor(seg / 60);
        return `${m} min ${Math.round(seg % 60)} s`;
    }

    function dataHora(iso) {
        if (!iso) return '—';
        return new Date(iso).toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'medium' });
    }

    function arquivoUrl(execId, arquivo = '') {
        return `${API}/arquivo.php/${encodeURIComponent(execId)}${arquivo ? '/' + arquivo : ''}`;
    }

    function aviso(texto, tipo = 'info') {
        const el = $('testes-aviso');
        if (!texto) { el.hidden = true; return; }
        el.className = `testes-aviso ${tipo}`;
        el.innerHTML = texto;
        el.hidden = false;
    }

    // ── Estado do cabeçalho / botões ───────────────────────────────────────
    function renderEstado(dados) {
        const estado = $('estado-execucao');
        const data = $('data-execucao');
        const btn = $('btn-executar');

        if (emExecucao) {
            estado.className = 'badge-teste running';
            estado.innerHTML = '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>Em execução';
            data.textContent = `Iniciada em ${dataHora(dados?.execucao_atual?.inicio)}`;
            btn.disabled = true;
            btn.querySelector('span').textContent = 'Executando…';
            return;
        }

        btn.querySelector('span').textContent = 'Executar testes';
        const ultima = dados?.ultima;
        if (!ultima) {
            estado.className = 'badge-teste neutro';
            estado.innerHTML = '<i class="bi bi-inbox" aria-hidden="true"></i>Sem execuções';
            data.textContent = '';
        } else {
            const st = STATUS[ultima.status] || STATUS.erro;
            estado.className = `badge-teste ${st.cls}`;
            estado.innerHTML = `<i class="bi ${st.icon}" aria-hidden="true"></i>${esc(st.label)}`;
            data.textContent = `Última execução: ${dataHora(ultima.fim || ultima.inicio)}`;
        }
    }

    // ── Cards + lista de cenários ──────────────────────────────────────────
    function renderExecucao(resumo) {
        execucaoExibida = resumo;
        const tbody = $('testes-tbody');
        const rel = $('link-relatorio');
        const log = $('link-log');

        if (!resumo) {
            ['card-total', 'card-aprovados', 'card-falhas', 'card-skip', 'card-duracao'].forEach(id => $(id).textContent = '—');
            $('execucao-exibida').textContent = '';
            tbody.innerHTML = `<tr><td colspan="5" class="admin-empty"><i class="bi bi-inbox"></i>Nenhuma execução registrada ainda. Clique em <strong>Executar testes</strong> para rodar a suíte.</td></tr>`;
            [rel, log].forEach(a => { a.classList.add('disabled'); a.setAttribute('aria-disabled', 'true'); a.removeAttribute('href'); });
            return;
        }

        const t = resumo.totais;
        $('card-total').textContent = t.total;
        $('card-aprovados').textContent = t.aprovados;
        $('card-falhas').textContent = t.falhas;
        $('card-skip').textContent = t.nao_executados;
        $('card-duracao').textContent = duracao(resumo.duracao);
        $('execucao-exibida').textContent = `Execução ${resumo.id} — ${dataHora(resumo.inicio)}`;

        [[rel, resumo.relatorio, 'report.html'], [log, resumo.log, 'log.html']].forEach(([a, existe, arq]) => {
            a.classList.toggle('disabled', !existe);
            a.setAttribute('aria-disabled', String(!existe));
            if (existe) a.href = arquivoUrl(resumo.id, arq); else a.removeAttribute('href');
        });

        if (!resumo.testes.length) {
            const motivo = resumo.erro ? esc(resumo.erro) : 'A execução não produziu resultados de testes.';
            tbody.innerHTML = `<tr><td colspan="5" class="admin-empty"><i class="bi bi-exclamation-octagon"></i>${motivo}</td></tr>`;
            return;
        }

        tbody.innerHTML = resumo.testes.map((teste, i) => `
            <tr>
                <td><div class="teste-nome">${esc(teste.nome)}</div><div class="teste-suite">${esc(teste.id)} · ${esc(teste.suite)}</div></td>
                <td data-label="Status">${badge(teste.status)}</td>
                <td data-label="Duração" class="text-nowrap">${duracao(teste.duracao)}</td>
                <td data-label="Mensagem"><span class="teste-msg" title="${esc(teste.mensagem || '')}">${teste.mensagem ? esc(teste.mensagem) : '<span class="text-muted-sm">—</span>'}</span></td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-teste="${i}" aria-label="Ver detalhes de ${esc(teste.nome)}"><i class="bi bi-eye me-1"></i>Detalhes</button></td>
            </tr>`).join('');

        if (resumo.erro) aviso(`<i class="bi bi-exclamation-triangle-fill me-2"></i>${esc(resumo.erro)}`, 'erro');
    }

    function renderHistorico(historico) {
        const tbody = $('historico-tbody');
        if (!historico || !historico.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="admin-empty"><i class="bi bi-clock-history"></i>Sem histórico de execuções.</td></tr>`;
            return;
        }
        tbody.innerHTML = historico.map(h => `
            <tr class="${execucaoExibida && execucaoExibida.id === h.id ? 'linha-ativa' : ''}">
                <td data-label="Data" class="text-nowrap">${dataHora(h.inicio)}</td>
                <td data-label="Status">${badge(h.status)}</td>
                <td data-label="Resultado" class="text-nowrap"><span class="txt-ok">${h.totais.aprovados} ok</span> · <span class="txt-fail">${h.totais.falhas} falha(s)</span> · <span class="txt-skip">${h.totais.nao_executados} não exec.</span></td>
                <td data-label="Duração" class="text-nowrap">${duracao(h.duracao)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-secondary" data-execucao="${esc(h.id)}" aria-label="Ver execução de ${dataHora(h.inicio)}"><i class="bi bi-box-arrow-in-up-right me-1"></i>Ver</button></td>
            </tr>`).join('');
    }

    // ── Modal de detalhes ──────────────────────────────────────────────────
    function abrirDetalhes(teste) {
        $('modal-teste-titulo').querySelector('span').textContent = teste.nome;
        const etapas = teste.etapas.length ? teste.etapas.map(e => `
            <li class="etapa ${STATUS[e.status]?.cls || ''}">
                <i class="bi ${STATUS[e.status]?.icon || 'bi-circle'}" aria-hidden="true"></i>
                <span class="visually-hidden">${STATUS[e.status]?.label || e.status}:</span>
                <code>${esc(e.nome)}</code>
                <span class="etapa-tempo">${duracao(e.duracao)}</span>
                ${e.falhou_em ? `<div class="etapa-falha">Falhou em: ${esc(e.falhou_em)}</div>` : ''}
            </li>`).join('') : '<li class="text-muted-sm">Etapas não registradas.</li>';

        const captura = teste.captura
            ? `<a href="${arquivoUrl(execucaoExibida.id, teste.captura)}" target="_blank" rel="noopener">
                   <img src="${arquivoUrl(execucaoExibida.id, teste.captura)}" alt="Captura de tela de ${esc(teste.nome)}" class="teste-captura" loading="lazy">
               </a>`
            : '<p class="text-muted-sm mb-0">Nenhuma captura de tela disponível.</p>';

        const ms = v => v >= 1000 ? `${(v / 1000).toFixed(2).replace('.', ',')} s` : `${Math.round(v)} ms`;
        const metricas = (teste.metricas || []).length
            ? `<ul class="lista-metricas">${teste.metricas.map(m => `
                   <li><span class="metrica-nome">${esc(m.nome)}:</span> <strong>${ms(m.valor_ms)}</strong>
                   ${m.limite_ms == null ? '' : `<span class="text-muted-sm">(limite ${ms(m.limite_ms)})</span> ${m.ok ? '<span class="txt-ok">✓ dentro do limite</span>' : '<span class="txt-fail">✗ acima do limite</span>'}`}</li>`).join('')}
               </ul>`
            : null;

        $('modal-teste-corpo').innerHTML = `
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">${badge(teste.status)}<span class="text-muted-sm">${duracao(teste.duracao)} · ${esc(teste.suite)}</span></div>
            <dl class="teste-detalhes">
                <dt>Cenário</dt><dd>${esc(teste.descricao) || '—'}</dd>
                <dt>Resultado esperado</dt><dd>${esc(teste.esperado) || '—'}</dd>
                <dt>Resultado obtido</dt><dd>${esc(teste.obtido)}</dd>
                ${teste.mensagem ? `<dt>Mensagem</dt><dd><pre>${esc(teste.mensagem)}</pre></dd>` : ''}
            </dl>
            <h6 class="detalhe-titulo">Etapas executadas</h6>
            <ol class="lista-etapas">${etapas}</ol>
            ${metricas
                ? `<h6 class="detalhe-titulo">Resultados de desempenho</h6>${metricas}`
                : `<h6 class="detalhe-titulo">Captura de tela</h6>${captura}`}`;
        modal.show();
    }

    // ── Comunicação com o backend ──────────────────────────────────────────
    async function getJson(url) {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        return res.json();
    }

    async function carregar() {
        $('btn-atualizar').disabled = true;
        try {
            const data = await getJson(`${API}/status.php`);
            if (data.status !== 'success') throw new Error(data.message);

            const exec = data.executor;
            if (!exec.configurado || !exec.disponivel) {
                emExecucao = false;
                $('btn-executar').disabled = true;
                aviso(exec.configurado
                    ? '<i class="bi bi-plug me-2"></i>O executor de testes não respondeu. Verifique se o serviço <code>robot-runner</code> está rodando (<code>docker compose up -d</code>).'
                    : '<i class="bi bi-info-circle me-2"></i>O executor de testes não está configurado neste ambiente (<code>TEST_RUNNER_URL</code>). Execute os testes pelo terminal — veja <code>tests-robot/README.md</code>.',
                    'alerta');
                renderEstado(null);
                renderExecucao(null);
                renderHistorico([]);
                return;
            }

            const dados = data.dados;
            emExecucao = dados.em_execucao;
            aviso(dados.ultimo_erro_executor ? `<i class="bi bi-exclamation-triangle-fill me-2"></i>${esc(dados.ultimo_erro_executor)}` : '', 'erro');
            $('btn-executar').disabled = emExecucao;
            renderEstado(dados);

            if (!selecaoManual || !execucaoExibida) renderExecucao(dados.ultima);
            renderHistorico(dados.historico);

            if (emExecucao) agendarPoll();
        } catch (e) {
            aviso('<i class="bi bi-wifi-off me-2"></i>Falha ao comunicar com o servidor. Tente atualizar.', 'erro');
        } finally {
            $('btn-atualizar').disabled = false;
        }
    }

    function agendarPoll() {
        clearTimeout(pollTimer);
        pollTimer = setTimeout(async () => {
            const estavaRodando = emExecucao;
            await carregar();
            if (estavaRodando && !emExecucao) {
                aviso('<i class="bi bi-check2-circle me-2"></i>Execução concluída. Resultados atualizados.', 'sucesso');
            }
        }, POLL_MS);
    }

    async function executar() {
        const btn = $('btn-executar');
        btn.disabled = true;
        try {
            const res = await fetch(`${API}/executar.php`, {
                method: 'POST',
                headers: { 'X-CSRF-Token': CSRF_TESTES },
            });
            const data = await res.json();
            if (data.status !== 'success') {
                aviso(`<i class="bi bi-exclamation-triangle-fill me-2"></i>${esc(data.message || 'Não foi possível iniciar a execução.')}`, 'erro');
                await carregar();
                return;
            }
            selecaoManual = false;
            aviso('<i class="bi bi-play-circle me-2"></i>Execução iniciada. Esta página será atualizada automaticamente ao final.', 'info');
            emExecucao = true;
            renderEstado({ execucao_atual: { inicio: new Date().toISOString() } });
            agendarPoll();
        } catch (e) {
            aviso('<i class="bi bi-wifi-off me-2"></i>Falha ao comunicar com o servidor.', 'erro');
            btn.disabled = false;
        }
    }

    async function verExecucao(id) {
        try {
            const resumo = await getJson(arquivoUrl(id));
            if (!resumo || !resumo.id) throw new Error('resumo inválido');
            aviso('');
            selecaoManual = true;
            renderExecucao(resumo);
            document.querySelectorAll('#historico-tbody tr').forEach(tr =>
                tr.classList.toggle('linha-ativa', tr.querySelector(`[data-execucao="${id}"]`) !== null));
        } catch (e) {
            aviso('<i class="bi bi-exclamation-triangle-fill me-2"></i>Não foi possível carregar essa execução.', 'erro');
        }
    }

    function init() {
        modal = new bootstrap.Modal($('modal-teste'));
        $('btn-executar').addEventListener('click', executar);
        $('btn-atualizar').addEventListener('click', () => { aviso(''); selecaoManual = false; carregar(); });
        $('testes-tbody').addEventListener('click', (ev) => {
            const btn = ev.target.closest('[data-teste]');
            if (btn && execucaoExibida) abrirDetalhes(execucaoExibida.testes[Number(btn.dataset.teste)]);
        });
        $('historico-tbody').addEventListener('click', (ev) => {
            const btn = ev.target.closest('[data-execucao]');
            if (btn) verExecucao(btn.dataset.execucao);
        });
        carregar();
    }

    return { init };
})();

TestesAdmin.init();
