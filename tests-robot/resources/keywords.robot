*** Settings ***
Documentation       Keywords e configuração compartilhadas por todas as suítes.
Variables           config.py
Library             Browser    timeout=${TIMEOUT}    run_on_failure=None
Library             TestSupport.py


*** Keywords ***
Preparar Ambiente De Testes
    [Documentation]    Abre o navegador da execução e garante o usuário de teste (somente fora de produção).
    Log    Ambiente: ${ROBOT_ENV} | URL-base: ${BASE_URL} | escrita: ${ALLOW_WRITE}    console=True
    New Browser    ${BROWSER}    headless=${HEADLESS}
    IF    ${CREATE_TEST_USER} and $USER_EMAIL and $USER_PASSWORD
        Garantir Usuario De Teste    ${BASE_URL}    ${USER_NAME}    ${USER_EMAIL}    ${USER_PASSWORD}
    END

Abrir Nova Sessao
    [Documentation]    Cada teste usa um contexto novo: cookies e sessão PHP isolados.
    New Context    viewport={'width': 1366, 'height': 900}    locale=pt-BR
    New Page    about:blank

Encerrar Teste
    [Documentation]    Salva captura de tela ao fim de todo teste (aprovado ou falho); em falha, registra também a URL.
    Aguardar Animacoes
    IF    'desempenho' in $TEST_TAGS and '${TEST STATUS}' != 'FAIL'
        No Operation
    ELSE IF    '${TEST STATUS}' == 'FAIL'
        ${url}=    Get Url
        Log    Falha registrada na URL: ${url}    level=WARN
        Take Screenshot    filename=${OUTPUT DIR}${/}screenshots${/}falha-{index}    fullPage=True
    ELSE
        Take Screenshot    filename=${OUTPUT DIR}${/}screenshots${/}sucesso-{index}    fullPage=True
    END

Aguardar Animacoes
    [Documentation]    Conclui animações de entrada (fade-in) para a captura não sair semitransparente.
    Evaluate JavaScript    ${None}    () => document.getAnimations().forEach(a => { try { a.finish(); } catch (e) {} })
    Sleep    0.4s

Exigir Credenciais De Teste
    [Documentation]    Marca o teste como não executado quando as credenciais não foram configuradas.
    Skip If    not $USER_EMAIL or not $USER_PASSWORD
    ...    Credenciais de teste ausentes: defina ROBOT_USER_EMAIL e ROBOT_USER_PASSWORD no tests-robot/.env.

Exigir Permissao De Escrita
    [Documentation]    Testes que gravam dados só rodam com ROBOT_ALLOW_WRITE=true e fora de produção.
    Skip If    not ${ALLOW_WRITE}
    ...    Teste de escrita desabilitado (ROBOT_ALLOW_WRITE=false ou ROBOT_ENV=production).

Fazer Login
    [Arguments]    ${email}    ${senha}
    Go To    ${LOGIN_URL}
    Wait For Elements State    id=form-login    visible
    Fill Text    id=login-email    ${email}
    Fill Secret    id=login-senha    $senha
    Click    id=form-login >> button[type=submit]

Fazer Login Com Usuario De Teste
    Fazer Login    ${USER_EMAIL}    ${USER_PASSWORD}
    Wait For Condition    Url    contains    /public/pages/user/dashboard.php

Url Deve Conter
    [Arguments]    ${trecho}
    Wait For Condition    Url    contains    ${trecho}
