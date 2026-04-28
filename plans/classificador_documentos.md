# Plano do Mini-Projeto: Classificador de Documentos com Gemini

## Objetivo
Criar um mini-projeto em PHP que permita o upload de um arquivo PDF, extraia seu texto (simulando ou utilizando uma ferramenta local para não depender da AWS nesta PoC), envie este texto para a API do Google Gemini para identificar o tipo de documento (Tributo, Outros), e exiba o resultado da classificação junto com uma estimativa de custo da requisição (tokens de input e output). O projeto será empacotado usando Docker para facilitar a execução local.

## Arquitetura e Fluxo

1.  **Interface Web (Frontend)**
    *   Um formulário simples em HTML/CSS (usando arquivos já existentes como `css/style.css` e `index.php`) para fazer upload de um arquivo PDF.
    *   Na interface, o usuário poderá selecionar qual modelo do Gemini deseja utilizar para o teste: Gemini 1.5 Flash, Gemini 1.5 Pro, Gemini 2.0 Pro, Gemini 3.0 Ultra ou Gemini 3.1.
    *   Exibição do resultado: Tipo de documento (Tributo/Outros), subtipo (ex: DAS, INSS), valor (se aplicável), vencimento (se aplicável) e o custo estimado da operação no Gemini.

2.  **Backend (PHP)**
    *   `upload.php`: Recebe o arquivo PDF e o modelo do Gemini selecionado.
    *   **Extração de Texto**: Utilizaremos a biblioteca `smalot/pdfparser` (via Composer) para extrair o texto do PDF de forma local e simples, simulando o comportamento que o AWS Textract teria no ambiente de produção.
    *   **Integração com Gemini API**: Um script PHP fará uma requisição HTTP para a API do Google Gemini utilizando o modelo escolhido.
        *   **Prompt**: Enviaremos o texto extraído instruindo a IA a responder em formato JSON, classificando o documento conforme os critérios estabelecidos (Tributo vs Outros, e detalhes do tributo).
    *   **Cálculo de Custo**: Com base no tamanho do texto enviado (estimativa de tokens), no modelo selecionado e no JSON recebido, o sistema calculará o custo aproximado daquela requisição.

3.  **Infraestrutura (Docker)**
    *   Criar um `Dockerfile` baseado em uma imagem PHP com Apache (ex: `php:8.2-apache`).
    *   Criar um `docker-compose.yml` para levantar o ambiente com um comando simples (`docker-compose up -d`).

## Passos para Implementação (Modo Code)

1.  **Configuração do Ambiente (Docker)**
    *   Criar `Dockerfile` (PHP + Apache + extensões necessárias).
    *   Criar `docker-compose.yml`.
2.  **Configuração de Dependências**
    *   Criar ou atualizar `composer.json` para incluir `smalot/pdfparser` e talvez o Guzzle para requisições HTTP (ou usar cURL puro).
3.  **Backend (Lógica)**
    *   Atualizar `upload.php` para lidar com o upload e salvar na pasta `uploads/`.
    *   Implementar a extração de texto do PDF.
    *   Implementar a chamada à API do Gemini usando cURL/Guzzle, aceitando a seleção do modelo (1.5 Flash, 1.5 Pro, 2.0 Pro, 3.0 Ultra, 3.1).
    *   Implementar o prompt estruturado para garantir resposta em JSON.
    *   Implementar a lógica de cálculo de custo (tokens in/out) baseado no modelo escolhido.
4.  **Frontend (Interface)**
    *   Atualizar `index.php` para ter o formulário de upload e um `<select>` para escolher o modelo do Gemini.
    *   Exibir os resultados retornados pelo backend de forma amigável na tela.

## Próximo Passo
Mudar para o modo **Code** para iniciar a implementação da infraestrutura Docker, instalação das bibliotecas e o desenvolvimento do backend/frontend em PHP.