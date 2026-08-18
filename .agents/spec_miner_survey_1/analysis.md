# Levantamento e Mineração de Especificações Técnicas e Funcionais
## Plataforma CONECTA EGRESSO — SEJUS / Governo do Estado do Espírito Santo

**Documento:** Especificação Atomizada de Requisitos, Modelos de Dados e Arquitetura  
**Fase:** Levantamento e Reconhecimento Técnico (Survey Phase)  
**Autor:** Agente Spec Miner 1 (`spec_miner_survey_1`)  
**Data:** 17 de Agosto de 2026  
**Status:** Especificação Final Consolidada  

---

## 1. Fontes Autoritativas e Contexto Executivo

A mineração de requisitos deste documento fundamenta-se nas seguintes fontes autoritativas do projeto:
1. **`ORIGINAL_REQUEST.md`:** Requisitos mandatórios (R1 a R4) e Critérios de Aceitação para a plataforma completa em Laravel 11 + Inertia.js / Vue 3 + TailwindCSS, microsserviço de WebRTC em Python (FastAPI / aiortc / WebSockets), PostgreSQL 16 com PostGIS/pgcrypto, Redis 7 e Coturn TURN Server.
2. **`TR_EDITAL_DE CPSI Nº 010_2026 - SEJUS.pdf`:** Termo de Referência do Edital de Contratação Pública de Solução Inovadora (CPSI Nº 010/2026 – SEJUS/SEGER – Lei Complementar nº 182/2021), estabelecendo a superação da barreira geográfica dos Escritórios Sociais (de 4 municípios presenciais para a totalidade dos 78 municípios capixabas), atendimento a mais de 108 mil pessoas egressas e familiares, conformidade estrita com o Art. 6º da LGPD e metas de redução da reincidência criminal.
3. **`DOCUMENTO_EXECUTIVO_CONECTA_EGRESSO.md` & `README.md`:** Memorando executivo e sumário de regras de negócio.
4. **Protótipo Validado (`index.html`, `styles.css`, `app.js`):** Arquitetura visual de 8 painéis, design system capixaba, modos de acessibilidade (Alto Contraste, Fonte A+ e Linguagem Fácil) e simulações de fluxo de atendimento.

---

## 2. Features Discovered (Tabela Exaustiva de Funcionalidades)

| # | Categoria | Funcionalidade | Descrição | Entradas (Inputs) | Saídas (Outputs) | Comportamento em Caso de Erro | Fonte / Descoberta |
|---|---|---|---|---|---|---|---|
| **F01** | Autenticação & RBAC | Login Federado Gov.br / Acesso Cidadão | Autenticação unificada via OpenID Connect/OAuth2 (PRODEST / Gov.br) vinculando o usuário ao seu CPF e papel governamental. | Código de autorização OAuth2 / token JWT Acesso Cidadão (`sub`, `cpf`, `email`, `nome`). | Sessão autenticada no Laravel, cookie de sessão seguro (`HttpOnly`, `SameSite=Lax`), redirecionamento para o Dashboard correspondente. | Falha de autenticação redireciona para tela de erro amigável com código de rastreio e registra tentativa de login inválida. | `ORIGINAL_REQUEST.md` (R1), `TR` (Item 3.1 `e`, `i`) |
| **F02** | Autenticação & RBAC | Login Local de Contingência / Demonstração | Permite login com e-mail/senha com verificação de credenciais criptografadas via bcrypt. | E-mail corporativo / institucional e senha. | Token de sessão e carregamento do perfil de acesso. | Retorna erro 422 com mensagem "Credenciais inválidas" e limitação de taxa (Rate Limiting 5 tentativas/min). | `ORIGINAL_REQUEST.md` (R1), `app.js` |
| **F03** | Autenticação & RBAC | Alternância Dinâmica de Perfis (Role Switcher) | Permite alternar a visão do sistema entre Gestor SEJUS, Técnico do Escritório Social e Egresso/Familiar (respeitando as permissões associadas). | Seleção de Perfil no menu superior (`role: 'gestor' \| 'tecnico' \| 'egresso'`). | Atualização reativa de layout, permissões de navegação, avatar, escopo e endpoints autorizados. | Erro 403 Forbidden se o usuário autenticado tentar trocar para perfil não concedido em sua conta. | `ORIGINAL_REQUEST.md` (Critério 1), `index.html` (L63-73), `app.js` (L64-98) |
| **F04** | Autenticação & RBAC | Perfil Gestor SEJUS (Estratégico) | Visão executiva estadual cobrindo os 78 municípios, com acesso a KPIs consolidados, mapas de calor, taxas de reincidência e relatórios gerenciais sem expor dados sensíveis não autorizados. | Parâmetros de filtro temporal, filtros por região do ES e tipo de atendimento. | Gráficos Canvas/SVG, matrizes agregadas de dados e exportação de relatórios sintéticos. | Erro 403 ao tentar acessar evoluções clínicas/psicossociais individualizadas de prontuário sem justificativa legal. | `DOCUMENTO_EXECUTIVO.md` (Seção 2.A), `TR` (Item 3.1 `l`) |
| **F05** | Autenticação & RBAC | Perfil Técnico / Atendente (Operacional) | Foco na fila de videochamadas, agendamentos do dia, inserção de pareceres e notas de evolução no prontuário único do egresso e encaminhamento para vagas. | Identificador do egresso, tipo de atendimento, texto de evolução técnica, seleção de encaminhamento. | Criação de registro assinado no prontuário, atualização do status da fila e emissão de comprovante. | Validação estrita de campos obrigatórios; falha ao tentar editar registros criados por outros técnicos sem permissão de chefia. | `DOCUMENTO_EXECUTIVO.md` (Seção 2.B), `index.html` (L83-88) |
| **F06** | Autenticação & RBAC | Perfil Egresso / Familiar (Cidadão) | Interface simplificada de alta acessibilidade para autoatendimento: entrada em salas de vídeo, visualização de carteira digital, solicitação de 2ª via e candidatura a vagas. | Token de acesso pessoal, confirmação de presença em fila, formulários simplificados de solicitação de documentos. | Carteira Digital em tela/PDF, confirmação de agendamento e comprovante de inscrição em vagas. | Tentativa de acessar prontuários de terceiros bloqueada por middleware de autorização (retorna 404/403). | `ORIGINAL_REQUEST.md` (R1, R3), `index.html` (L89-96) |
| **F07** | Prontuário Único | Criação & Numeração Automática de Prontuário | Geração de identificador unificado único no padrão estadual (`PRT-2026-XXXXXX`) no primeiro acolhimento ou importação de cadastro penitenciário. | Dados biográficos do egresso (Nome, Data Nasc, Município, CPF, Status Prisional, Unidade de Origem). | Registro do Prontuário Único persistido em banco com chave UUID primária. | Impede duplicidade de prontuário para o mesmo CPF (Erro 409 Conflict). | `ORIGINAL_REQUEST.md` (R1), `TR` (Item 3.1 `d`, `m`) |
| **F08** | Prontuário Único | Busca Unificada de Prontuários | Localizador ágil de prontuários por Nome, CPF mascarado ou Número de Registro SEJUS. | String de busca (mínimo 3 caracteres ou CPF). | Lista de prontuários correspondentes com avatar, status atual e município de residência. | Retorna lista vazia com mensagem de orientação caso nenhum registro seja localizado. | `index.html` (L903-906), `app.js` |
| **F09** | Prontuário Único | Linha do Tempo de Evolução Social Imutável | Visualização cronológica contínua de todos os eventos da jornada de reintegração (acolhimentos em vídeo, entrevistas presenciais, emissão de documentos, inscrições em cursos). | `prontuario_id` e permissão de acesso. | Feed cronológico ordenado (data decrescente) com nós coloridos por categoria e identificação do responsável. | Acesso negado com registro em auditoria caso o usuário não possua permissão de leitura. | `ORIGINAL_REQUEST.md` (R1), `index.html` (L933-968) |
| **F10** | Prontuário Único | Registro de Atendimentos Multimodais | Formulário para lançamento de parecer técnico psicossocial decorrente de videochamada, atendimento presencial ou contato telefônico. | `tipo_atendimento`, `categoria`, `evolucao_texto`, `encaminhamento_detalhe`, `duracao_minutos`. | Registro gravado na tabela `prontuario_atendimentos`, carimbo automático do servidor (`NOW()`), CPF e cargo do técnico. | Rejeita submissões com texto em branco ou sem categoria definida (Erro 422 Unprocessable Entity). | `ORIGINAL_REQUEST.md` (R1), `index.html` (L502-520) |
| **F11** | Segurança & LGPD | Criptografia de Dados Sensíveis (pgcrypto / AES-256) | Criptografia simétrica em repouso para colunas contendo dados sensíveis (CPF, telefone, endereço, histórico clínico/psicossocial). | Texto em claro (Plaintext) nos modelos Eloquent. | Bytes criptografados armazenados nas colunas `BYTEA` do PostgreSQL. | Falha de decriptação lança exceção segura de log sem vazar a chave simétrica ou os dados em claro. | `ORIGINAL_REQUEST.md` (R1, R4), `TR` (Item 3.1 `c`) |
| **F12** | Segurança & LGPD | Trilha de Auditoria Imutável (Audit Trail) | Gravação automática de registros de log indelével para toda ação (`CREATE`, `VIEW`, `UPDATE`, `EXPORT_PDF`, `ANONYMIZE`) sobre prontuários, contendo IP, User-Agent, timestamp e payload hash. | Contexto da requisição HTTP / Inertia e identificador do usuário logado. | Registro persistido em `prontuario_audit_logs`. | Regras de banco (`CREATE RULE ... DO INSTEAD NOTHING`) bloqueiam qualquer tentativa de UPDATE ou DELETE na tabela de auditoria. | `ORIGINAL_REQUEST.md` (Critério 2), `TR` (Item 3.1 `d`, `3.5`) |
| **F13** | Segurança & LGPD | Termo de Consentimento Livre e Esclarecido (TCLE) | Coleta e armazenamento de aceite explícito do egresso para geolocalização e compartilhamento de dados com vagas conveniadas. | Aceite do usuário (`consent: true`, versão do termo, IP e timestamp). | Registro de consentimento vinculado ao perfil do egresso no banco de dados. | Impede ativação de geolocalização e envio de currículo sem o consentimento formal registrado. | `TR` (Item 3.1 `n`, 3.5), `index.html` (L1160-1163) |
| **F14** | WebRTC Video | Fila de Espera em Tempo Real (Waiting Queue) | Organização dinâmica dos egressos aguardando acolhimento remoto, classificados por município de residência e nível de prioridade (Urgente, Preferencial, Normal). | Entrada do egresso na sala/fila de espera com seleção de motivo de atendimento. | Lista em tempo real para os técnicos logados, com avatar, município sem escritório físico e botão de entrada. | Notificação de desconexão caso o egresso feche o navegador ou perca a conexão por mais de 30 segundos. | `ORIGINAL_REQUEST.md` (R2), `index.html` (L404-446) |
| **F15** | WebRTC Video | Sinalização SDP / ICE via WebSockets (FastAPI) | Troca assíncrona de ofertas (`offer`), respostas (`answer`) e candidatos ICE (`ice-candidate`) entre os navegadores do técnico e do egresso. | Mensagens JSON via WebSocket autenticadas com token JWT gerado pelo Laravel. | Encaminhamento de mensagens SDP/ICE ponto a ponto para estabelecimento do canal WebRTC PeerConnection. | Emissão de erro `4001 Unauthorized` no WebSocket se o JWT for inválido ou expirado. | `ORIGINAL_REQUEST.md` (R2), `architecture_survey.md` (Seção 3) |
| **F16** | WebRTC Video | Sala de Vídeo Privada com Controles de Mídia | Interface interativa de atendimento com feed de vídeo principal do atendido, janela Picture-in-Picture (PiP) do técnico, timer de chamada e botões de mudo/câmera/compartilhamento de tela. | Ações de clique nos botões de controle de mídia e tracks de áudio/vídeo do navegador. | Manipulação dos tracks `MediaStream` locais e sinalização de muting para o par remoto. | Exibição de banner de alerta caso a permissão de câmera/microfone seja negada pelo navegador. | `ORIGINAL_REQUEST.md` (R2, R3), `index.html` (L470-500) |
| **F17** | WebRTC Video | Telemetria de Conexão & Monitoramento de Qualidade | Coleta contínua de estatísticas de conexão (RTT, perda de pacotes, jitter, bitrate) e cálculo da métrica de qualidade MOS (Mean Opinion Score de 1.0 a 5.0). | Relatórios RTCP / `getStats()` enviados pelo cliente a cada 5 segundos. | Indicador visual em tela (Ex: *"4G Estável - MOS 4.8"*) e persistência dos metadados da sessão. | Em caso de degradação severa da rede, recomenda automaticamente desativar o vídeo para priorizar áudio. | `ORIGINAL_REQUEST.md` (R2), `app.js` (L300), `architecture_survey.md` |
| **F18** | WebRTC Video | Suporte a Relé Coturn (STUN/TURN) | Transposição de NAT simétrico e Carrier-Grade NAT (CGNAT) comum em conexões de redes móveis 3G/4G/5G do interior do Estado do Espírito Santo. | Configuração ICE Servers com credenciais efêmeras baseadas em HMAC emitidas dinamicamente pelo Laravel. | Tráfego de pacotes de mídia relay UDP/TCP via portas 3478/5349 e 49152-49200. | Fallback automático de STUN direto para TURN Relay quando a conexão direta falhar no ICE Gathering. | `ORIGINAL_REQUEST.md` (R4), `architecture_survey.md` (Seção 4) |
| **F19** | WebRTC Video | Webhooks de Ciclo de Vida da Chamada | O microsserviço Python notifica o backend Laravel via POST assinado (HMAC-SHA256) nos eventos `room.created`, `participant.joined` e `call.ended`. | Evento de ciclo de vida com `room_id`, `started_at`, `ended_at`, `duration_seconds` e estatísticas de rede. | Atualização do registro em `video_sessions` e vinculação automática com o prontuário do egresso. | Se o webhook falhar (status != 200), o FastAPI armazena no Redis e tenta novamente com backoff exponencial. | `ORIGINAL_REQUEST.md` (R2), `architecture_survey.md` (Seção 3.3) |
| **F20** | Oportunidades & Vagas | Painel Centralizador de Vagas de Emprego Inclusivas | Exibição em cards de vagas de empresas conveniadas com o selo *"Empresa Amiga da Reintegração"*, detalhando remuneração, localidade, escolaridade e benefícios. | Filtros aplicados pelo usuário (município, escolaridade, categoria). | Lista paginada de vagas ativas com botões de encaminhamento e detalhes. | Exibe estado vazio amigável caso não haja vagas cadastradas para o filtro selecionado. | `ORIGINAL_REQUEST.md` (R1, R3), `index.html` (L578-682) |
| **F21** | Oportunidades & Vagas | Painel de Cursos e Capacitações Gratuitas | Vitrine de capacitações técnicas e cursos profissionalizantes (SENAI, IFES, etc.), com carga horária, modalidade (EAD/Presencial) e benefício de bolsa auxílio. | Filtros por modalidade e localização municipal. | Cards de cursos com botão de inscrição do egresso. | Bloqueia inscrição se o número de vagas estiver esgotado, exibindo aviso de lista de espera. | `DOCUMENTO_EXECUTIVO.md` (Seção 2.C), `index.html` (L598-646) |
| **F22** | Oportunidades & Vagas | Filtro Multifatorial por Município Capixaba | Mecanismo de busca e filtragem que permite cruzar vagas e cursos com qualquer um dos 78 municípios do Estado do Espírito Santo. | Seleção de município no dropdown ou clique no mapa interativo. | Grid de oportunidades recalculado instantaneamente com apenas as opções disponíveis no território. | Retorna sugestões de vagas na microrregião vizinha quando o município selecionado não possuir vagas locais. | `ORIGINAL_REQUEST.md` (Critério 5), `index.html` (L543-575) |
| **F23** | Oportunidades & Vagas | Encaminhamento & Inscrição com 1 Clique | Ação rápida para técnicos ou egressos se candidatarem a uma vaga/curso, gerando registro na tabela `candidaturas` e notificação ao parceiro. | `vaga_id` ou `curso_id`, `egresso_id` e parecer técnico. | Criação da candidatura com status `encaminhado` e feedback visual na interface. | Erro caso o egresso já tenha se candidatado para a mesma vaga nos últimos 30 dias. | `DOCUMENTO_EXECUTIVO.md` (Item 3.1 `h`), `app.js` (L320-322) |
| **F24** | Oportunidades & Vagas | Modal de Cadastro de Oportunidades | Formulário restrito a gestores e parceiros homologados para publicação de novas vagas com incentivo fiscal SEJUS. | Dados da vaga (Empresa, Título, Salário, Regime, Vagas Totais, Requisitos, Município). | Nova oportunidade registrada no banco de dados e disponível em tempo real no feed. | Validação de campos obrigatórios e rejeição de ofertas com salários abaixo do mínimo legal. | `index.html` (L537-540), `app.js` (L328-330) |
| **F25** | Carteira Digital | Visualizador da Carteira Digital do Egresso | Exibição gráfica oficial do documento de identidade com brasão do ES, foto com selo de verificação, CPF mascarado, registro SEJUS (`ES-2026-XXXXXX`) e data de validade. | `egresso_id` autenticado. | Cartão digital interativo com efeito glassmorphism e dados homologados. | Exibe aviso de pendência documental caso o registro prisional não esteja regularizado. | `ORIGINAL_REQUEST.md` (R1, R3), `index.html` (L701-763) |
| **F26** | Carteira Digital | Emissão de Carteira Digital em PDF Oficial | Geração de documento PDF em alta resolução assinado digitalmente pelo Governo do Estado do Espírito Santo (Dompdf), contendo layout frente/verso e certificado. | Requisição de download com identificador do egresso. | Arquivo binário PDF (`application/pdf`) disponibilizado para download e impressão. | Em caso de erro na compilação do PDF, retorna mensagem com código de erro e loga na auditoria. | `ORIGINAL_REQUEST.md` (R1, Critério 4), `index.html` (L756-758) |
| **F27** | Carteira Digital | QR Code Criptográfico Assinado (HMAC-SHA256) | Geração de QR Code contendo token criptográfico com assinatura da SEJUS para validação pública e combate a falsificações em todo o território capixaba. | Payload do documento (`id`, `cpf_mask`, `cod_registro`, `timestamp`, `expiracao`). | Imagem do QR Code em Base64 / SVG inserida no PDF e na tela da carteira. | Qualquer alteração de 1 bit no payload invalida a assinatura criptográfica na checagem pública. | `ORIGINAL_REQUEST.md` (R1, Critério 4), `TR` (Item 3.1 `e`, `i`) |
| **F28** | Carteira Digital | Página Pública de Validação do QR Code | Rota pública (`/validar-documento?doc={id}&sig={signature}`) acessível por qualquer cidadão ou autoridade policial para verificar a autenticidade do documento. | Parâmetros GET `doc` (UUID) e `sig` (HMAC Hash). | Página responsiva informando: "Documento Oficial Válido", nome, data de emissão e órgão emissor. | Exibe banner vermelho de "DOCUMENTO INVÁLIDO OU ADULTERADO" caso a assinatura não coincida. | `ORIGINAL_REQUEST.md` (Critério 4), `index.html` (L759-762) |
| **F29** | Carteira Digital | Solicitação de 2ª Via de Documentação Básica | Módulo de solicitação gratuita de 2ª via de RG/CIN (Polícia Científica), Certidão de Nascimento/Casamento (Defensoria), Título de Eleitor (TRE) e Certidão de Execução Penal. | Seleção do documento desejado, município para retirada e telefone/WhatsApp de contato. | Registro do pedido, encaminhamento para o órgão emissor e notificação com protocolo. | Alerta se o usuário já possuir solicitação em andamento para o mesmo documento. | `DOCUMENTO_EXECUTIVO.md` (Seção 2.D), `index.html` (L767-810) |
| **F30** | Mapeamento Territorial | Base Geoespacial dos 78 Municípios (PostGIS) | Tabela espacial com coordenadas geográficas, microrregiões do IJSN, contagem de egressos e sinalização dos 4 municípios com escritório físico vs 74 municípios remotos. | Código IBGE ou coordenadas do usuário. | Objetos espaciais GeoJSON com atributos demográficos e de atendimento. | Fallback para coordenadas da sede municipal caso a geolocalização do usuário esteja indisponível. | `ORIGINAL_REQUEST.md` (R1), `TR` (Item 3.1 `n`), `architecture_survey.md` |
| **F31** | Mapeamento Territorial | Painel Interativo de Mapeamento dos Municípios | Interface dinâmica com seleção de municípios por botões / mapa interativo, exibindo estatísticas locais e status de cobertura (Físico vs Remoto Conecta Egresso). | Clique do usuário no município desejado. | Atualização instantânea do painel de detalhes municipais (`#muniDetailsPanel`). | Nenhuma quebra de interface caso um município ainda não possua atendimentos registrados (exibe zero). | `ORIGINAL_REQUEST.md` (R3), `index.html` (L820-851), `app.js` (L279-293) |
| **F32** | Mapeamento Territorial | Exibição da Rede Socioassistencial Local | Apresentação dos equipamentos de apoio no município selecionado: CRAS (Bolsa Capixaba / Cesta Alimento), Casa do Cidadão, CAPS (Saúde Mental) e SINE (Vagas locais). | `municipio_id` selecionado. | Lista estruturada de órgãos parceiros locais com endereços e serviços oferecidos. | Exibe orientação de atendimento no polo regional mais próximo caso o município não possua CAPS próprio. | `DOCUMENTO_EXECUTIVO.md` (Seção 2.E), `index.html` (L873-880) |
| **F33** | Mapeamento Territorial | Encaminhamento Inteligente por Proximidade | Algoritmo geoespacial que calcula a distância euclidiana/rodoviária (`ST_Distance`) entre a localização do egresso e a rede de apoio, sugerindo o ponto mais próximo. | Coordenadas GPS do usuário (`lat`, `lng`) mediante consentimento. | Sugestão automática do CRAS/SINE mais próximo com estimativa de tempo e rota. | Se o egresso negar acesso ao GPS, utiliza o município informado em seu cadastro residencial. | `TR` (Item 3.1 `n`), `index.html` (L881-884) |
| **F34** | Dashboard & KPIs | Hero Banner & Atalhos Operacionais | Cabeçalho executivo institucional com status em tempo real da plataforma SEJUS e botões de atalho (*"Iniciar Atendimento Remoto"*, *"Exportar Relatório Sintético"*). | Ações do usuário. | Transição suave de tela para a fila de atendimento ou modal de exportação. | Comportamento não destrutivo se acionado repetidamente. | `index.html` (L167-187), `app.js` (L36-59) |
| **F35** | Dashboard & KPIs | Card KPI: Total de Egressos Cadastrados | Indicador numérico do volume de egressos atendidos no Estado, comparado com a meta populacional de 108.000 pessoas e taxa de crescimento mensal (+12.4%). | Dados agregados da tabela `egressos`. | Número formatado no padrão brasileiro (`14.850`), percentual e barra de progresso visual. | Exibe zero caso a base de dados esteja em estado inicial pós-migração sem erros de divisão por zero. | `ORIGINAL_REQUEST.md` (Critério 6), `index.html` (L192-204) |
| **F36** | Dashboard & KPIs | Card KPI: Atendimentos Remotos Realizados | Contagem consolidada de atendimentos psicossociais, jurídicos e documentais executados via plataforma, com índice de satisfação (98.4%). | Agregação da tabela `prontuario_atendimentos`. | Valor formatado (`32.410`), índice de satisfação e barra de progresso. | Trata valores nulos retornando contadores zerados. | `ORIGINAL_REQUEST.md` (Critério 6), `index.html` (L205-217) |
| **F37** | Dashboard & KPIs | Card KPI: Encaminhamentos para Emprego | Total de egressos encaminhados para empresas parceiras com percentual de contratação efetiva (76%). | Agregação da tabela `candidaturas`. | Número formatado (`4.120`), taxa de efetividade de contratação e indicador visual. | Cálculo seguro prevenindo divisão por zero em períodos sem encaminhamentos. | `DOCUMENTO_EXECUTIVO.md` (Seção 2.A), `index.html` (L218-230) |
| **F38** | Dashboard & KPIs | Card KPI: Redução da Reincidência Criminal | Indicador estratégico da diminuição da reincidência entre egressos acompanhados pela plataforma (-34.2%), conforme metas do CPSI 2026. | Comparativo histórico parametrizado. | Valor percentual em destaque âmbar/verde com indicativo de tendência de queda. | Mensagem explicativa de dados preliminares caso a amostra temporal seja inferior a 6 meses. | `ORIGINAL_REQUEST.md` (Critério 6), `TR` (Item 3.2 `b`), `index.html` (L231-243) |
| **F39** | Dashboard & KPIs | Gráfico de Barras de Atendimentos por Município | Renderização visual (HTML5 Canvas / Chart.js) da evolução dos atendimentos nos municípios polo (Vitória, Serra, Vila Velha, Cariacica, Linhares, Cachoeiro, Colatina, São Mateus). | Dados agregados por município e filtro regional (Grande Vitória, Norte, Sul, Serrana). | Gráfico de barras com eixos numéricos, rótulos e animação de carregamento. | Redimensionamento reativo automático no evento `window.resize` sem quebra de canvas. | `index.html` (L249-270), `app.js` (L140-204) |
| **F40** | Dashboard & KPIs | Gráfico Donut de Desempenho da Reintegração | Gráfico em formato de rosca detalhando a proporção das ações de acolhimento: Emprego (42%), Capacitação (28%), Psicossocial (18%) e Documentação (12%). | Dados percentuais calculados a partir dos encaminhamentos registrados. | Gráfico donut estilizado com legenda colorida à direita e percentual central de 100% efetividade. | Fallback para renderização em estado neutro caso não haja dados no período. | `index.html` (L271-285), `app.js` (L206-275) |
| **F41** | Dashboard & KPIs | Mini-Mapa Territorial com Indicadores Regionais | Miniatura esquemática do mapa do ES com pontos interativos e legenda lateral com divisão percentual (Grande Vitória 62%, Norte 18%, Sul 12%, Serrana 8%). | Coordenadas percentuais dos polos e contagens regionais. | Pontos com efeito hover e atalho para o mapa completo. | Layout fluido que se adapta a telas pequenas sem sobrepor legendas. | `index.html` (L290-325) |
| **F42** | Dashboard & KPIs | Feed de Atividades Recentes em Tempo Real | Lista dos últimos registros executados na plataforma com carimbo de horário, técnico, município e status pills coloridos. | Registros mais recentes de `prontuario_atendimentos` e `candidaturas`. | Lista cronológica com ícones semânticos (Vídeo, Emprego, Documento, Carteira). | Exibição de mensagem "Nenhuma atividade recente registrada" caso a tabela esteja vazia. | `index.html` (L328-380) |
| **F43** | Relatórios SEJUS | Tabela Sintética Municipal de Desempenho | Tabela consolidada com colunas: Município, Tipo de Atendimento (Físico vs Remoto), Egressos Atendidos, Encaminhamentos, Redução de Reincidência e Status de Cobertura. | Parâmetros de filtro (Ano, Região SEJUS, Tipo de Atendimento). | Tabela responsiva com formatação numérica e badges semânticos. | Paginação automática e ordenação por qualquer coluna. | `ORIGINAL_REQUEST.md` (Critério 6), `index.html` (L1018-1100) |
| **F44** | Relatórios SEJUS | Exportação de Relatórios Gerenciais em PDF | Compilação e download de relatório analítico executivo em formato PDF com cabeçalho oficial SEJUS/SEGER, assinatura digital e tabelas agregadas. | Filtros selecionados pelo gestor. | Arquivo binário PDF gerado para download imediato. | Notificação clara caso o tempo de geração do relatório exceda o timeout padrão (fila assíncrona). | `TR` (Item 3.1 `k`), `index.html` (L981-984) |
| **F45** | Acessibilidade | Modo Alto Contraste (High Contrast) | Alternância de tema para fundo preto absoluto (`#000000`), cartões escuros (`#121212`), textos em branco (`#ffffff`) e acentos ciano brilhante (`#00ffff`) em conformidade com WCAG 2.1 AAA. | Clique no botão "👁️ Alto Contraste" ou preferência do sistema (`prefers-contrast: more`). | Classe `high-contrast` aplicada no `<body>` e persistência da preferência no `localStorage`. | Garantia de legibilidade de todos os textos e botões sem perda de contraste em nenhum elemento. | `ORIGINAL_REQUEST.md` (R3), `index.html` (L51-53), `styles.css` (L56-67), `app.js` (L108-110) |
| **F46** | Acessibilidade | Ampliação Dinâmica de Fonte (Escala A+) | Aumento proporcional de 18% em todos os textos da interface através da variável CSS `--font-scale: 1.18`. | Clique no botão "A+" na barra de acessibilidade. | Redimensionamento instantâneo de fontes mantendo alinhamento e sem quebras de layout. | Toggle para retornar ao tamanho original (`--font-scale: 1`) no segundo clique. | `ORIGINAL_REQUEST.md` (R3), `index.html` (L54-56), `styles.css` (L52, L86), `app.js` (L112-124) |
| **F47** | Acessibilidade | Modo Linguagem Simplificada (Baixo Letramento) | Ajuste textual e tipográfico voltado para pessoas egressas e familiares com baixo letramento digital, substituindo termos técnicos e aumentando espaçamentos (`letter-spacing: 0.02em`). | Clique no botão "💬 Linguagem Fácil". | Classe `simplified-lang` ativada, textos explicativos simplificados e modais com ícones grandes e diretos. | Alerta explicativo em linguagem acessível informando a ativação do modo. | `ORIGINAL_REQUEST.md` (R3), `TR` (Item 3.1 `b`), `styles.css` (L69-72), `app.js` (L126-129) |
| **F48** | Interface & Shell | Barra Superior Fixa e Logotipo Institucional ES | Cabeçalho sticky com logotipo oficial do Estado do Espírito Santo (badge tricolor rosa/branco/azul), título da plataforma e busca global. | Renderização inicial da página. | Elemento visual estável no topo da tela com acesso rápido a todas as funções globais. | Comportamento responsivo recolhendo a busca global em telas menores que 768px. | `index.html` (L21-47), `styles.css` (L97-175) |
| **F49** | Interface & Shell | Menu Lateral Retrátil (Collapsible Sidebar) | Barra de navegação lateral com 8 links organizados em categorias (*Menu Principal* e *Gestão & Governança*), botão de recolhimento e selo institucional SEJUS/SEGER. | Clique no botão hamburguer `#sidebarToggleBtn` ou clique nos itens de menu. | Sidebar transiciona suavemente de 270px para 70px (`.sidebar.collapsed`) mantendo ícones visíveis. | Mantém o estado recolhido/expandido sincronizado e fecha automaticamente em mobile ao clicar fora. | `index.html` (L90-157), `styles.css` (L306-436), `app.js` (L16-34) |
| **F50** | Infraestrutura | Orquestração Docker Compose Unificada | Arquivo `docker-compose.yml` orquestrando 6 serviços essenciais: Nginx, PHP 8.3-FPM/Laravel 11, Python FastAPI WebRTC, PostgreSQL 16 (PostGIS/pgcrypto), Redis 7 e Coturn TURN. | Execução do comando `docker compose up -d`. | Todos os contêineres inicializados, saudáveis (`healthy`) e comunicando-se através da rede interna `conecta_net`. | Logs descritivos em caso de falha de inicialização em qualquer dependência de banco ou redis. | `ORIGINAL_REQUEST.md` (R4, Critério 7), `architecture_survey.md` (Seção 5) |

---

## 3. Especificações Detalhadas dos Subsistemas de Domínio

### 3.1 Subsistema de Autenticação & Matriz de Permissões RBAC

A plataforma CONECTA EGRESSO adota um modelo estrito de Role-Based Access Control (RBAC) com suporte à autenticação federada governamental (Acesso Cidadão / Gov.br) e perfis locais de contingência.

```
                  ┌──────────────────────────────────────────────┐
                  │          PROVEDOR DE IDENTIDADE              │
                  │   (Gov.br / Acesso Cidadão PRODEST - OIDC)   │
                  └──────────────────────┬───────────────────────┘
                                         │ Token JWT / Claims
                                         ▼
                  ┌──────────────────────────────────────────────┐
                  │            LARAVEL 11 CORE AUTH              │
                  │           (EnsureRole Middleware)            │
                  └───────┬──────────────┬──────────────┬────────┘
                          │              │              │
        role == 'gestor'  │              │role=='tecnico│ role == 'egresso'
                          ▼              ▼              ▼
                   ┌────────────┐ ┌────────────┐ ┌────────────┐
                   │   GESTOR   │ │  TÉCNICO   │ │  EGRESSO   │
                   │   SEJUS    │ │ ESCRITÓRIO │ │ / FAMILIAR │
                   └────────────┘ └────────────┘ └────────────┘
```

#### Matriz de Acesso Formal por Rota e Perfil

| Módulo / Rota | Ação | Perfil Gestor SEJUS | Perfil Técnico Escritório Social | Perfil Egresso / Familiar |
|---|---|---|---|---|
| `/dashboard` | Visualizar KPIs consolidados dos 78 municípios | **Total** (Estatísticas globais descaracterizadas) | **Operacional** (Fila e atendimentos locais) | **Simplificado** (Status dos seus serviços) |
| `/atendimento` | Fila de videochamadas & Acolhimento | **Somente Leitura** (Métricas de espera) | **Total** (Iniciar chamada, prescrever, evoluir) | **Próprio** (Entrar na sala quando chamado) |
| `/prontuario/{id}` | Visualização do Prontuário | **Restrito / Mascarado** (Com justificativa em auditoria) | **Total** (Visualização e evolução técnica) | **Próprio** (Apenas o seu próprio prontuário) |
| `/prontuario/{id}/evolucoes` | Inserir nova evolução psicossocial | ❌ Não Autorizado (403) | **Total** (Com carimbo de data, hora e CPF) | ❌ Não Autorizado (403) |
| `/oportunidades` | Painel de Vagas & Cursos | **Total** (Cadastrar, editar vagas e relatórios) | **Operacional** (Encaminhar atendido) | **Visualizar & Candidatar-se** |
| `/carteira-digital` | Visualização da Carteira Digital | **Consulta de Validação** | **Consulta de Validação** | **Total** (Visualizar, baixar PDF, QR Code) |
| `/carteira-digital/pdf` | Baixar PDF Oficial com QR Code | ❌ Acesso Restrito | **Com autorização do egresso** | **Total** (Download direto do próprio documento) |
| `/validar-documento` | Consulta pública de autenticidade QR Code | **Público** | **Público** | **Público** |
| `/geolocalizacao` | Mapeamento dos 78 Municípios e PostGIS | **Total** (Mapeamento de demanda e gestão) | **Total** (Consulta de rede de apoio) | **Consulta do seu município** |
| `/relatorios` | Relatórios sintéticos e reincidência | **Total** (Exportar PDFs e gráficos) | **Parcial** (Relatório da sua unidade) | ❌ Não Autorizado (403) |
| `/lgpd` | Auditoria e Níveis de Acesso | **Total** (Trilha de auditoria e conformidade) | **Consulta de Políticas** | **Consulta dos seus dados (Art. 18 LGPD)** |

---

### 3.2 Subsistema de Governança LGPD & Trilha de Auditoria Imutável

Conforme o Artigo 6º da Lei Geral de Proteção de Dados Pessoais (Lei Federal nº 13.709/2018) e o Item 3.1 `d` e 3.5 do Termo de Referência SEJUS:
1. **Princípio da Necessidade e Minimização:** Dados sensíveis de antecedentes e prontuários psicossociais são estritamente restritos à equipe multiprofissional (Assistentes Sociais e Psicólogos).
2. **Criptografia Simétrica de Ponta a Ponta no Banco:** Colunas sensíveis (`cpf_encrypted`, `filiacao_mae_encrypted`, `rg_encrypted`, `endereco_encrypted`, `telefone_encrypted`, `evolucao_texto_encrypted`) utilizam criptografia AES-256 via extensão `pgcrypto` ou rotinas do Laravel `Crypt::encryptString()`.
3. **Indexação Cega (Blind Index):** A busca exata de registros por CPF utiliza um hash determinístico `cpf_hash = SHA-256(cpf + PEPPER)`, impedindo a exposição do CPF em texto claro nos índices do PostgreSQL.
4. **Imutabilidade Indelével da Tabela `prontuario_audit_logs`:**
   - Cada consulta (`VIEW`), criação (`CREATE`), atualização (`UPDATE`), exportação (`EXPORT_PDF`) ou anonimização (`ANONYMIZE`) gera um registro obrigatório contendo: `prontuario_id`, `user_id`, `ip_address`, `user_agent`, `payload_hash` e `created_at`.
   - Triggers / Rules do PostgreSQL impedem qualquer operação de `UPDATE` ou `DELETE` nesta tabela:
     ```sql
     CREATE RULE prontuario_audit_logs_no_update AS ON UPDATE TO prontuario_audit_logs DO INSTEAD NOTHING;
     CREATE RULE prontuario_audit_logs_no_delete AS ON DELETE TO prontuario_audit_logs DO INSTEAD NOTHING;
     ```

---

### 3.3 Subsistema de Videochamada WebRTC & Webhook Lifecycle

O atendimento remoto é executado através da cooperação entre o frontend Vue 3, o microsserviço Python FastAPI e o backend Laravel 11.

```
┌──────────────┐          ┌───────────────────┐          ┌──────────────┐          ┌──────────────┐
│  NAVEGADOR   │          │  PYTHON FASTAPI   │          │ LARAVEL CORE │          │   COTURN     │
│ (Técnico /   │          │  (Signaling WSS)  │          │ (API & BD)   │          │ (STUN/TURN)  │
│   Egresso)   │          │                   │          │              │          │              │
└──────┬───────┘          └─────────┬─────────┘          └──────┬───────┘          └──────┬───────┘
       │                            │                           │                         │
       │ 1. Solicita Token JWT Sala │                           │                         │
       ├────────────────────────────┼──────────────────────────►│                         │
       │ 2. Retorna JWT + Cred. TURN│                           │                         │
       │◄───────────────────────────┼───────────────────────────┤                         │
       │                            │                           │                         │
       │ 3. Conexão WSS /ws/room/{id}?token=JWT                 │                         │
       ├───────────────────────────►│                           │                         │
       │ 4. WSS: 'join'             │                           │                         │
       ├───────────────────────────►│                           │                         │
       │                            │ 5. Webhook: 'participant.joined'                    │
       │                            ├──────────────────────────►│                         │
       │ 6. Troca SDP Offer/Answer  │                           │                         │
       │◄──────────────────────────►│                           │                         │
       │ 7. Troca ICE Candidates    │                           │                         │
       │◄──────────────────────────►│                           │                         │
       │                            │                           │                         │
       │ 8. Conexão WebRTC P2P Direta (ou Relé via COTURN)      │                         │
       │◄═══════════════════════════╪═══════════════════════════╪════════════════════════►│
       │                            │                           │                         │
       │ 9. Telemetria (MOS/Loss/RTT)                           │                         │
       ├───────────────────────────►│                           │                         │
       │                            │                           │                         │
       │ 10. Encerramento 'leave'   │                           │                         │
       ├───────────────────────────►│                           │                         │
       │                            │ 11. Webhook: 'call.ended' (Duração, MOS, Bytes)     │
       │                            ├──────────────────────────►│                         │
       │                            │                           │ 12. Grava Atendimento  │
       │                            │                           │     no Prontuário Único │
       │                            │                           │◄────────────────────────┤
```

#### Especificação dos Webhooks FastAPI -> Laravel (`POST /api/webrtc/webhook`)

- **Cabeçalho de Autenticação Obrigatório:** `X-Signature-256: HMAC_SHA256(raw_body, WEBHOOK_SECRET)`
- **Payload do Evento `call.ended`:**
```json
{
  "event": "call.ended",
  "timestamp": "2026-08-17T10:04:30Z",
  "data": {
    "room_id": "8f7e2a1b-3c4d-5e6f-7a8b-9c0d1e2f3a4b",
    "room_code": "ATD-SM-2026-8910",
    "prontuario_id": "550e8400-e29b-41d4-a716-446655440000",
    "tecnico_id": "c3a1d9e2-4b5f-6a7c-8d9e-0f1a2b3c4d5e",
    "egresso_id": "a1b2c3d4-e5f6-7a8b-9c0d-1e2f3a4b5c6d",
    "started_at": "2026-08-17T09:55:48Z",
    "ended_at": "2026-08-17T10:04:30Z",
    "duration_seconds": 522,
    "quality_metrics": {
      "avg_mos_score": 4.82,
      "packet_loss_pct": 0.45,
      "avg_rtt_ms": 38.2,
      "bytes_transferred": 48920150,
      "codec_video": "VP8",
      "codec_audio": "opus"
    },
    "hangup_reason": "technician_ended"
  }
}
```

---

### 3.4 Subsistema da Carteira Digital (PDF Oficial & QR Code Criptográfico)

A Carteira Digital do Egresso consolida o registro oficial perante os órgãos de fiscalização e oportunidades parceiras:
1. **Emissão de PDF com Layout Institucional Oficial:**
   - Formato de impressão padrão cartão funcional frente e verso (85.6mm x 53.98mm) e página A4 com autenticação digital.
   - Elementos presentes: Brasão Oficial do Estado do Espírito Santo, denominação *"SEJUS • ESCRITÓRIO SOCIAL"*, foto do egresso com tag *"✓ Verificado"*, Nome Completo, CPF mascarado, Número de Registro SEJUS (`ES-2026-XXXXXX`), Município de Residência, Data de Emissão e Data de Validade (1 ano).
2. **Algoritmo de Assinatura do QR Code:**
   - Payload JSON canônico:
     ```json
     {
       "doc_id": "UUID-CARTEIRA",
       "cpf_masked": "***.192.830-**",
       "registro_sejus": "ES-2026-948102",
       "nome": "LUCAS SANTOS",
       "municipio": "São Mateus/ES",
       "emitido_em": "2026-08-17",
       "valido_ate": "2027-08-17",
       "lei_base": "Lei 182/2021"
     }
     ```
   - Assinatura: `HMAC_SHA256(canonical_json, CARTEIRA_SIGNING_KEY)`
   - URL de validação codificada no QR Code: `https://conectaegresso.es.gov.br/validar-documento?doc={doc_id}&sig={signature}`.

---

### 3.5 Mapeamento Territorial dos 78 Municípios do Espírito Santo

O sistema cadastra e gerencia integralmente todos os 78 municípios capixabas, agrupados por macrorregiões e microrregiões oficiais do IJSN:

```
                            ESTADO DO ESPÍRITO SANTO (78 Municípios)
    ┌───────────────────────────────────────────────────────────────────────┐
    │  4 MUNICÍPIOS COM ESCRITÓRIO SOCIAL FÍSICO (Grande Vitória):          │
    │  - Vitória (Sede), Serra, Vila Velha, Cariacica                       │
    ├───────────────────────────────────────────────────────────────────────┤
    │  74 MUNICÍPIOS DO INTERIOR COM ATENDIMENTO 100% REMOTO:               │
    │  - Norte / Rio Doce (Linhares, São Mateus, Aracruz, etc.)             │
    │  - Noroeste (Colatina, Nova Venécia, Barra de São Francisco, etc.)    │
    │  - Central Serrana / Sudoeste (Afonso Cláudio, Domingos Martins, etc.)│
    │  - Sul / Litoral Sul / Caparaó (Cachoeiro, Alegre, Itapemirim, etc.)  │
    └───────────────────────────────────────────────────────────────────────┘
```

- **Inteligência Geoespacial PostGIS:** Consultas por raio (`ST_DWithin`) e determinação de proximidade (`ST_Distance`) para direcionamento automático de demandas para os equipamentos de acolhimento (CRAS, CREAS, SINE e CAPS) mais próximos da residência do egresso.

---

### 3.6 Painel de Oportunidades & Qualificação Profissional

- **Vagas de Trabalho:** Vagas inclusivas com filtros combinados (Município, Categoria, Escolaridade, Faixa Salarial).
- **Cursos Gratuitos:** Parcerias com SENAI, Findes, IFES, FAETEC, com suporte a bolsas de estudo e transporte.
- **Ações Afirmativas:** Empresas parceiras credenciadas sob o programa *"Empresa Amiga da Reintegração"* e *"Inclusão no Campo SEJUS"*.

---

### 3.7 Acessibilidade Universal Tripla (WCAG 2.1 AAA)

1. **Modo Alto Contraste (`high-contrast`):** Paleta preto total (`#000000`) com acentos em ciano elétrico (`#00ffff`), garantindo taxa de contraste superior a 7:1.
2. **Escala de Fonte Dinâmica (`--font-scale: 1.18`):** Aumento proporcional sem truncamento ou overflow de textos.
3. **Modo Linguagem Simplificada (`simplified-lang`):** Textos adaptados para pessoas em vulnerabilidade e baixo letramento digital, vocabulário simplificado e ícones visuais ampliados.

---

## 4. Modelos de Dados e Estrutura Relacional (PostgreSQL 16)

```
┌──────────────────┐       1:1       ┌──────────────────┐       1:N       ┌────────────────────────┐
│      users       ├────────────────►│     egressos     ├────────────────►│      candidaturas      │
│  (Auth & RBAC)   │                 │ (Dados Pessoais) │                 │  (Vagas / Cursos)      │
└────────┬─────────┘                 └────────┬─────────┘                 └────────────────────────┘
         │                                    │
         │                                    │ 1:1
         │                                    ▼
         │                           ┌──────────────────┐       1:N       ┌────────────────────────┐
         │                           │   prontuarios    ├────────────────►│ prontuario_atendimentos│
         │                           │  (Número Único)  │                 │ (Evoluções & Pareceres)│
         │                           └────────┬─────────┘                 └───────────┬────────────┘
         │                                    │                                       │
         │ 1:N                                │ 1:N                                   │ 1:1
         ▼                                    ▼                                       ▼
┌──────────────────┐                 ┌──────────────────┐                 ┌────────────────────────┐
│   video_rooms    │                 │prontuario_audit_ │                 │     video_sessions     │
│ (Salas / Espera) │                 │      logs        │                 │ (Duração, Telemetria)  │
└──────────────────┘                 └──────────────────┘                 └────────────────────────┘
```

### Entidades do Sistema:
1. `municipios_es` (78 municípios, código IBGE, coordenadas, geometria PostGIS, contadores da rede CRAS/CREAS/SINE).
2. `users` (id UUID, nome, e-mail, senha bcrypt, `cpf_hash`, `cpf_encrypted`, `role`, `acesso_cidadao_sub`).
3. `egressos` (id UUID, `user_id`, nome completo, data nascimento, `filiacao_mae_encrypted`, `rg_encrypted`, `municipio_residencia_id`, `geom`, escolaridade, status prisional, vulnerabilidades JSONB).
4. `prontuarios` (id UUID, `egresso_id`, `numero_prontuario`, `status_acompanhamento`, `tecnico_responsavel_id`).
5. `prontuario_atendimentos` (id UUID, `prontuario_id`, `tecnico_id`, `tipo_atendimento`, `categoria`, `evolucao_texto_encrypted`, `duracao_minutos`, `data_hora_atendimento`, `video_session_id`).
6. `prontuario_audit_logs` (id BIGSERIAL, `prontuario_id`, `user_id`, `acao`, `ip_address`, `user_agent`, `payload_hash`, `created_at` — Tabela Imutável).
7. `vagas` (id UUID, empresa parceira, título, descrição, categoria, `municipio_id`, salário, regime, vagas totais, ativa).
8. `cursos` (id UUID, instituição, nome curso, modalidade, carga horária, `municipio_id`, vagas disponíveis, ativo).
9. `candidaturas` (id UUID, `egresso_id`, `vaga_id`, `curso_id`, `status`, `tecnico_encaminhador_id`, data candidatura).
10. `carteiras_digitais` (id UUID, `egresso_id`, `codigo_autenticacao`, `qr_payload_signed`, `validade_ate`, `status`, `dados_snapshot` JSONB).
11. `video_rooms` (id UUID, `room_code`, `prontuario_id`, `egresso_id`, `tecnico_id`, `municipio_id`, `status`, `prioridade`).
12. `video_sessions` (id UUID, `room_id`, `started_at`, `ended_at`, `duracao_segundos`, `qualidade_media_score`, `bytes_transferred`, `packets_lost_pct`, `session_metadata` JSONB).

---

## 5. Tabela de Casos de Borda e Resiliência (Edge Cases)

| # | Funcionalidade | Cenário / Entrada Extrema | Comportamento Esperado do Sistema |
|---|---|---|---|
| **E01** | Videochamada WebRTC | Perda repentina de sinal 4G/Wi-Fi no smartphone do egresso durante o atendimento | O cliente exibe mensagem de reconexão automática com buffer de 30s. Se a conexão for restabelecida, reassocia os tracks sem reiniciar a sala; se expirar, encerra com motivo `network_timeout` e registra a duração parcial no prontuário. |
| **E02** | Videochamada WebRTC | Conexão móvel sob Carrier-Grade NAT (CGNAT) estrito impedindo conexão P2P direta | O navegador recebe candidatos do Coturn TURN Relay autenticado com credenciais efêmeras e comuta o tráfego para relé TURN via porta 3478/5349 sem falha na chamada. |
| **E03** | Fila de Atendimento | Técnico tenta atender egresso que já está em chamada com outro atendente | O sistema bloqueia a ação, retorna aviso visual "Egresso já está em atendimento com o Técnico [Nome]" e atualiza a fila em tempo real. |
| **E04** | Carteira Digital | Validação pública de QR Code gerado há mais de 1 ano ou com dados adulterados | A página de validação computa a assinatura HMAC, detecta divergência ou expiração da data de validade e exibe status vermelho "DOCUMENTO EXPIRADO OU INVÁLIDO". |
| **E05** | LGPD & Auditoria | Tentativa de injeção SQL ou comando manual de `DELETE`/`UPDATE` na tabela de auditoria | A regra do PostgreSQL (`prontuario_audit_logs_no_delete`) intercepta o comando e executa `INSTEAD NOTHING`, preservando a integridade absoluta da trilha. |
| **E06** | Mapeamento 78 Municípios | Egresso residente em município do interior sem unidade de saúde mental (CAPS) local | A plataforma consulta a tabela espacial PostGIS e encaminha automaticamente a demanda para o polo regional socioassistencial mais próximo (ex: São Mateus ou Colatina). |
| **E07** | Oportunidades & Vagas | Candidatura de egresso sem escolaridade formal para vaga que não exige comprovação | O sistema valida a compatibilidade com a política afirmativa e conclui o encaminhamento sem bloquear por falta de diploma escolar. |
| **E08** | Acessibilidade | Usuário alterna para Alto Contraste e Fonte Ampliada simultaneamente em smartphone pequeno (360px de largura) | O layout TailwindCSS reorganiza o header e a barra de ferramentas em visualização empilhada sem quebra de elementos ou scroll horizontal indesejado. |
| **E09** | Comunicação Inter-serviços | Microsserviço Python tenta disparar webhook para o Laravel enquanto o PHP-FPM está ocupado/reiniciando | O FastAPI captura a falha de conexão HTTP, enfileira o payload do webhook no Redis e dispara retentativas assíncronas com backoff exponencial. |
| **E10** | Prontuário Único | Tentativa de cadastro simultâneo de dois prontuários para o mesmo CPF | O índice único em `cpf_hash` impede a duplicidade no banco e retorna erro 409 amigável com redirecionamento para o prontuário já existente. |

---

## 6. Rastreabilidade com os Critérios de Aceite do Projeto

| Critério de Aceite (`ORIGINAL_REQUEST.md`) | Requisitos Atendidos | Módulos & Componentes Responsáveis | Status de Validação na Especificação |
|---|---|---|---|
| **Login funcional com alternância entre os 3 perfis com permissões estritas** | R1, R3, F01-F06 | `EnsureRole.php`, `RoleSwitcher.vue`, `GovBrAcessoCidadaoController.php` | **100% Especificado e Mapeado** |
| **Trilha de auditoria gravando usuário, timestamp e ação em todas as alterações** | R1, F07-F13 | `AuditLgpdAccess.php`, `prontuario_audit_logs` (PostgreSQL Rule) | **100% Especificado e Mapeado** |
| **Dois usuários conectados em sala privada de vídeo com áudio/vídeo bidirecionais** | R2, F14-F18 | `signaling/sdp_relay.py`, `WebRtcVideoRoom.vue`, Coturn TURN Server | **100% Especificado e Mapeado** |
| **Encerramento da chamada registra automaticamente duração e metadados no prontuário** | R2, F19 | `laravel_dispatcher.py`, `WebRtcWebhookController.php`, `video_sessions` | **100% Especificado e Mapeado** |
| **Emissão de Carteira Digital em PDF com QR Code legível que valida o registro** | R1, F25-F28 | `CarteiraPdfService.php`, `QrCodeSignerService.php`, Dompdf | **100% Especificado e Mapeado** |
| **Filtro funcional de vagas de trabalho e cursos por município do Espírito Santo** | R1, F20-F24 | `OportunidadeController.php`, `OpportunityFilter.vue`, `vagas`/`cursos` | **100% Especificado e Mapeado** |
| **Dashboard de KPIs gerando métricas agregadas por município** | R1, R3, F30-F44 | `DashboardController.php`, `KpiCard.vue`, `ChartBar.vue`, `EsMapSvg.vue` | **100% Especificado e Mapeado** |
| **Execução unificada de todos os serviços através de `docker compose up -d`** | R4, F50 | `docker-compose.yml` (Nginx, PHP-FPM, FastAPI, Postgres, Redis, Coturn) | **100% Especificado e Mapeado** |
