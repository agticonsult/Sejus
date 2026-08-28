# Original User Request

## 2026-08-18T13:04:39Z

Implementação de sistema de autenticação completo (Login/Logout), criação de usuário de suporte (Agile), gerenciamento de usuários, integração com o microsserviço Document Generator para PDFs, e Toasts reativos no Conecta Egresso (SEJUS/ES).

Working directory: d:\Agile\projeto dia 18
Integrity mode: development

## Requirements

### R1. Sistema de Notificações (Toasts)
- Substituir todas as chamadas nativas de `alert()` nos arquivos Vue (`Atendimento.vue`, `Carteira.vue`, `Oportunidades.vue`, `Relatorios.vue`, `SegurancaLgpd.vue`) por um componente elegante de Toasts posicionado no canto superior direito.
- Suportar estados de Sucesso, Erro, Alerta e Informação com cores adequadas, ícones/emojis e transições de entrada/saída suaves.

### R2. Geração de PDF via Document Generator API
- Integrar a geração da carteira digital em PDF com o microsserviço **Document Generator** (rodando localmente em `http://localhost:8080` com a API Key `token-secreto-dev`).
- No `CarteiraPdfService`, enviar a requisição `POST` com o template HTML compilado para a API externa e obter o PDF resultante.
- Manter o gerador local `Dompdf` como um fallback automático (graceful fallback) caso o microsserviço externo falhe ou esteja offline.
- Registrar a rota GET `/carteira/pdf` em `routes/web.php` e acoplar a este serviço. Se o usuário estiver deslogado localmente, usar o primeiro egresso como fallback para testes.

### R3. Autenticação Completa (Login & Logout na UI)
- Criar a página de login reativa (`Login.vue` ou semelhante) com a identidade do **Gov.br / Acesso Cidadão** e do Governo do Estado do Espírito Santo.
- Proteger rotas internas para exigir autenticação (com credenciais válidas).
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
- [ ] A geração consome a API do Document Generator em `localhost:8080`, com fallback local funcional.
