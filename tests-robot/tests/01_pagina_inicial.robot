*** Settings ***
Documentation       Página inicial pública.
Resource            ../resources/keywords.robot


*** Test Cases ***
Página inicial carrega elementos essenciais
    [Documentation]    Cenário: visitante anônimo abre a página inicial.
    ...
    ...    Resultado esperado: título, chamada principal e botão "Entrar" visíveis; o botão leva ao login.
    [Tags]    T01    smoke    leitura
    Go To    ${HOME_URL}
    Get Title    ==    InvestAi - Inteligência Financeira
    Get Text    h1    contains    Gerencie Suas Finanças
    Get Element States    .navbar-brand    contains    visible
    ${entrar}=    Set Variable    a.btn-primary >> text=Entrar
    Get Element States    ${entrar}    contains    visible
    Get Attribute    ${entrar}    href    ==    login.php
    Click    ${entrar}
    Url Deve Conter    /frontend/login.php
    Get Element States    id=form-login    contains    visible
