*** Settings ***
Documentation       Testes automatizados de interface do InvestAI.
Resource            ../resources/keywords.robot
Suite Setup         Preparar Ambiente De Testes
Test Setup          Abrir Nova Sessao
Test Teardown       Encerrar Teste
Test Timeout        90 seconds
