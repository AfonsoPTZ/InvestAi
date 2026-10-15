*** Settings ***
Documentation       Formulário principal: cadastro e exclusão de despesa (escreve dados do usuário de teste).
Resource            ../resources/keywords.robot


*** Variables ***
${CATEGORIA}        Alimentação
${VALOR}            12.34
${VALOR_EXIBIDO}    12,34


*** Test Cases ***
Cadastrar e excluir despesa
    [Documentation]    Cenário: usuário de teste registra uma despesa pelo formulário e depois a exclui.
    ...
    ...    Resultado esperado: despesa aparece na lista com valor e categoria corretos e some após a exclusão.
    [Tags]    T05    escrita
    Exigir Permissao De Escrita
    Exigir Credenciais De Teste
    Fazer Login Com Usuario De Teste
    Go To    ${DESPESAS_URL}
    ${descricao}=    Gerar Descricao Unica    Robot despesa
    ${item}=    Set Variable    .list-item:has(.desc:text-is("${descricao}"))

    # As opções exibem um emoji antes do nome; seleciona pelo value da opção que contém o texto.
    ${opcao}=    Set Variable    id=despesa-categoria >> option >> text=${CATEGORIA}
    Wait For Elements State    ${opcao}    attached
    ${categoria_id}=    Get Attribute    ${opcao}    value
    Fill Text    id=despesa-descricao    ${descricao}
    Select Options By    id=despesa-categoria    value    ${categoria_id}
    Fill Text    id=despesa-valor    ${VALOR}
    Click    id=btn-submit
    Get Text    id=despesa-alert    contains    Despesa criada

    Wait For Elements State    ${item}    visible
    Get Text    ${item} >> .item-value    contains    ${VALOR_EXIBIDO}
    Get Text    ${item} >> .categoria    contains    ${CATEGORIA}

    Click    ${item} >> .btn-delete
    Wait For Elements State    id=modal-delete    visible
    Click    id=btn-confirm-delete
    Get Text    id=despesa-alert    contains    excluída
    Wait For Elements State    ${item}    detached
