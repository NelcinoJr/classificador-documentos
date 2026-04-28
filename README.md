# Classificador de Documentos IA (PoC - ContaÁgil)

Projeto de Prova de Conceito (PoC) para classificação automática de documentos (PDFs) utilizando Inteligência Artificial (Google Gemini).

Este sistema extrai o texto de arquivos PDF (simulando a etapa de OCR do AWS Textract) e os envia para a API do Gemini. A IA devolve um JSON estruturado identificando se é um Tributo, o subtipo (DAS, INSS, etc.), a competência, valor, vencimento, CNPJ e o custo estimado da requisição (tokens).

## 🚀 Tecnologias Utilizadas
- **PHP 8.2** (Backend)
- **HTML/CSS** (Interface Simples)
- **Composer** (Gerenciador de Dependências)
- **Smalot PDFParser** (Extração de texto local de PDFs)
- **API Google Gemini** (Modelos 1.5 a 3.1 para estruturação de dados)
- **Docker & Docker Compose** (Para facilitar a execução local)

## 🛠️ Como Instalar e Rodar com Docker (Recomendado)

A maneira mais fácil de testar a aplicação é utilizando o Docker, pois ele já prepara todo o ambiente PHP e instala as dependências do Composer automaticamente.

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/NelcinoJr/classificador-documentos.git
   cd classificador-documentos
   ```

2. **Inicie os containers:**
   Abra o terminal na raiz do projeto e execute o comando abaixo. Ele fará o build da imagem do PHP, instalará a pasta `vendor` com o Composer e subirá o servidor.
   ```bash
   docker-compose up -d --build
   ```

3. **Acesse a aplicação:**
   Abra o seu navegador e acesse:
   [http://localhost:8080](http://localhost:8080)

*Nota: Para parar o container posteriormente, basta rodar `docker-compose down`.*

## 💻 Como Instalar Manualmente (Sem Docker)

Caso prefira rodar diretamente no seu ambiente (ex: WSL, XAMPP, Laragon), certifique-se de ter o **PHP 8.x** e o **Composer** instalados.

1. **Clone o repositório:**
   ```bash
   git clone https://github.com/NelcinoJr/classificador-documentos.git
   cd classificador-documentos
   ```

2. **Instale as dependências do PHP:**
   ```bash
   composer install
   ```

3. **Inicie o servidor embutido do PHP:**
   ```bash
   php -S localhost:8000
   ```

4. **Acesse a aplicação:**
   Abra o seu navegador e acesse [http://localhost:8000](http://localhost:8000).

## 🔑 Como Utilizar o Sistema

1. Ao abrir a página, clique em **"Escolher arquivo"** e selecione um PDF (ex: Guia DAS, INSS, Nota Fiscal).
2. Escolha o **Modelo do Gemini** no menu suspenso. (Recomendamos o "1.5 Flash" para velocidade e baixo custo).
3. Insira sua **Chave da API do Google Gemini** (AIzaSy...). *Se você não tem uma, acesse [Google AI Studio](https://aistudio.google.com/) para gerar uma gratuitamente.*
4. Clique em **"Enviar e Analisar"**.
5. Aguarde o processamento. O sistema exibirá uma caixa com os dados extraídos (Tipo, Vencimento, Valor, etc) e logo abaixo a estimativa de custos baseada no uso de tokens e na tabela de preços atual da Google.

## 📄 Estrutura Principal do Código
- `index.php`: Interface do usuário, formulário e exibição de resultados.
- `upload.php`: Recebe o PDF, extrai o texto com `Smalot\PdfParser`, monta o Prompt e se comunica via cURL com a API do Gemini.
- `Dockerfile` / `docker-compose.yml`: Arquivos de orquestração de ambiente.
- `plans/classificador_documentos.md`: Documento de escopo, visão geral e orçamentos do projeto (Sprint 1 e Sprint 2).