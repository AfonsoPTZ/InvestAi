*** Settings ***
Documentation       Login com credenciais válidas e inválidas.
Resource            ../resources/keywords.robot


*** Variables ***
${EMAIL_INEXISTENTE}    robot.invalido@example.com
${SENHA_INVALIDA}       senha-errada-123


*** Test Cases ***
Login válido acessa o dashboard
    [Documentation]    Cenário: usuário de teste faz login com e-mail e senha corretos.
    ...
    ...    Resultado esperado: mensagem de sucesso, redirecionamento ao dashboard e nome do usuário na navbar.
    [Tags]    T02    smoke    leitura
    Exigir Credenciais De Teste
    Fazer Login    ${USER_EMAIL}    ${USER_PASSWORD}
    Get Text    id=auth-alert    contains    Login realizado
    Url Deve Conter    /public/pages/user/dashboard.php
    Get Text    .navbar-custom .user-badge    contains    ${USER_NAME}
    Get Element States    .navbar-custom a[href$="logout.php"]    contains    attached

Login inválido exibe erro e não autentica
    [Documentation]    Cenário: visitante tenta entrar com credenciais incorretas.
    ...
    ...    Resultado esperado: alerta "E-mail ou senha incorretos.", permanência no login e dashboard inacessível.
    [Tags]    T03    smoke    leitura
    Fazer Login    ${EMAIL_INEXISTENTE}    ${SENHA_INVALIDA}
    Wait For Elements State    id=auth-alert    visible
    Get Text    id=auth-alert    ==    E-mail ou senha incorretos.
    Get Classes    id=auth-alert    contains    error
    Get Url    contains    /frontend/login.php
    Go To    ${DASHBOARD_URL}
    Url Deve Conter    /frontend/login.php
