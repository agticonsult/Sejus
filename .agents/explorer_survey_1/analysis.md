# Relatório de Análise e Survey da Plataforma CONECTA EGRESSO (SEJUS/ES)

**Data:** 17 de Agosto de 2026  
**Fase:** Survey & Mapeamento Inicial de Ativos  
**Agente:** Explorer Survey 1  
**Diretório de Metadados:** `d:\Agile\projeto dia 18\.agents\explorer_survey_1`  
**Diretório Raiz do Projeto:** `d:\Agile\projeto dia 18`

---

## 1. Resumo Executivo

A investigação detalhada do repositório `d:\Agile\projeto dia 18` revelou que o projeto encontra-se atualmente em um estágio de **protótipo estático avançado de interface e regras visuais (Vanilla HTML5/CSS3/JS)** acompanhado pela documentação oficial do **Edital de Contratação Pública de Solução Inovadora (CPSI) Nº 010/2026 - SEJUS/SEGER (Governo do Estado do Espírito Santo)**.

O objetivo do sistema é **superar a barreira geográfica** que atualmente restringe a atuação presencial dos Escritórios Sociais a apenas 4 municípios capixabas (Vitória, Vila Velha, Serra e Cariacica), expandindo o atendimento psicossocial, qualificação profissional, emissão de documentos e reintegração social para todos os **78 municípios do Espírito Santo**, cobrindo uma população-alvo de mais de **108.000 egressos do sistema prisional e seus familiares**.

A base visual existente em `index.html` (1.211 linhas) e `styles.css` (1.381 linhas) já define com exatidão a identidade institucional (cores do ES, acessibilidade, temas claro e alto contraste, linguagem simplificada, layout responsivo e 8 visões completas de negócio). A próxima etapa requer a transformação desse protótipo estático em uma **arquitetura de microsserviços e backend corporativo completo**, composto por:
1. **Laravel 11 (PHP 8.3/8.4) + Inertia.js + Vue 3 + TailwindCSS**;
2. **Microsserviço de Sinalização WebRTC e Telemetria em Python (FastAPI / WebSockets / aiortc)**;
3. **Banco de Dados PostgreSQL 16 com criptografia LGPD (pgcrypto) e Redis 7**;
4. **Infraestrutura orquestrada via Docker Compose com Nginx e Coturn (STUN/TURN)**.

---

## 2. Inventário Completo dos Arquivos Existentes

| Caminho do Arquivo | Tamanho | Linhas | Descrição / Papel no Projeto |
|---|---|---|---|
| `ORIGINAL_REQUEST.md` | 3.139 B | 47 | Especificação autoritativa dos requisitos funcionais (R1 a R4) e critérios de aceitação. |
| `TR_EDITAL_DE CPSI Nº 010_2026 - SEJUS.pdf` | 237.754 B | 17 pág. | Documento oficial do Termo de Referência do Edital CPSI nº 010/2026 SEJUS/SEGER/FAPES. |
| `DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md` | 6.757 B | 108 | Memorando executivo detalhando os 6 pilares do protótipo e orientações de validação. |
| `README.md` | 4.699 B | 88 | Guia de execução local do protótipo, mapa de arquivos e especificações de segurança. |
| `index.html` | 61.021 B | 1.211 | Estrutura de interface SPA com 8 visões operacionais, modais e componentes de acessibilidade. |
| `app.js` | 11.842 B | 331 | Controladores de navegação SPA, chaveador de perfis (RBAC), gráficos Canvas e simulações. |
| `styles.css` | 25.428 B | 1.381 | Design system completo com tokens do Estado do ES, temas de alto contraste e glassmorphism. |

---

## 3. Mapeamento Funcional das 8 Visões Prototipadas

A análise do arquivo `index.html` e `app.js` identificou as seguintes 8 visões funcionais que servem de especificação visual para a implementação em Vue 3 / Inertia:

1. **Dashboard & KPIs (`view-dashboard`, linhas 165–388):**
   - Banner institucional com indicadores de expansão para os 78 municípios.
   - Cards de métricas: *Egressos Cadastrados* (14.850 / meta 108k), *Atendimentos Remotos* (32.410), *Encaminhamentos p/ Emprego* (4.120 / 76% contratados), *Redução de Reincidência* (-34.2%).
   - Gráficos nativos em Canvas: Atendimentos por município (Vitória, Serra, Vila Velha, Cariacica, Linhares, Cachoeiro, Colatina, São Mateus) e Donut de efetividade por tipo de ação (Emprego, Capacitação, Psicossocial, Documentação).
   - Feed de atividades recentes em tempo real com timestamp e status.

2. **Atendimento Remoto & Videochamadas Seguras (`view-atendimento`, linhas 389–527):**
   - Fila de acolhimento em espera em tempo real por município e nível de prioridade (acolhimento inicial, orientação documental, vaga).
   - Agenda de atendimentos do dia com horários definidos.
   - Sala de chamada por vídeo simulada com visor de câmera remota, PiP do técnico, indicador de conectividade móvel (4G/Wi-Fi estável), contador de duração e controles de chamada.
   - Painel integrado de notas psicossociais com seleção de encaminhamento e persistência direta no prontuário único.

3. **Oportunidades & Qualificação Profissional (`view-oportunidades`, linhas 531–684):**
   - Barra de filtros dinâmicos por Tipo (Vaga de Emprego, Curso, Estágio), Município (Grande Vitória e interior) e Escolaridade.
   - Cards de vagas inclusivas e cursos profissionalizantes (parcerias SEJUS, Porto de Tubarão, SENAI/Findes, Cooperativas Agropecuárias, IFES e ADERES/NossoCrédito).
   - Ação de encaminhamento/inscrição direta do egresso pelo técnico ou autoatendimento.

4. **Carteira Digital do Egresso & Documentos Básicos (`view-carteira`, linhas 689–814):**
   - Widget oficial da Carteira Digital do Egresso com Brasão do Estado do ES, foto, status de verificação, CPF mascarado, registro SEJUS (`ES-2026-948102`), data de emissão e QR Code de validação.
   - Exportação em PDF assinado digitalmente e link de validação pública.
   - Módulo de solicitação de 2ª via gratuita de documentos (CIN/RG via Polícia Científica, Certidão de Nascimento/Casamento com isenção via Defensoria, Regularização Eleitoral e Certidão de Execução Penal / Nada Consta).

5. **Mapeamento Territorial dos 78 Municípios (`view-geolocalizacao`, linhas 819–888):**
   - Painel interativo com os municípios do Espírito Santo divididos entre Escritórios Físicos (4 sedes) e Atendimento Remoto Conecta Egresso (74 municípios do interior).
   - Estatísticas de demanda territorial e integração com a rede socioassistencial local (CRAS, CREAS, SINE, CAPS e Casa do Cidadão).
   - Encaminhamento inteligente baseado em geolocalização com consentimento do usuário.

6. **Prontuário Único & Registros Imutáveis (`view-prontuario`, linhas 893–970):**
   - Busca por nome, CPF ou registro SEJUS.
   - Ficha do atendido (dados sociodemográficos, escolaridade, histórico prisional/CEJA, contato e técnico responsável).
   - Linha do tempo de evolução social imutável com carimbo de data, hora e responsável (conforme Art. 6º da LGPD).

7. **Relatórios Sintéticos & Análise Gerencial SEJUS (`view-relatorios`, linhas 975–1102):**
   - Filtros por período temporal, macrorregião SEJUS (Metropolitana, Norte, Sul, Serrana) e tipo de atendimento.
   - Tabela comparativa por município detalhando volume de egressos, encaminhamentos e taxas de reincidência.
   - Botão de exportação de relatório executivo em PDF com assinatura digital.

8. **Segurança da Informação, LGPD & Níveis de Acesso (`view-lgpd`, linhas 1107–1176):**
   - Matriz de perfis RBAC (Egresso/Familiar, Técnico/Atendente e Gestor SEJUS/SEGER).
   - Garantias de privacidade: criptografia de ponta a ponta (AES-256 e TLS), gestão de consentimento livre e esclarecido e trilhas de auditoria obrigatórias.

---

## 4. Design System & Acessibilidade Identificados

O arquivo `styles.css` contém os seguintes elementos institucionais consolidados:
- **Cores Oficiais do ES:**
  - Azul Institucional: `#003366`
  - Rosa/Coral Estadual: `#e63946`
  - Azul Claro: `#38bdf8` / `#0284c7`
  - Verde Sucesso/Efetividade: `#10b981`
  - Roxo Qualificação: `#8b5cf6`
  - Âmbar Atenção/Documentação: `#f59e0b`
- **Tipografia:** Google Fonts `Inter` (corpo e formulários) e `Outfit` (títulos e métricas de destaque).
- **Recursos de Acessibilidade:**
  - `body.high-contrast`: Tema de alto contraste para baixa visão (fundo `#000000`, textos `#ffffff`, realces `#00ffff`).
  - `var(--font-scale)`: Controle de escalonamento tipográfico (1.0x a 1.18x) sem quebra de layout.
  - `body.simplified-lang`: Linguagem simplificada voltada a cidadãos com menor letramento digital.

---

## 5. Análise do Termo de Referência CPSI Nº 010/2026 (SEJUS/SEGER)

A leitura integral das 17 páginas do documento `TR_EDITAL_DE CPSI Nº 010_2026 - SEJUS.pdf` forneceu as seguintes diretrizes críticas para o desenvolvimento:

1. **Desafio Principal (Item 1 e 2.1):** Superar o gargalo de atendimento físico limitado a 4 municípios (Vitória, Serra, Vila Velha e Cariacica) e alcançar os 108 mil egressos distribuídos pelos 78 municípios capixabas através de atendimento digital remoto individualizado e contínuo.
2. **Requisitos Funcionais Mandatórios (Item 3.1):**
   - *a) Adaptabilidade:* Flexibilidade para novos módulos de políticas públicas.
   - *b) Acessibilidade:* Interface compreensível para baixa literacia digital e compatibilidade com leitores de tela.
   - *c) Segurança:* Criptografia em trânsito e em repouso com isolamento de dados pessoais.
   - *d) Registros automáticos e imutáveis:* Auditoria inviolável com carimbo de data, hora e identificação inequívoca do técnico.
   - *e) Autenticação:* Integração com os padrões Acesso Cidadão (PRODEST/ES) e Gov.br (OAuth2/OpenID Connect).
   - *f) Chamadas de Vídeo Estáveis:* Suporte WebRTC com adaptação dinâmica para conexões móveis 3G/4G/5G do interior.
   - *h) Gestão de Vagas:* Atualização em tempo real de oportunidades de trabalho e cursos.
   - *k) Relatórios e Métricas:* Geração de relatórios consolidados para a SEGER e SEJUS.
   - *l) Níveis de Acesso:* Controle rigoroso de visibilidade (Gestor vê estatísticas globais descaracterizadas; Técnico vê prontuários operacionais; Egresso vê exclusivamente seu histórico).
   - *n) Geolocalização e Mapeamento:* Identificação do município do egresso para direcionamento à rede de apoio mais próxima (CRAS, CREAS, SINE).
3. **Metas de Desempenho e Validação da PoC (Item 3.2 e 6.4):**
   - Volume de atendimentos e egressos cadastrados.
   - Taxa de redução da reincidência criminal.
   - Estabilidade técnica em condições de baixa conectividade.

---

## 6. Diagnóstico do Ambiente de Desenvolvimento Local

A sondagem de ferramentas no host Windows revelou o seguinte cenário:
- **PHP:** PHP 8.2.18 (CLI) disponível em `C:\tools\php82\php.exe` com extensões `pdo_sqlite`, `sqlite3`, `curl`, `gd`, `intl`, `mbstring`, `openssl`, `zip`.
- **Composer:** Composer 2.9.5 disponível em `C:\tools\composer.phar`.
- **Node.js & NPM:** Node.js v24.14.1 e NPM 11.11.0 instalados e funcionais.
- **Python:** Python 3.14.7 e pip 26.2.1 disponíveis.
- **Docker:** O CLI do Docker não está configurado no PATH do Windows do host, o que significa que os arquivos de Docker (`docker-compose.yml`, Dockerfiles, Nginx, Coturn) devem ser minuciosamente configurados como artefatos de deploy/orquestração, enquanto a execução e testes locais automatizados podem ser executados e validados perfeitamente via PHP 8.2/8.3 CLI + SQLite/PDO e Python 3.14 / Uvicorn / Pytest.

---

## 7. Mapeamento de Obras: O que existe vs. O que deve ser construído

```
┌─────────────────────────────────────────────────────────────────────────────────┐
│                           ESTADO ATUAL DO REPOSITÓRIO                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│ [EXISTS] index.html (Protótipo Vanilla de 8 Telas e Modais)                     │
│ [EXISTS] styles.css (Tokens de Design, Cores ES, Acessibilidade)                 │
│ [EXISTS] app.js (Lógica de Navegação e Canvas)                                  │
│ [EXISTS] TR_EDITAL_DE CPSI Nº 010_2026 - SEJUS.pdf (Termo de Referência)        │
│ [EXISTS] DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md (Memorando de Apresentação)     │
│ [EXISTS] ORIGINAL_REQUEST.md (Requisitos R1 a R4)                               │
├─────────────────────────────────────────────────────────────────────────────────┤
│                        COMPONENTES A CONSTRUIR DO ZERO                          │
├─────────────────────────────────────────────────────────────────────────────────┤
│ 1. BACKEND LARAVEL 11 (R1):                                                     │
│    ├── composer.json & Estrutura Laravel 11 com Inertia.js                       │
│    ├── Modelos e Migrations (Users, Roles, Egressos, Prontuarios, Vagas, etc.)  │
│    ├── Controllers & Requests (AuthController, ProntuarioController, etc.)      │
│    ├── Serviço de Emissão de Carteira Digital em PDF + QR Code                  │
│    ├── Auditoria Imutável LGPD (Hash SHA-256 + Log de Acesso)                   │
│    └── Seeders com dados dos 78 Municípios e Redes Socioassistenciais           │
│                                                                                 │
│ 2. MICROSSERVIÇO PYTHON WEBRTC & SINALIZAÇÃO (R2):                              │
│    ├── FastAPI + WebSockets + aiortc para Sinalização SDP/ICE                   │
│    ├── Gestor de Salas e Fila de Espera em Memória / Redis                      │
│    ├── Monitoramento de Telemetria e Qualidade de Conexão                       │
│    └── Webhooks assinados com JWT para integração com Laravel                   │
│                                                                                 │
│ 3. FRONTEND INERTIA.JS + VUE 3 + TAILWINDCSS (R3):                              │
│    ├── Componentes Vue 3 reativos portando todo o design de index.html          │
│    ├── Páginas Inertia: Dashboard, Atendimento, Oportunidades, Carteira,        │
│    │   Geolocalizacao, Prontuario, Relatorios, Seguranca/LGPD                   │
│    ├── Integração WebRTC nativa no browser via WebSockets                       │
│    └── Modos de Alto Contraste, Escala de Fonte e Linguagem Fácil reativos      │
│                                                                                 │
│ 4. ORQUESTRAÇÃO DOCKER COMPOSE & INFRAESTRUTURA (R4):                           │
│    ├── docker-compose.yml unificado                                             │
│    ├── Dockerfile para Laravel / PHP 8.3-FPM                                    │
│    ├── Dockerfile para Microsserviço Python WebRTC                              │
│    ├── Configuração do Nginx (Reverse Proxy, SSL, WebSockets)                   │
│    ├── Configuração do PostgreSQL 16 (PostGIS / pgcrypto)                       │
│    ├── Configuração do Redis 7                                                  │
│    └── Configuração do Coturn (STUN/TURN para redes móveis 3G/4G/5G)            │
│                                                                                 │
│ 5. SUÍTE DE TESTES E VALIDAÇÃO:                                                 │
│    ├── Testes automatizados de Backend (PHPUnit / Feature Tests)                │
│    ├── Testes do Microsserviço de Vídeo (Pytest / Async WebSockets)             │
│    └── Validação de conformidade com os critérios de aceitação do Edital        │
└─────────────────────────────────────────────────────────────────────────────────┘
```

---

## 8. Estrutura Proposta para os 78 Municípios do Espírito Santo

O sistema contará com o cadastro completo e georreferenciado dos 78 municípios do Estado do Espírito Santo, classificados em 4 macrorregiões oficiais:
1. **Região Metropolitana / Grande Vitória (4 sedes físicas + interior metropolitano):** Vitória (Sede), Vila Velha (Sede), Serra (Sede), Cariacica (Sede), Viana, Guarapari, Fundão.
2. **Região Norte / Noroeste:** Linhares, São Mateus, Colatina, Aracruz, Barra de São Francisco, Nova Venécia, Conceição da Barra, Pinheiros, Jaguaré, Montanha, Boa Esperança, São Gabriel da Palha, Baixo Guandu, Marilândia, Pancas, São Domingos do Norte, Sooretama, Pedro Canário, Vila Pavão, Vila Valério, Alto Rio Novo, Governador Lindenberg, Ponto Belo, Mantenópolis, Água Doce do Norte, Mucurici.
3. **Região Sul / Caparaó:** Cachoeiro de Itapemirim, Itapemirim, Marataízes, Piúma, Anchieta, Iconha, Rio Novo do Sul, Presidente Kennedy, Castelo, Muqui, Mimoso do Sul, Atílio Vivácqua, Jerônimo Monteiro, Alegre, Guaçuí, Iúna, Ibitirama, Irupi, Dores do Rio Preto, Muniz Freire, Divino de São Lourenço, Apiacá, Bom Jesus do Norte, São José do Calçado.
4. **Região Serrana / Central:** Afonso Cláudio, Domingos Martins, Santa Maria de Jetibá, Santa Leopoldina, Venda Nova do Imigrante, Marechal Floriano, Conceição do Castelo, Brejetuba, Santa Teresa, Itarana, Itaguaçu, São Roque do Canaã, Laranja da Terra.

Cada município terá sua respectiva referência da **Rede Socioassistencial** (CRAS, CREAS, SINE e CAPS) mapeada para viabilizar o encaminhamento inteligente previsto no Item 3.1 `n` do Edital.

---

## 9. Próximos Passos e Recomendações

1. **Fase de Arquitetura & Especificação:** Estruturar os schemas de banco de dados, contratos de API REST/GraphQL, fluxos de WebSockets e matriz de autorização.
2. **Fase de Implementação do Backend Laravel 11:** Inicializar a base do Laravel com Inertia, autenticação RBAC, migrations e serviços de negócio.
3. **Fase de Implementação do Microsserviço WebRTC:** Desenvolver o servidor de sinalização em FastAPI com WebSockets, suporte a salas e webhooks de auditoria.
4. **Fase de Implementação do Frontend Vue 3:** Criar as páginas e componentes reativos utilizando a base visual já validada.
5. **Fase de Integração, Docker e Testes:** Finalizar os manifests Docker Compose, arquivos `.env`, scripts de setup e rodar a suíte de testes.
