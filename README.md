# Conecta Egresso (SEJUS/ES)

Plataforma digital para atendimento remoto, acolhimento psicossocial e reintegração social para a população egressa do sistema prisional e seus familiares no Estado do Espírito Santo. Desenvolvido em conformidade com as diretrizes do **Edital de Contratação Pública de Solução Inovadora (CPSI) Nº 010/2026** (SEJUS/SEGER).

A plataforma visa superar a barreira geográfica do atendimento presencial (anteriormente restrito a 4 municípios) ao descentralizar os serviços do Escritório Social de forma remota e continuada para todos os **78 municípios capixabas**.

---

## 🚀 Funcionalidades Principais

- **Dashboard de Monitoramento & KPIs (Seção 3.2):** Acompanhamento de metas estaduais, egressos cadastrados, contagem de atendimentos psicossociais, taxa de encaminhamento para vagas de trabalho e índice de redução de reincidência criminal.
- **Atendimento Remoto por Vídeo (Item 3.1 `f`):** Sala de videoconferência integrada com criptografia ponta a ponta, fila de espera inteligente por prioridade e integração direta de notas de atendimento.
- **Oportunidades & Qualificação Profissional (Item 3.1 `h`):** Painel interativo de vagas de emprego inclusivas e cursos gratuitos (SENAI, IFES e cooperativas capixabas) com filtros e encaminhamento imediato.
- **Carteira Digital do Egresso & Documentação (Item 3.1 `e`, `i`):** Emissão visual do documento oficial com QR Code de verificação criptográfica estadual e integração com os sistemas Acesso Cidadão e Gov.br.
- **Mapeamento Territorial dos 78 Municípios (Item 3.1 `n`):** Painel dinâmico da rede de apoio socioassistencial (CRAS, CREAS, SINE e CAPS) integrada por geolocalização.
- **Prontuário Único & Registros Imutáveis (Item 3.1 `d`, `j`):** Linha do tempo social automatizada com carimbo de data, hora e responsável para fins de auditoria e segurança.

---

## 🛠️ Tecnologias Utilizadas (Tech Stack)

- **Frontend:** HTML5 Semântico, CSS3 Moderno (Variações de Design Tokens, Flexbox, CSS Grid, Glassmorphism).
- **Lógica e Dinâmica:** Vanilla JavaScript (ES6+) modular para roteamento de Single Page Application (SPA).
- **Gráficos:** Renderização nativa via HTML5 Canvas (sem dependência externa de pacotes pesados no carregamento inicial).
- **Tipografia:** Google Fonts (`Inter` e `Outfit`).
- **Acessibilidade:** Suporte integrado a Alto Contraste, Controle de Escala de Fonte e Linguagem Simplificada (baixo letramento digital).

---

## ⚙️ Pré-requisitos

Para rodar e testar o protótipo, você só precisa de:
- Um navegador de internet moderno (Google Chrome, Microsoft Edge, Mozilla Firefox ou Safari).
- Python (opcional, apenas se quiser executar como um servidor HTTP local).

---

## 💻 Como Rodar o Projeto Localmente

### Método 1: Abertura Direta (Sem Instalação)
1. Clone o repositório ou baixe a pasta do projeto:
   ```bash
   git clone https://github.com/agticonsult/Sejus.git
   cd Sejus
   ```
2. Dê um **duplo clique** no arquivo **`index.html`**.
3. O projeto abrirá instantaneamente em seu navegador padrão.

---

### Método 2: Servidor Local HTTP (Recomendado para Apresentações)
Para executar a aplicação rodando sob protocolo HTTP (evitando problemas de CORS em testes avançados):

#### Utilizando Python:
No diretório raiz do projeto, execute:
```bash
python -m http.server 8080
```
Em seguida, abra o navegador e acesse:
👉 **[http://localhost:8080](http://localhost:8080)**

#### Utilizando Node.js (se instalado):
Instale e execute um servidor simples:
```bash
npx serve ./
```

---

## 🗺️ Estrutura de Arquivos do Projeto

```
Sejus/
├── index.html        # Estrutura principal da Single Page Application (SPA)
├── styles.css        # Estilos globais, temas de acessibilidade e design system
├── app.js            # Lógica de controle de rotas, gráficos e simulações
├── README.md         # Documentação de referência do repositório
└── DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md  # Relatório executivo para a gestão e líderes
```

---

## 🔒 Segurança da Informação & LGPD

* **Níveis de Acesso Diferenciados (Item 3.1 `l`):** A plataforma divide as visões em **Egresso** (visualização simples e solicitações), **Técnico** (operacional e prontuários) e **Gestor** (estatísticas globais descaracterizadas).
* **Trilhas de Auditoria (Item 3.1 `d`):** Todas as alterações nos prontuários possuem identificação inequívoca do técnico executor e carimbo temporal automático do servidor.
* **Segurança de Dados (Item 3.1 `c`):** Interface preparada para comunicação HTTPS e criptografia AES-256 no tráfego de prontuários.
