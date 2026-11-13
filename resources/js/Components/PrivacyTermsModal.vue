<template>
  <div
    v-if="isOpen"
    id="privacyTermsModal"
    class="fixed inset-0 z-50 flex items-center justify-center p-3 md:p-6 bg-slate-950/80 backdrop-blur-sm animate-fade-in"
    role="dialog"
    aria-modal="true"
    aria-labelledby="privacyModalTitle"
    tabindex="-1"
    @keydown.esc="handleClose"
  >
    <div class="modal-card w-full max-w-4xl bg-white dark:bg-slate-900 rounded-3xl shadow-2xl border border-slate-200 dark:border-slate-800 overflow-hidden flex flex-col max-h-[90vh]">
      <!-- Modal Header -->
      <div class="p-6 bg-gradient-to-r from-slate-950 via-[#003366] to-[#0f172a] text-white flex items-center justify-between border-b border-slate-800">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-2xl bg-white/10 border border-white/20 flex items-center justify-center">
            <ShieldCheck class="w-6 h-6 text-emerald-400 stroke-[2]" />
          </div>
          <div>
            <h2 id="privacyModalTitle" class="text-base sm:text-lg font-extrabold font-heading text-white leading-tight">
              Termos de Uso, Governança & Política de Privacidade LGPD
            </h2>
            <p class="text-xs text-sky-200">
              Secretaria de Estado da Justiça (SEJUS) • Governo do Estado do Espírito Santo
            </p>
          </div>
        </div>

        <button
          type="button"
          class="p-2 text-slate-300 hover:text-white rounded-xl hover:bg-white/10 transition cursor-pointer"
          aria-label="Fechar"
          @click="handleClose"
        >
          ✕
        </button>
      </div>

      <!-- Modal Body (Scrollable Legal Terms) -->
      <div class="p-6 md:p-8 overflow-y-auto space-y-6 text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed">
        <!-- Badge Resumo -->
        <div class="p-4 rounded-2xl bg-sky-50 dark:bg-sky-950/50 border border-sky-200 dark:border-sky-800 text-sky-950 dark:text-sky-200 space-y-1.5">
          <strong class="font-bold block text-sm flex items-center gap-2">
            <span>⚖️ Compromisso Institucional com a Lei nº 13.709/2018 (LGPD)</span>
          </strong>
          <p class="text-xs">
            Esta plataforma é gerida pela <strong>Secretaria de Estado da Justiça (SEJUS/ES)</strong> para a operacionalização da política pública de reintegração social, emissão da Carteira Digital do Egresso e encaminhamento produtivo aos 78 municípios capixabas.
          </p>
        </div>

        <!-- Seção 1 -->
        <section class="space-y-2">
          <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider flex items-center gap-2">
            <span>1. Finalidade e Base Legal do Tratamento (Art. 7º e Art. 23)</span>
          </h3>
          <p>
            O tratamento de dados pessoais e dados pessoais sensíveis pela SEJUS tem como fundamentação jurídica o <strong>cumprimento de obrigação legal e execução de políticas públicas</strong> previstas na Lei de Execução Penal (Lei nº 7.210/1984), bem como resoluções do Conselho Nacional de Justiça (CNJ) sobre os Escritórios Sociais.
          </p>
        </section>

        <!-- Seção 2 -->
        <section class="space-y-2">
          <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider flex items-center gap-2">
            <span>2. Dados Coletados & Segurança Criptográfica At-Rest</span>
          </h3>
          <ul class="list-disc pl-5 space-y-1.5">
            <li><strong>Identificação do Cidadão:</strong> Nome completo, CPF, número do processo de execução e município de residência.</li>
            <li><strong>Criptografia Forte AES-256:</strong> O CPF é armazenado de forma criptografada no banco de dados com algoritmo AES-256 e técnicas de <em>Blind Indexing (HMAC-SHA256)</em>, impossibilitando a exposição de dados em texto puro.</li>
            <li><strong>Trilha de Auditoria Imutável (Art. 37):</strong> Todos os acessos a prontuários e históricos são auditados com registro encadeado de hashes SHA-256 e endereço IP.</li>
            <li><strong>Teleatendimento WebRTC:</strong> As salas virtuais contam com transmissão criptografada ponta a ponta (DTLS-SRTP).</li>
          </ul>
        </section>

        <!-- Seção 3 -->
        <section class="space-y-2">
          <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider flex items-center gap-2">
            <span>3. Compartilhamento Restrito com Empresas Parceiras</span>
          </h3>
          <p>
            Quando o cidadão egresso se candidata a uma vaga de emprego, apenas as informações profissionais estritamente necessárias (conhecimentos técnicos cadastrados, município e validação de autenticidade da Carteira Digital) são compartilhadas com a empresa contratante. Nenhum dado penal íntimo ou sensível é transferido a terceiros.
          </p>
        </section>

        <!-- Seção 4 -->
        <section class="space-y-2">
          <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider flex items-center gap-2">
            <span>4. Política de Cookies e Armazenamento Local</span>
          </h3>
          <p>
            Utilizamos apenas cookies e itens de armazenamento estritamente essenciais para o funcionamento da plataforma:
          </p>
          <ul class="list-disc pl-5 space-y-1">
            <li><strong>Sessão Autenticada (Gov.br / SEJUS):</strong> Manutenção da identidade e proteção contra requisições forjadas (CSRF).</li>
            <li><strong>Acessibilidade & Temas:</strong> Armazenamento do tema de alto contraste escolhido e tamanho da fonte no navegador do usuário.</li>
          </ul>
        </section>

        <!-- Seção 5 -->
        <section class="space-y-2">
          <h3 class="font-bold text-slate-900 dark:text-white text-sm uppercase tracking-wider flex items-center gap-2">
            <span>5. Direitos do Titular dos Dados (Art. 18 da LGPD)</span>
          </h3>
          <p>
            O titular pode solicitar a confirmação de tratamento, retificação de dados cadastrais incompletos e revogação de consentimentos opcionais diretamente na seção <strong>Segurança & LGPD</strong> do sistema ou através da Ouvidoria Geral do Estado do Espírito Santo.
          </p>
        </section>
      </div>

      <!-- Modal Footer -->
      <div class="p-4 md:p-6 bg-slate-50 dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 flex items-center justify-between gap-3">
        <span class="text-[11px] text-slate-500 dark:text-slate-400">
          Versão 2.4 Homologada • DPO SEJUS/ES
        </span>
        <button
          type="button"
          class="px-6 py-2.5 bg-[#003366] hover:bg-[#002244] text-white font-bold text-xs rounded-xl shadow-md transition cursor-pointer"
          @click="handleClose"
        >
          Entendido e Fechar
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ShieldCheck } from 'lucide-vue-next';

defineProps({
  isOpen: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(['close']);

const handleClose = () => {
  emit('close');
};
</script>
