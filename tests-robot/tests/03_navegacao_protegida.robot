*** Settings ***
Documentation       Controle de acesso a páginas e APIs restritas.
Resource            ../resources/keywords.robot


*** Test Cases ***
Página restrita exige autenticação
    [Documentation]    Cenário: acessar "Minhas Despesas" sem login e depois autenticado.
    ...
    ...    Resultado esperado: anônimo é redirecionado ao login e a API responde 401; autenticado acessa a página.
    [Tags]    T04    smoke    leitura
    Go To    ${DESPESAS_URL}
    Url Deve Conter    /frontend/login.php
    Get Element States    id=form-login    contains    visible
    ${resposta}=    Http    ${API_DESPESAS_READ_URL}
    Should Be Equal As Integers    ${resposta}[status]    401
    ...    msg=API de despesas deveria negar acesso anônimo (recebido ${resposta}[status])
    Exigir Credenciais De Teste
    Fazer Login Com Usuario De Teste
    Go To    ${DESPESAS_URL}
    Url Deve Conter    /public/pages/user/despesas.php
    Get Text    .page-header h1    contains    Minhas Despesas
