# Conecta Egresso (SEJUS/ES)

> **Plataforma Digital de Acolhimento, Gestão Social e Reintegração para Egressos do Sistema Prisional do Espírito Santo.**  
> Desenvolvido em conformidade com o **Edital CPSI Nº 010/2026** (SEJUS/SEGER - Governo do Estado do Espírito Santo).

---

## 📌 Visão Geral do Sistema

O **Conecta Egresso** é uma solução completa para descentralizar e garantir a continuidade dos serviços do Escritório Social em todos os **78 municípios capixabas**.

O projeto é estruturado em **duas camadas integradas**:

1. **Plataforma Web (Gestão e Atendimento Técnico):**
   - Ambiente administrativo acessado via navegador por **Gestores**, **Técnicos Sociais** e **Equipe de Suporte**.
   - Gerenciamento completo de prontuários eletrônicos, oportunidades de emprego, cursos, emissão de carteiras digitais e telemetria/KPIs de reincidência criminal.
   - Construído com **Laravel 11**, **Inertia.js**, **Vue 3** e **Tailwind CSS**.

2. **Camada de API Mobile (Backend para o Aplicativo Móvel):**
   - Conjunto de endpoints RESTful prontos e otimizados para consumo por **aplicativos móveis (iOS e Android)**.
   - **Autenticação por Tokens Bearer** (com hashing SHA-256 no banco de dados), desacoplada de cookies ou sessões de navegador.
   - **Controle de Dados Atualizados (Sincronização Delta / Incremental):** Endpoints com suporte a filtros temporais (`?since=` / `?updated_after=`) e carimbo autoritativo do servidor (`meta.server_timestamp`), permitindo que o aplicativo sincronize apenas o que mudou, economize dados móveis e funcione com cache local offline.
   - 📖 *Documentação completa de integração para a equipe do aplicativo disponível em:* [`MOBILE_API_SPEC.md`](./MOBILE_API_SPEC.md).

---

## 🚀 Principais Módulos e Recursos

* **Carteira Digital do Egresso:**
  * Emissão digital do documento oficial com QR Code assinado criptograficamente (HMAC-SHA256) para validação pública e rápida conferência de autenticidade.
  * Serviço de geração de PDF com resiliência tripla (microsserviço Node/Puppeteer, fallback local Dompdf e stream textual).
* **Prontuário Social Único & Linha do Tempo:**
  * Registro imutável de atendimentos, encaminhamentos e evoluções psicossociais com cadeia criptográfica auditável.
  * Isolamento estrito de acesso: egressos visualizam somente seu próprio histórico; técnicos e gestores têm controle por perfil.
* **Oportunidades de Emprego & Qualificação Profissional:**
  * Painel de vagas inclusivas com filtros de municípios, ações afirmativas para egressos e incentivos do programa Empresa Amiga.
  * Catálogo de cursos de capacitação técnica (IFES, SENAI) com filtros por modalidade EAD e benefício de bolsa auxílio.
* **Atendimento Remoto por Vídeo (WebRTC):**
  * Salas virtuais de videoconferência psicossocial com sinalização em tempo real e tokens de sessão temporários.
* **Mapeamento da Rede de Apoio nos 78 Municípios:**
  * Localização georreferenciada de unidades do CRAS, CREAS, SINE e CAPS em todo o Espírito Santo.
* **Segurança e Conformidade com a LGPD:**
  * Criptografia em repouso (AES-256-CBC) para campos sensíveis como CPF.
  * Mascaramento automático de identificadores nas respostas de API móvel (`192.***.***-78`).

---

## 🛠️ Tecnologias Utilizadas

* **Backend & API:** PHP 8.2+, Laravel 11, Eloquent ORM, SQLite / MySQL.
* **Frontend Web:** Vue 3 (Composition API, `<script setup>`), Inertia.js, Tailwind CSS 3.4, Lucide Icons.
* **Autenticação:** Dual-mode — Sessão Web (Gov.br / Acesso Cidadão / Credenciais) e Tokens Móveis Bearer RFC 6750.
* **Microsserviços:** Serviço de sinalização WebRTC e gerador de documentos PDF.
* **Testes & Qualidade:** PHPUnit com suítes de testes unitários, testes de integração, auditoria adversarial e verificação LGPD.

---

## ⚙️ Instalação e Execução Local

### Pré-requisitos
* PHP 8.2 ou superior (com extensões `pdo_sqlite` ou `pdo_mysql`, `mbstring`, `openssl`, `curl`)
* Composer
* Node.js (v18+) e NPM

### Passo a Passo

1. **Clonar o repositório:**
   ```bash
   git clone https://github.com/agticonsult/Sejus.git
   cd Sejus
   ```

2. **Instalar as dependências do backend:**
   ```bash
   composer install
   ```

3. **Instalar as dependências do frontend:**
   ```bash
   npm install
   npm run build
   ```

4. **Configurar as variáveis de ambiente:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Executar migrações e popular dados iniciais:**
   ```bash
   php artisan migrate --seed
   ```

6. **Iniciar o servidor de desenvolvimento:**
   ```bash
   php artisan serve
   ```
   Acesse a aplicação no navegador em: **[http://localhost:8000](http://localhost:8000)**.

---

## 🧪 Testes Automatizados

O projeto conta com uma cobertura rigorosa de testes cobrindo rotas web, APIs móveis, segurança LGPD e cenários adversariais:

```bash
php artisan test
```

### Resultados da Homologação
```text
Tests:    135 passed (768 assertions)
Duration: 27.60s
Status:   100% Sucesso (0 falhas)
```

---

## 📱 Guia de Integração para a Equipe Mobile

Se você faz parte da equipe responsável pelo desenvolvimento do aplicativo para celular (Android / iOS), consulte o arquivo de especificação técnica:

👉 **[Especificação Completa da API Mobile (MOBILE_API_SPEC.md)](./MOBILE_API_SPEC.md)**

Neste arquivo você encontrará:
- Ciclo de vida da autenticação (Login via e-mail ou CPF, cabeçalho `Authorization: Bearer <token>`, perfil e logout).
- Como funciona o protocolo de sincronização delta (`?since=YYYY-MM-DDTHH:MM:SSZ`).
- Schemas JSON de requisição e resposta para Carteira Digital, Vagas, Cursos e Prontuários.
- Tabela de códigos de erro HTTP e diretrizes de cache offline (SQLite local no dispositivo).

---

## 📄 Licença e Governança

Projeto desenvolvido para a **Secretaria de Estado da Justiça do Espírito Santo (SEJUS/ES)** e Secretaria de Gestão e Recursos Humanos (SEGER/ES) no âmbito do Edital CPSI Nº 010/2026.

