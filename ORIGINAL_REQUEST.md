# Original User Request

## 2026-08-17T12:13:16Z

<USER_REQUEST>
Plataforma Web completa para o sistema CONECTA EGRESSO (SEJUS/ES), integrando backend robusto em Laravel 11 (PHP 8.3/8.4) com Inertia.js + Vue 3 e TailwindCSS, microsserviço de WebRTC/Sinalização em Python (FastAPI/aiortc/WebSockets), banco de dados PostgreSQL 16 com criptografia LGPD, Redis e orquestração Docker Compose.

Working directory: d:\Agile\projeto dia 18

Integrity mode: development

## Requirements

### R1. Backend Core & APIs (Laravel 11 / PHP 8.3+)
- Estruturação do backend em Laravel 11 com Inertia.js, autenticação com suporte a OAuth2/OpenID Connect (Acesso Cidadão / Gov.br) e controle de perfis RBAC (Gestor SEJUS, Técnico Escritório Social, Egresso/Familiar).
- Módulos de negócio: Prontuário Único (trilha de auditoria imutável LGPD), Oportunidades & Vagas de Emprego, Carteira Digital com emissão de PDF e QR Code criptográfico, e Mapeamento Territorial dos 78 municípios com geolocalização.

### R2. Serviço de Videochamada & Atendimento Remoto (Python FastAPI + WebRTC)
- Microsserviço assíncrono em Python (FastAPI / WebSockets / aiortc) para controle de salas de videochamada seguras, sinalização SDP/ICE, fila de espera em tempo real e monitoramento de telemetria/qualidade da conexão.
- Integração via webhooks e JWT com o backend Laravel para registro automático de início, término e gravação/metadados no prontuário do atendido.

### R3. Frontend Reativo & Acessível (Inertia.js + Vue 3 + TailwindCSS)
- Interface reativa construída com Inertia.js e Vue 3, replicando os componentes visuais já validados (dashboard de KPIs, linha do tempo de prontuário, mapa dos 78 municípios, fila de atendimento e tela de vídeo).
- Suporte nativo a Alto Contraste, aumento de fonte e modo de Linguagem Simplificada (baixo letramento digital).

### R4. Infraestrutura & Orquestração (Docker Compose)
- Orquestração completa de contêineres: Nginx, PHP 8.3-FPM / Laravel, Python WebRTC (FastAPI), PostgreSQL 16 (com PostGIS/pgcrypto), Redis (filas/cache) e servidor Coturn (STUN/TURN para suporte a conexões móveis 3G/4G/5G).

## Acceptance Criteria

### Autenticação & Permissões
- [ ] Login funcional com alternância entre os 3 perfis (Gestor, Técnico, Egresso) com permissões estritas por rota.
- [ ] Trilha de auditoria gravando usuário, timestamp e ação em todas as alterações e consultas de prontuários.

### Videochamada WebRTC
- [ ] Dois usuários conseguem se conectar em uma sala de vídeo privada com áudio e vídeo bidirecionais via sinalização Python.
- [ ] O encerramento da chamada registra automaticamente a duração e metadados no backend Laravel.

### Módulos de Negócio & Carteira Digital
- [ ] Emissão de Carteira Digital em PDF com QR Code legível que valida o registro do egresso.
- [ ] Filtro funcional de vagas de trabalho e cursos por município do Espírito Santo.
- [ ] Dashboard de KPIs gerando métricas agregadas por município.

### Orquestração Docker
- [ ] Execução unificada de todos os serviços através de `docker compose up -d`.
</USER_REQUEST>

## 2026-08-17T14:16:12Z

<USER_REQUEST>
Plataforma Web completa para o sistema CONECTA EGRESSO (SEJUS/ES), integrando backend robusto em Laravel 11 (PHP 8.3/8.4) com Inertia.js + Vue 3 e TailwindCSS, microsserviço de WebRTC/Sinalização em Python (FastAPI/aiortc/WebSockets), banco de dados PostgreSQL 16 com criptografia LGPD, Redis e orquestração Docker Compose.

Continuar e concluir a execução a partir do PROJECT.md e dos arquivos já existentes no repositório.

Working directory: d:\Agile\projeto dia 18

Integrity mode: development

## Requirements

### R1. Backend Core & APIs (Laravel 11 / PHP 8.3+)
- Estruturação do backend em Laravel 11 com Inertia.js, autenticação com suporte a OAuth2/OpenID Connect (Acesso Cidadão / Gov.br) e controle de perfis RBAC (Gestor SEJUS, Técnico Escritório Social, Egresso/Familiar).
- Módulos de negócio: Prontuário Único (trilha de auditoria imutável LGPD), Oportunidades & Vagas de Emprego, Carteira Digital com emissão de PDF e QR Code criptográfico, e Mapeamento Territorial dos 78 municípios com geolocalização.

### R2. Serviço de Videochamada & Atendimento Remoto (Python FastAPI + WebRTC)
- Microsserviço assíncrono em Python (FastAPI / WebSockets / aiortc) para controle de salas de videochamada seguras, sinalização SDP/ICE, fila de espera em tempo real e monitoramento de telemetria/qualidade da conexão.
- Integração via webhooks e JWT com o backend Laravel para registro automático de início, término e gravação/metadados no prontuário do atendido.

### R3. Frontend Reativo & Acessível (Inertia.js + Vue 3 + TailwindCSS)
- Interface reativa construída com Inertia.js e Vue 3, replicando os componentes visuais já validados (dashboard de KPIs, linha do tempo de prontuário, mapa dos 78 municípios, fila de atendimento e tela de vídeo).
- Suporte nativo a Alto Contraste, aumento de fonte e modo de Linguagem Simplificada (baixo letramento digital).

### R4. Infraestrutura & Orquestração (Docker Compose)
- Orquestração completa de contêineres: Nginx, PHP 8.3-FPM / Laravel, Python WebRTC (FastAPI), PostgreSQL 16 (com PostGIS/pgcrypto), Redis (filas/cache) e servidor Coturn (STUN/TURN para suporte a conexões móveis 3G/4G/5G).

## Acceptance Criteria

### Autenticação & Permissões
- [ ] Login funcional com alternância entre os 3 perfis (Gestor, Técnico, Egresso) com permissões estritas por rota.
- [ ] Trilha de auditoria gravando usuário, timestamp e ação em todas as alterações e consultas de prontuários.

### Videochamada WebRTC
- [ ] Dois usuários conseguem se conectar em uma sala de vídeo privada com áudio e vídeo bidirecionais via sinalização Python.
- [ ] O encerramento da chamada registra automaticamente a duração e metadados no backend Laravel.

### Módulos de Negócio & Carteira Digital
- [ ] Emissão de Carteira Digital em PDF com QR Code legível que valida o registro do egresso.
- [ ] Filtro funcional de vagas de trabalho e cursos por município do Espírito Santo.
- [ ] Dashboard de KPIs gerando métricas agregadas por município.

### Orquestração Docker
- [ ] Execução unificada de todos os serviços através de `docker compose up -d`.
</USER_REQUEST>

## 2026-08-18T12:56:31Z

<USER_REQUEST>
Plataforma Web completa para o sistema CONECTA EGRESSO (SEJUS/ES), integrando backend robusto em Laravel 11 (PHP 8.3/8.4) com Inertia.js + Vue 3 e TailwindCSS, microsserviço de WebRTC/Sinalização em Python (FastAPI/aiortc/WebSockets), banco de dados PostgreSQL 16 com criptografia LGPD, Redis e orquestração Docker Compose.

Refatoração das notificações de alerta e implementação da rota de download da Carteira Digital em PDF no Conecta Egresso (SEJUS/ES) rodando localmente.

Working directory: d:\Agile\projeto dia 18
Integrity mode: development

## Requirements

### R1. Sistema de Notificações (Toasts)
- Substituir todas as chamadas nativas de `alert()` nos arquivos Vue (`Atendimento.vue`, `Carteira.vue`, `Oportunidades.vue`, `Relatorios.vue`, `SegurancaLgpd.vue`) por um componente elegante de Toasts posicionado no canto superior direito.
- Suportar estados de Sucesso, Erro, Alerta e Informação com cores adequadas, ícones/emojis e transições de entrada/saída suaves.

### R2. Rota de Download e Geração de PDF da Carteira Digital
- Registrar a rota GET `/carteira/pdf` em `routes/web.php` e acoplar ao serviço `CarteiraPdfService` existente para compilar e descarregar o PDF correto da carteira com o QR Code.
- Caso o usuário não esteja logado no localhost, buscar o primeiro egresso cadastrado no banco como fallback para evitar que a página trave e permitir testes imediatos.

### R3. Autenticação Completa (Login & Logout na UI)
- Criar a página de login reativa (`Login.vue` ou semelhante) com a identidade do **Gov.br / Acesso Cidadão** e do Governo do Estado do Espírito Santo.
- Proteger rotas internas para exigir autenticação (com bypass ou login automático se necessário em desenvolvimento local, mas funcional com credenciais).
- Adicionar botão de **Sair/Logout** no menu/cabeçalho da aplicação que realiza o encerramento seguro da sessão.

### R4. Usuário de Suporte (Agile) & Gerenciamento de Usuários
- Adicionar o perfil de `suporte` (com permissões administrativas completas) e cadastrar o usuário inicial `suporte.agile@sejus.es.gov.br` (senha: `secret123`) via seeder.
- Implementar uma tela de **Gerenciamento de Usuários** (acessível por administradores e suporte) que permita cadastrar, listar e editar perfis de usuários (Gestor, Técnico, Egresso, Familiar) com campos de Nome, Email, Senha, CPF e Município.

### R5. Auditoria de Links no Localhost
- Revisar todos os links no frontend e rotas web no Laravel para garantir 100% de funcionamento sem erros 404.

## Acceptance Criteria

### Autenticação & Gerenciamento
- [ ] O usuário consegue fazer login e logout na interface reativa.
- [ ] O usuário `suporte.agile@sejus.es.gov.br` consegue acessar o menu de gerenciamento e cadastrar novos usuários.
- [ ] Perfis de usuários salvos no banco são associados corretamente aos papéis de segurança do Laravel.

### Toasts & Notificações
- [ ] Todas as chamadas de `alert()` foram eliminadas do código Vue.
- [ ] Mensagens de sucesso ao cadastrar usuário, reemitir carteira ou salvar prontuário aparecem em Toasts modernos no canto superior direito.

### Carteira Digital em PDF
- [ ] A rota `/carteira/pdf` retorna um stream de PDF válido no navegador com cabeçalhos apropriados.
</USER_REQUEST>

