*** Settings ***
Documentation       Desempenho: tempos de carregamento e de resposta medidos no navegador (Performance API).
Resource            ../resources/keywords.robot


*** Variables ***
${LIMITE_CARGA_MS}      5000
${LIMITE_LOGIN_MS}      5000
${LIMITE_API_MS}        2000
${JS_NAVEGACAO}         () => { const n = performance.getEntriesByType('navigation')[0]; const f = performance.getEntriesByName('first-contentful-paint')[0]; return { ttfb: n.responseStart, dcl: n.domContentLoadedEventEnd, load: n.loadEventEnd || (performance.now()), fcp: f ? f.startTime : null }; }
${JS_API}               async (url) => { const t = performance.now(); const r = await fetch(url, {credentials: 'same-origin'}); await r.text(); return { ms: performance.now() - t, status: r.status }; }


*** Test Cases ***
Desempenho do login ao dashboard
    [Documentation]    Cenário: mede carregamento da tela de login, tempo entre clicar em Entrar e o dashboard abrir, e resposta da API de despesas.
    ...
    ...    Resultado esperado: login carrega em até 5 s, login até dashboard em até 5 s e API de despesas responde em até 2 s.
    [Tags]    T06    desempenho    leitura
    Exigir Credenciais De Teste
    # 1) Carregamento da página de login
    Go To    ${LOGIN_URL}
    Wait For Elements State    id=form-login    visible
    ${nav}=    Evaluate JavaScript    ${None}    ${JS_NAVEGACAO}
    Registrar Metrica    Login: tempo até o primeiro byte (TTFB)    ${nav}[ttfb]
    Registrar Metrica    Login: DOM carregado    ${nav}[dcl]
    IF    $nav['fcp'] is not None
        Registrar Metrica    Login: primeiro conteúdo visível (FCP)    ${nav}[fcp]
    END
    ${carga_login}    ${limite_carga}=    Registrar Metrica    Login: carregamento completo    ${nav}[load]    ${LIMITE_CARGA_MS}
    # 2) Do clique em Entrar até o dashboard
    Fill Text    id=login-email    ${USER_EMAIL}
    Fill Secret    id=login-senha    $USER_PASSWORD
    ${inicio}=    Evaluate    time.time()    modules=time
    Click    id=form-login >> button[type=submit]
    Wait For Condition    Url    contains    /public/pages/user/dashboard.php
    Wait For Load State    load
    ${fim}=    Evaluate    time.time()    modules=time
    ${ms_login}=    Evaluate    ($fim - $inicio) * 1000
    ${ms_login}    ${limite_login}=    Registrar Metrica    Login até dashboard (clique → página aberta)    ${ms_login}    ${LIMITE_LOGIN_MS}
    # 3) Carregamento do dashboard
    ${nav}=    Evaluate JavaScript    ${None}    ${JS_NAVEGACAO}
    Registrar Metrica    Dashboard: tempo até o primeiro byte (TTFB)    ${nav}[ttfb]
    ${carga_dash}    ${limite_dash}=    Registrar Metrica    Dashboard: carregamento completo    ${nav}[load]    ${LIMITE_CARGA_MS}
    # 4) API autenticada
    ${api}=    Evaluate JavaScript    ${None}    ${JS_API}    arg=${API_DESPESAS_READ_URL}
    ${ms_api}    ${limite_api}=    Registrar Metrica    API de despesas: tempo de resposta    ${api}[ms]    ${LIMITE_API_MS}
    Should Be Equal As Integers    ${api}[status]    200
    Should Be True    ${carga_login} <= ${limite_carga}    Carregamento do login acima do limite.
    Should Be True    ${ms_login} <= ${limite_login}    Login até dashboard acima do limite.
    Should Be True    ${carga_dash} <= ${limite_dash}    Carregamento do dashboard acima do limite.
    Should Be True    ${ms_api} <= ${limite_api}    API de despesas acima do limite.
