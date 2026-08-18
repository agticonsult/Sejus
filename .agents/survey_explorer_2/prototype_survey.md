# Levantamento Completo do Protótipo Frontend — CONECTA EGRESSO (SEJUS/ES)

**Data do Levantamento:** 17 de Agosto de 2026  
**Agente Responsável:** `survey_explorer_2` (Teamwork Explorer)  
**Projeto de Referência:** CPSI Nº 010/2026 – SEJUS / SEGER • Governo do Estado do Espírito Santo  
**Arquivos Analisados:**
- `ORIGINAL_REQUEST.md` (Especificação Técnica e Critérios de Aceite)
- `index.html` (Estrutura Completa da Single Page Application de 1.211 linhas)
- `styles.css` (Design System, Tokens, Glassmorphism, Temas e Responsividade de 1.381 linhas)
- `app.js` (Lógica de Navegação, Controle de Papéis RBAC, Gráficos Canvas e Simulações de 331 linhas)
- `DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md` e `README.md` (Contextualização Estratégica)

---

## 1. Visão Geral da Arquitetura do Protótipo

O protótipo existente implementa uma **Single Page Application (SPA) em HTML5, CSS3 e Vanilla JavaScript (ES6+)**, estruturada para demonstrar a expansão dos serviços dos Escritórios Sociais de 4 municípios físicos para a totalidade dos **78 municípios do Estado do Espírito Santo**.

A interface foi projetada com base nos princípios de:
1. **Design Institucional Capixaba:** Paleta oficial baseada nas cores da bandeira do Espírito Santo (Azul `#003366`, Rosa `#e63946`, Branco `#ffffff`) com toques modernos em azul ciano `#38bdf8` e azul primário `#0284c7`.
2. **Acessibilidade Universal:** Modos nativos de Alto Contraste (Dark Mode preto total com acentos ciano), Escala de Fonte (`1.18x`) e Linguagem Simplificada (*Modo Baixo Letramento*).
3. **Controle de Acesso RBAC Triplo:** Alternância dinâmica de visão entre **Gestor SEJUS**, **Técnico Escritório Social** e **Egresso/Familiar**.
4. **Prontuário Único Imutável:** Rastreabilidade estrita em conformidade com o Artigo 6º da LGPD.

---

## 2. Inventário Detalhado de Componentes e Vistas

### 2.1. Estrutura Global de Layout (Shell da Aplicação)

| Componente | Elementos HTML / Seletores | Descrição Funcional e Comportamento |
| :--- | :--- | :--- |
| **Top Header** | `.top-header`, `#sidebarToggleBtn`, `.brand-logo` | Barra superior fixa (sticky, 70px) contendo: botão hamburguer de recolhimento da sidebar, logotipo oficial com badge tricolor da bandeira do ES, título estilizado *"CONECTA EGRESSO"* e subtítulo *"SEJUS • Governo do Estado do Espírito Santo"*. |
| **Busca Global** | `.search-box`, `#globalSearchInput` | Input com ícone de lupa para pesquisa unificada de egressos, prontuários, vagas de emprego e serviços municipais. |
| **Barra de Acessibilidade** | `.accessibility-bar`, `#contrastBtn`, `#fontSizeBtn`, `#simplifiedTextBtn` | Três botões de pílula (`.btn-pill-sm`):<br>1. **Alto Contraste:** Alterna classe `high-contrast` no `<body>`.<br>2. **A+ (Tamanho da Fonte):** Modifica a variável CSS `--font-scale` de `1.0` para `1.18`.<br>3. **Linguagem Fácil:** Alterna classe `simplified-lang` e alerta o usuário sobre adaptação de termos. |
| **Seletor de Perfis (RBAC)** | `.profile-switcher`, `#userRoleSelect`, `#roleBadge` | Dropdown seletor de papel com 3 opções (`gestor`, `tecnico`, `egresso`). Ao alterar, modifica em tempo real o cabeçalho, avatar, títulos e escopo de atuação. |
| **Card de Autenticação Gov.br** | `.govbr-user-card`, `.govbr-badge`, `#userNameHeader`, `#userCpfHeader` | Badge azul Gov.br oficial com identificação do usuário logado, CPF mascarado e órgão de lotação. |
| **Sidebar Lateral** | `#sidebar`, `.sidebar`, `.sidebar.collapsed` | Barra de navegação lateral (270px expandida / 70px colapsada) com cartão de resumo do usuário logado, lista de 8 itens de navegação divididos em *Menu Principal* e *Gestão & Governança*, e selo institucional de rodapé SEJUS/SEGER. |

---

### 2.2. Inventário das 8 Vistas Funcionais

#### Vista 1: Dashboard Executivo & KPIs (`#view-dashboard`)
- **Banner Hero (`.hero-banner`):** Gradiente institucional `#003366` ➔ `#0284c7`, indicador pulsante *"Plataforma Oficial SEJUS/ES"*, chamada explicativa da cobertura dos 78 municípios, e botões de atalho rápido (*"Iniciar Atendimento Remoto"* e *"Exportar Relatório Sintético"*).
- **Cards de Métricas KPI (`.kpi-grid`):** 4 cartões em glassmorphism com borda colorida à esquerda:
  1. *Egressos Cadastrados:* `14.850` (Meta: 108.000, +12.4% no mês, barra de progresso 68%, azul).
  2. *Atendimentos Remotos:* `32.410` (98.4% satisfação, barra de progresso 85%, verde).
  3. *Encaminhamentos p/ Emprego:* `4.120` (76% contratados, barra de progresso 76%, roxo).
  4. *Redução de Reincidência:* `-34.2%` (Comparado com média histórica, barra 90%, âmbar).
- **Gráficos Analíticos Dinâmicos (`.grid-2col`):**
  1. *Gráfico de Barras:* `#chartMunicipios` (Evolução de atendimentos por município capixaba: Vitória, Serra, Vila Velha, Cariacica, Linhares, Cachoeiro, Colatina, São Mateus com filtro de região).
  2. *Gráfico Donut de Efetividade:* `#chartReintegracao` (Divisão proporcional: Emprego 42%, Cursos 28%, Apoio Psicossocial 18%, Documentação 12%).
- **Prévia do Mapa Territorial Capixaba (`.es-map-preview-container`):** Mini mapa vetorial com 8 pontos interativos posicionados geograficamente e legenda lateral com porcentagens (Grande Vitória 62%, Norte 18%, Sul 12%, Serrana 8%).
- **Feed de Atividades em Tempo Real (`.activity-feed`):** 4 registros recentes com status pills (Acolhimento em Vídeo, Encaminhamento de Vaga, 2ª Via de RG, Carteira Digital emitida).

#### Vista 2: Atendimento Remoto & Videochamadas Seguras (`#view-atendimento`)
- **Cabeçalho com Telemetria de Servidor:** Indicador pulsante verde de status do servidor WebRTC SEJUS com criptografia de ponta a ponta.
- **Painel de Fila de Espera (`.queue-card`):**
  - Lista de pessoas aguardando atendimento com avatar, nome, município de residência (com destaque para cidades sem escritório físico), etiqueta do tipo de acolhimento (Psicossocial, Documental, Trabalho) e botão *"Entrar no Atendimento"*.
  - Bloco de Agendamentos do Dia (horários formatados: 14:00, 15:30).
- **Janela de Videochamada Simulada (`.videocall-card`, `.video-window`):**
  - Feed principal do usuário remoto com badge *"EM ATENDIMENTO REMOTO AO VIVO"*, cronômetro de duração da chamada (*"Duração: 08:42"*), avatar central e indicador de conexão (*"Conectado via Smartphone • São Mateus/ES - 4G Estável"*).
  - Janela Picture-in-Picture (PiP) do técnico/atendente (*"Dra. Márcia - Você"*).
  - Barra de controles flutuante: Microfone 🎙️, Câmera 📹, Compartilhar Tela 🖥️, Botão Vermelho de Encerrar Chamada 📞.
- **Painel Integrado de Notas e Prontuário (`.call-notes-panel`):**
  - Dropdown para selecionar encaminhamento (Qualificação SENAI, Vaga de Emprego, Apoio Psicossocial, Documentação).
  - Textarea para observações e parecer técnico.
  - Botão de ação primária: *"Salvar no Prontuário & Concluir Atendimento"*.

#### Vista 3: Oportunidades & Qualificação Profissional (`#view-oportunidades`)
- **Barra de Filtros (`.filter-bar`):**
  - Filtro por Tipo: *Todos, Vagas de Emprego, Cursos de Capacitação, Menor Aprendiz/Estágio*.
  - Filtro por Município: *Todos os 78 Municípios, Vitória, Serra, Vila Velha, Cariacica, Linhares*.
  - Filtro por Escolaridade: *Todas, Fundamental, Médio, Técnico/Superior*.
- **Grid de Oportunidades (`.grid-3col`):** 6 cartões detalhados contendo:
  - Badge de categoria (Vaga de Emprego [Verde], Curso Gratuito [Azul/Roxo], Capacitação [Roxo]).
  - Título do cargo/curso, Empresa ou Instituição parceira (Porto de Tubarão, SENAI/Findes, Cooperativa Agropecuária, IFES, Construtora Capixaba, ADERES/Banestes).
  - Lista de requisitos: Localização, Remuneração/Bolsa, Escolaridade e Selo de Ação Afirmativa (*"Empresa Amiga da Reintegração"*).
  - Botão de ação: *"Encaminhar Egresso"*, *"Inscrever Egresso"* ou *"Agendar Orientação"*.
- **Ação de Gestão:** Botão superior *"Cadastrar Nova Vaga / Curso"*.

#### Vista 4: Carteira Digital & Documentos Básicos (`#view-carteira`)
- **Visualizador da Carteira Digital (`.id-card-widget`):**
  - Cartão estilizado com gradiente institucional e borda azul ciano.
  - Brasão e cabeçalho oficial: *"ESTADO DO ESPÍRITO SANTO • SEJUS • ESCRITÓRIO SOCIAL"*.
  - Foto do usuário com badge verde *"✓ Verificado"*.
  - Dados cadastrais completos: Nome, CPF mascarado, Número de Registro SEJUS (`ES-2026-948102`), Município de Residência e Data de Emissão.
  - Validador visual QR Code com selo de validade estadual (Lei 182/2021).
  - Botões: *"Baixar Carteira em PDF"* (com assinatura digital) e *"Compartilhar Validação"*.
- **Painel de Solicitação de 2ª Via de Documentos:** 4 serviços gratuitos integrados:
  1. 2ª Via de RG / CIN (Polícia Científica / Faça Fácil).
  2. Certidão de Nascimento / Casamento (Isenção via Defensoria Pública).
  3. Regularização Eleitoral (Título de Eleitor).
  4. Certidão de Execução Penal / Nada Consta.

#### Vista 5: Mapeamento Territorial dos 78 Municípios (`#view-geolocalizacao`)
- **Painel Interativo de Municípios (`.map-grid-interactive`):** Botões para seleção dos municípios sede com unidade física (*Vitória, Vila Velha, Serra, Cariacica*) e municípios remotos (*Linhares, Cachoeiro, Colatina, São Mateus, Aracruz, Guarapari, Viana, +67 Municípios do Interior*).
- **Painel Dinâmico de Detalhes Municipais (`#muniDetailsPanel`):**
  - Nome do município selecionado e tag de cobertura (*Escritório Social Físico* vs *Atendimento Remoto Conecta Egresso*).
  - Estatísticas locais: Número de egressos atendidos e porcentagem de atendimento remoto ativo (ex: 94%).
  - Relação da Rede Socioassistencial Parceira local: CRAS Central, Casa do Cidadão, CAPS / Unidade de Saúde e SINE.
  - Alerta explicativo de roteamento inteligente por geolocalização.

#### Vista 6: Prontuário Único & Registros Imutáveis (`#view-prontuario`)
- **Barra de Busca Rápida:** Campo de busca por Nome, CPF ou Registro SEJUS do egresso.
- **Ficha Cadastral do Atendido:** Avatar, nome, CPF, registro SEJUS, pílula de status (*Em Acompanhamento Remoto*), município, escolaridade, áreas de interesse, contato WhatsApp verificado e assistente social responsável.
- **Linha do Tempo de Evolução Social (`.timeline`):**
  - Linha contínua com nós circulares coloridos por tipo de registro (Verde = Acolhimento em Vídeo, Roxo = Validação de Carteira Digital, Azul = Primeiro Acolhimento Pós-Livramento).
  - Carimbo imutável de data, hora e técnico responsável (LGPD Art. 6º).

#### Vista 7: Relatórios & Análise SEJUS (`#view-relatorios`)
- **Filtros Gerenciais:** Filtro por Período (2026, 6 meses, 2025), Região SEJUS (Metropolitana, Norte, Sul) e Tipo de Atendimento.
- **Ação de Exportação:** Botão para download de Relatório Consolidado em PDF assinado digitalmente.
- **Tabela Sintética por Município (`.custom-table`):** Colunas: Município, Tipo de Atendimento, Egressos Atendidos, Encaminhamentos Emprego, Redução Reincidência (com badges coloridos) e Status de Cobertura.

#### Vista 8: Segurança da Informação & LGPD (`#view-lgpd`)
- **Matriz de Permissões RBAC:** Detalhamento formal dos privilégios para os 3 perfis: Egresso/Familiar (Acesso Restrito ao Próprio Perfil), Técnico/Atendente (Operacional e Clínico), Gestor SEJUS (Estratégico e Auditoria).
- **Garantias de Privacidade e Sigilo:** Criptografia de Ponta a Ponta (AES-256), Consentimento Livre e Esclarecido, e Trilha de Auditoria Automática.

#### Modal de Simulação de Videochamada (`#videoModal`)
- Modal com efeito backdrop blur, avatar, nome do atendido, município, indicador de sinal 4G e animação de ondas sonoras (`.call-wave-animation`), com botão para abrir a sala de atendimento psicossocial.

---

## 3. Estruturas de Dados e Modelos Identificados no Frontend

O código JavaScript (`app.js`) e as marcações HTML revelam os seguintes modelos de dados a serem formalizados no backend Laravel e no estado reativo do Vue 3:

### 3.1. Modelo `User` / `Profile`
```typescript
interface UserProfile {
  id: string | number;
  name: string;
  cpf: string;
  role: 'gestor' | 'tecnico' | 'egresso';
  roleTitle: string;        // Ex: "Visão Gestor Estadual", "Técnico Escritório Social"
  roleScope: string;        // Ex: "78 Municípios • SEJUS/ES", "São Mateus / ES"
  avatarInitials: string;   // Ex: "CS", "MO", "LS"
  cressOrCredential?: string; // Ex: "CRESS 4891/ES"
  authSource: 'govbr' | 'acesso_cidadao' | 'local';
}
```

### 3.2. Modelo `AttendanceQueueItem` & `VideoSession`
```typescript
interface AttendanceQueueItem {
  id: string;
  egressoId: string;
  name: string;
  initials: string;
  municipality: string;
  isRemoteWithoutOffice: boolean;
  serviceType: 'Psicossocial' | 'Documental' | 'Trabalho' | 'Jurídico';
  priorityLevel: 'normal' | 'preferencial' | 'urgente';
  status: 'waiting' | 'in_call' | 'completed' | 'scheduled';
  scheduledAt?: string;
  connectionTelemetry?: {
    networkType: '4G' | '5G' | 'WiFi';
    signalQuality: 'excelente' | 'boa' | 'instavel';
    packetLossPct: number;
    latencyMs: number;
  };
}

interface CallRecordPayload {
  attendanceId: string;
  egressoId: string;
  technicianId: string;
  referralType: string;
  notes: string;
  durationSeconds: number;
  timestamp: string;
}
```

### 3.3. Modelo `Opportunity` (Vagas e Cursos)
```typescript
interface Opportunity {
  id: string;
  type: 'emprego' | 'curso' | 'estagio' | 'capacitacao';
  title: string;
  partnerCompany: string;
  location: string;
  compensation: string;       // Ex: "R$ 2.100,00 + Benefícios" ou "Bolsa R$ 400,00"
  schoolingRequired: string;
  isInclusiveAffirmative: boolean;
  programTag: string;          // Ex: "Empresa Amiga da Reintegração", "Inclusão no Campo SEJUS"
  durationHours?: number;
  spotsAvailable?: number;
}
```

### 3.4. Modelo `DigitalWallet` & `DocumentRequest`
```typescript
interface DigitalWallet {
  id: string;
  egressoName: string;
  cpf: string;
  sejusRegisterCode: string;   // Ex: "ES-2026-948102"
  municipality: string;
  issueDate: string;
  isVerified: boolean;
  qrCodeSignatureToken: string;
  legalBase: string;           // "Lei 182/2021"
}

interface DocumentRequest {
  id: string;
  egressoId: string;
  documentType: 'RG' | 'Certidão' | 'Título' | 'Nada Consta';
  partnerAgency: string;       // "Polícia Científica", "Defensoria Pública", "TRE/ES"
  status: 'solicitado' | 'em_processamento' | 'pronto_para_retirada';
  pickupLocation: string;
}
```

### 3.5. Modelo `MunicipalityTerritory`
```typescript
interface MunicipalityTerritory {
  id: string;
  name: string;
  region: 'Metropolitana' | 'Norte' | 'Noroeste' | 'Central' | 'Sul' | 'Caparaó';
  hasPhysicalOffice: boolean;
  attendedEgressosCount: number;
  remoteAttendanceRatePct: number;
  coordinates?: { lat: number; lng: number };
  partnerNetwork: {
    cras: string;
    casaDoCidadao?: string;
    capsOuSaude: string;
    sine: string;
  };
}
```

### 3.6. Modelo `ProntuarioEvolution`
```typescript
interface ProntuarioEvolutionEntry {
  id: string;
  egressoId: string;
  timestamp: string;
  authorName: string;
  authorRole: string;
  category: 'video' | 'documental' | 'psicossocial' | 'trabalho' | 'sistema';
  title: string;
  description: string;
  immutableHash: string;
}
```

---

## 4. Design Tokens, Paleta de Cores, Tipografia e CSS

### 4.1. Tokens de Cores Principais (`:root`)
- **Cores Oficiais do ES / Brand:**
  - `--es-blue`: `#003366` (Azul Marinho Institucional)
  - `--es-pink`: `#e63946` (Rosa/Vermelho da Bandeira do ES)
  - `--es-light-blue`: `#38bdf8` (Azul Céu / Destaques e Bordas de Destaque)
- **Cores de Interface / Ações Primárias:**
  - `--primary`: `#0284c7` (Sky 600)
  - `--primary-hover`: `#0369a1` (Sky 700)
  - `--primary-light`: `#e0f2fe` (Sky 100)
- **Cores Semânticas de Estado:**
  - `--success`: `#10b981` (Emerald 500), `--success-light`: `#d1fae5`
  - `--warning`: `#f59e0b` (Amber 500), `--warning-light`: `#fef3c7`
  - `--danger`: `#ef4444` (Red 500), `--danger-light`: `#fee2e2`
  - `--purple`: `#8b5cf6` (Purple 500), `--purple-light`: `#ede9fe`
- **Fundos e Textos:**
  - `--bg-main`: `#f4f7fb` (Cinza Azulado Suave)
  - `--bg-card`: `#ffffff`
  - `--bg-sidebar`: `#0f172a` (Slate 900)
  - `--text-main`: `#1e293b` (Slate 800)
  - `--text-muted`: `#64748b` (Slate 500)
  - `--border-color`: `#e2e8f0` (Slate 200)

### 4.2. Tema de Alto Contraste (`body.high-contrast`)
- Fundo Principal e Sidebar: `#000000` (Preto Absoluto)
- Cartões e Header: `#121212`
- Texto Principal: `#ffffff`
- Texto Secundário: `#cccccc`
- Bordas: `#444444`
- Cor Primária e Foco: `#00ffff` (Ciano de Alto Contraste)
- Fundo Ativo Primário: `#003333`

### 4.3. Tipografia e Escalas
- **Fontes do Google Fonts:**
  - `Inter` (300, 400, 500, 600, 700, 800) — Corpo de texto, tabelas, inputs e labels.
  - `Outfit` (500, 600, 700, 800) — Títulos, logotipos, números de KPI e cabeçalhos de destaque.
- **Escala de Acessibilidade:**
  - Padrão: `--font-scale: 1` (`15px` base).
  - Modo A+: `--font-scale: 1.18` (`17.7px` base).
  - Modo Linguagem Simplificada: `--font-scale: 1.1` com `letter-spacing: 0.02em`.

### 4.4. Breakpoints e Estruturas de Grid
- **Sidebar:** `270px` (padrão) ➔ `70px` (modo recolhido com transição cúbica `0.3s cubic-bezier(0.4, 0, 0.2, 1)`).
- **Header:** `70px` de altura fixa com `position: sticky; z-index: 100;`.
- **Grids Responsivos:**
  - `.kpi-grid`: `repeat(auto-fit, minmax(230px, 1fr))`
  - `.grid-2col`: `repeat(auto-fit, minmax(420px, 1fr))`
  - `.grid-3col`: `repeat(auto-fit, minmax(300px, 1fr))`
  - `.grid-atendimento`: `320px 1fr` (Fila à esquerda, tela de vídeo à direita)
  - `.map-grid-interactive`: `repeat(auto-fill, minmax(130px, 1fr))`

---

## 5. Recomendações Técnicas para Migração (Inertia.js + Vue 3 SFC + TailwindCSS)

Para garantir **100% de paridade visual e funcional** com o protótipo, elevando a manutenibilidade, reatividade e segurança, recomenda-se a seguinte estrutura de implementação:

### 5.1. Mapeamento de Cores no `tailwind.config.js`
```javascript
export default {
  darkMode: 'class',
  content: [
    './resources/views/**/*.blade.php',
    './resources/js/**/*.vue',
    './resources/js/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        'es-blue': '#003366',
        'es-pink': '#e63946',
        'es-sky': '#38bdf8',
        'sejus-primary': {
          DEFAULT: '#0284c7',
          hover: '#0369a1',
          light: '#e0f2fe',
        },
        'sejus-sidebar': '#0f172a',
      },
      fontFamily: {
        sans: ['Inter', 'sans-serif'],
        heading: ['Outfit', 'Inter', 'sans-serif'],
      },
    },
  },
  plugins: [],
}
```

### 5.2. Arquitetura de Componentes Vue 3

```
resources/js/
├── Layouts/
│   ├── AuthenticatedLayout.vue       # Header fixo, sidebar colapsável, acessibilidade, container de notificações
│   └── GuestLayout.vue               # Tela de Login Gov.br / Acesso Cidadão
├── Components/
│   ├── Navigation/
│   │   ├── TopHeader.vue             # Toggle menu, logo ES, busca global, perfil ativo, Gov.br card
│   │   ├── SidebarNav.vue            # Menus 1 a 8, badges reativos, selo SEJUS/SEGER
│   │   ├── AccessibilityControls.vue # Alto contraste, A+, Linguagem Fácil
│   │   └── RoleSwitcher.vue          # Alternador de perfis (Gestor / Técnico / Egresso)
│   ├── Dashboard/
│   │   ├── HeroBanner.vue            # Banner com botões de ação rápida
│   │   ├── KpiCard.vue               # Cartão de métrica genérico e reativo
│   │   ├── MunicipiosChart.vue       # Wrapper Vue para Chart.js (Gráfico de barras dos 78 municípios)
│   │   ├── ReintegracaoDonut.vue     # Gráfico Donut de efetividade social
│   │   ├── EsMiniMap.vue             # Mini mapa com pontos e porcentagens regionais
│   │   └── ActivityFeed.vue          # Lista de eventos recentes
│   ├── Atendimento/
│   │   ├── QueueList.vue             # Fila de acolhimento com botões de entrada
│   │   ├── ScheduledList.vue         # Agendamentos do dia
│   │   ├── WebRtcVideoRoom.vue       # Sala de vídeo WebRTC com PiP, timers e controles de mídia
│   │   ├── ConnectionTelemetry.vue   # Qualidade de rede (4G/Wi-Fi, latência, perda)
│   │   └── ClinicalNotesPanel.vue    # Formulário integrado de notas do prontuário
│   ├── Oportunidades/
│   │   ├── OpportunityFilter.vue     # Filtros por cidade, tipo e escolaridade
│   │   ├── OpportunityCard.vue       # Cartão de vaga/curso com ação afirmativa
│   │   └── NewOpportunityModal.vue   # Modal de cadastro de vagas parceiras
│   ├── Carteira/
│   │   ├── DigitalWalletCard.vue     # Renderização visual do cartão oficial
│   │   ├── QrCodeDisplay.vue         # Renderizador de QR Code assinado
│   │   ├── DocumentRequestCard.vue   # Solicitações de 2ª via (RG, Certidão, etc.)
│   │   └── PdfExportButton.vue       # Gatilho de download do PDF autenticado
│   ├── Territorio/
│   │   ├── EsTerritorialMap.vue      # Mapa interativo com 78 municípios e regiões do ES
│   │   └── MunicipalityDetails.vue   # Detalhes da rede socioassistencial e estatísticas
│   ├── Prontuario/
│   │   ├── EgressoProfileCard.vue    # Dados cadastrais e situação jurídica/social
│   │   └── EvolutionTimeline.vue     # Linha do tempo imutável de registros
│   ├── Relatorios/
│   │   └── SyntheticReportTable.vue  # Tabela com dados consolidados por município
│   └── LGPD/
│       ├── RbacMatrix.vue            # Matriz de privilégios dos 3 perfis
│       └── SecurityGuarantees.vue    # Indicadores de criptografia e auditoria
└── Pages/
    ├── Dashboard/Index.vue
    ├── Atendimento/Index.vue
    ├── Oportunidades/Index.vue
    ├── Carteira/Index.vue
    ├── Territorio/Index.vue
    ├── Prontuario/Index.vue
    ├── Relatorios/Index.vue
    └── LGPD/Index.vue
```

### 5.3. Integração com WebRTC Python e APIs Laravel
1. **WebSockets / Sinalização:** Conectar o componente `WebRtcVideoRoom.vue` ao microsserviço Python FastAPI via WebSocket para troca de SDP (*Offer/Answer*) e *ICE Candidates*, atualizando o componente `ConnectionTelemetry.vue` em tempo real.
2. **Auditoria LGPD no Prontuário:** Todas as ações salvas em `ClinicalNotesPanel.vue` ou visualizações de `Prontuario/Index.vue` devem disparar requisições Inertia com registro automático do CPF do operador, timestamp do servidor e hash criptográfico.
3. **Emissão de Carteira Digital:** O componente `PdfExportButton.vue` deve acionar a rota do Laravel (`/carteira-digital/pdf`) que compila o layout oficial com QR Code gerado dinamicamente via `simplesoftwareio/simple-qrcode` ou biblioteca compatível.
4. **Mapeamento Territorial:** Substituir os botões simples de municípios por uma camada vetorial SVG/GeoJSON dos 78 municípios do Espírito Santo, mantendo a reatividade com o painel `MunicipalityDetails.vue`.

---

## 6. Conclusão do Levantamento

O protótipo atual possui uma base de design rica, visualmente sofisticada e altamente aderente aos requisitos do Edital CPSI Nº 010/2026. Todas as 8 vistas, tokens visuais, componentes de acessibilidade e fluxos de interação foram catalogados e estão prontos para a implementação em **Laravel 11 + Inertia.js + Vue 3 + TailwindCSS**.
