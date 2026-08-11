# MEMORANDO EXECUTIVO DE APRESENTAÇÃO DE SOLUÇÃO

**PARA:** Gestão Executiva / Liderança de Projeto  
**DE:** Equipe de Desenvolvimento de Soluções Inovadoras  
**DATA:** 11 de Agosto de 2026  
**ASSUNTO:** Apresentação da Interface e Protótipo Digital da Plataforma **"CONECTA EGRESSO"**  
**REFERÊNCIA:** Edital de Contratação Pública de Solução Inovadora (CPSI) Nº 010/2026 – SEJUS / SEGER  

---

## 1. SUMÁRIO EXECUTIVO

Apresentamos a proposta de interface gráfica, navegação e painel de controle (dashboard) da **Plataforma CONECTA EGRESSO**, desenvolvida em alinhamento rigoroso às diretrizes técnicas e operacionais estabelecidas no **Termo de Referência CPSI Nº 010/2026** da Secretaria de Estado da Justiça (SEJUS) e Secretaria de Estado de Gestão e Recursos Humanos (SEGER) do Governo do Estado do Espírito Santo.

O objetivo estratégico do projeto é **superar a barreira geográfica** que atualmente limita a atuação presencial dos Escritórios Sociais a apenas 4 dos 78 municípios capixabas, viabilizando o **atendimento remoto, individualizado e contínuo** a mais de 108 mil pessoas egressas do sistema prisional e seus familiares.

---

## 2. ESTRUTURA DO PROTÓTIPO E FUNCIONALIDADES MAPEADAS

O protótipo entregue contempla a disposição completa de menus, navegação entre perfis e visualização de dashboards (sem dependência imediata de backend funcional), estruturado nos seguintes pilares:

### A. Dashboard Executivo & Indicadores de Desempenho (KPIs - Seção 3.2 do Edital)
* **Monitoramento em Tempo Real:** Volume de egressos cadastrados (meta de 108 mil), total de atendimentos psicossociais remotos e taxa de encaminhamento para vagas de trabalho.
* **Redução da Reincidência Criminal:** Métrica visual de acompanhamento do impacto social e efetividade da reintegração.
* **Gráficos Analíticos Dinâmicos:** Distribuição de atendimentos pelos 78 municípios e divisão proporcional por tipo de acolhimento (Trabalho, Qualificação, Apoio Psicológico e Documentação).

### B. Módulo de Atendimento Remoto & Videoconferência (Item 3.1 `f`)
* **Fila de Espera em Tempo Real:** Organização por município e nível de prioridade.
* **Sala de Atendimento por Vídeo:** Simulador de chamada criptografada de ponta a ponta, com identificação do sinal do egresso (4G/Wi-Fi) e canal direto para atendimento psicossocial.
* **Prontuário Integrado:** Registro automático com carimbo de data, hora e CPF do técnico responsável.

### C. Módulo de Oportunidades & Qualificação Profissional (Item 3.1 `h`)
* Painel centralizador de vagas de emprego inclusivas (empresas conveniadas ao Estado) e cursos de capacitação técnica (SENAI, IFES, etc.), permitindo o encaminhamento direto do egresso.

### D. Carteira Digital do Egresso & Documentação Básica (Item 3.1 `e`, `i`)
* **Documento Digital Oficial:** Exibição da Carteira Digital do Egresso com QR Code de validação estadual e integração com o sistema **Acesso Cidadão / Gov.br**.
* **Solicitação de 2ª Via:** Encaminhamento para emissão gratuita de RG, Certidão de Nascimento, Título de Eleitor e Certidão de Execução Penal.

### E. Mapeamento Territorial dos 78 Municípios (Item 3.1 `n`)
* Painel interativo com a cobertura dos 74 municípios do interior do Estado que não possuem unidade física do Escritório Social, conectando automaticamente a demanda à rede socioassistencial local (CRAS/CREAS/SINE).

### F. Governança, LGPD e Níveis de Acesso (Item 3.1 `c`, `l`)
* **Seletor de Perfis:** Alternador dinâmico de visões na própria interface:
  1. *Perfil Gestor SEJUS:* Visão estratégica e estatística global.
  2. *Perfil Técnico / Atendente:* Fila de atendimento e registro de prontuário.
  3. *Perfil Egresso / Familiar:* Interface simplificada e de alta acessibilidade.
* **Acessibilidade:** Suporte nativo a Alto Contraste, Ampliação de Fonte e Modo de Linguagem Simplificada.

---

## 3. PASSO A PASSO PARA EXECUÇÃO NA MÁQUINA DO GESTOR / CHEFE

Para visualizar e testar o protótipo em qualquer computador (Windows, Mac ou Linux), siga as instruções simples abaixo:

### 💡 MÉTODO 1: ABERTURA DIRETA (MUITO FÁCIL - SEM INSTALAÇÃO)
1. Abra a pasta do projeto no computador.
2. Dê um **duplo clique** no arquivo **`index.html`**.
3. O projeto abrirá instantaneamente no seu navegador padrão (Google Chrome, Microsoft Edge, Mozilla Firefox ou Safari).
4. Pronto! Você já pode navegar por todos os menus, testar os botões e alternar entre os perfis de acesso no topo da tela.

---

### 🚀 MÉTODO 2: EXECUÇÃO VIA SERVIDOR LOCAL (RECOMENDADO PARA APRESENTAÇÕES)

Caso prefira rodar a aplicação em um servidor local via HTTP (porta 8080):

#### Opção A (Via Terminal / Prompt de Comando):
1. Abra o **Prompt de Comando (CMD)** ou **PowerShell**.
2. Navegue até a pasta do projeto e digite o comando abaixo:
   ```bash
   python -m http.server 8080
   ```
3. Abra o seu navegador e acesse a URL:
   👉 **http://localhost:8080**

#### Opção B (Via VS Code / Live Server, se utilizar o editor):
1. Abra a pasta do projeto no VS Code.
2. Clique com o botão direito no arquivo `index.html` e selecione **"Open with Live Server"**.

---

## 4. ROTEIRO RÁPIDO PARA TESTAR AS FUNCIONALIDADES

Durante a navegação, sugerimos os seguintes testes:
1. **Alternar de Perfil:** No canto superior direito, mude a opção em *"Perfil de Acesso"* de **Gestor SEJUS** para **Técnico** ou **Egresso** e veja a adaptação do painel.
2. **Testar Acessibilidade:** No topo da tela, clique em **"Alto Contraste"** ou **"Linguagem Fácil"** para ver o tema escuro institucional e a ampliação de leitura.
3. **Navegar pelos Menus Laterais:**
   - Clique em **"Atendimento Remoto & Vídeo"** para ver a fila de espera e a sala de chamada em vídeo.
   - Clique em **"Oportunidades & Trabalho"** para visualizar o painel de vagas de emprego no ES.
   - Clique em **"Carteira Digital & Documentos"** para visualizar o documento oficial digital com QR Code.
   - Clique em **"Mapeamento dos 78 Municípios"** para clicar nos municípios do ES e ver a rede de apoio local.

---

## 5. PRÓXIMOS PASSOS SUGERIDOS

Com a validação da arquitetura de menus e interface gráfica:
1. Apresentação do protótipo aos envolvidos da comissão técnica.
2. Definição da arquitetura de integração com o sistema de autenticação **Acesso Cidadão (PRODEST)** e banco de dados relacional.
3. Elaboração do Plano de Testes detalhado para a Prova de Conceito (PoC).

Ficamos à disposição para demonstrar a solução pessoalmente e responder a dúvidas técnicas.

---
**Equipe de Desenvolvimento de Soluções Inovadoras**  
*Plataforma Conecta Egresso • SEJUS/ES*
