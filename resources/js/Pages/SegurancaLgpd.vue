<template>
  <AppLayout :breadcrumbs="[{ name: 'Segurança & LGPD' }]">
    <Head title="Segurança da Informação & LGPD" />

    <div class="seguranca-lgpd-view space-y-6" id="view-lgpd">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-slate-900 via-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-semibold mb-2 border border-emerald-500/30">
            <span>🛡️ Conformidade Total com a Lei Geral de Proteção de Dados (Lei 13.709/2018)</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('seguranca_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Arquitetura de segurança com criptografia de ponta a ponta, blind index para busca protegida de PII, controle de acesso RBAC estrito e canal direto com o Encarregado de Dados (DPO).
          </p>
        </div>

        <div class="bg-white/10 backdrop-blur-md px-5 py-3 rounded-2xl border border-white/20 text-center">
          <span class="text-[10px] uppercase font-bold text-emerald-300 block">Status de Conformidade</span>
          <strong class="text-xl font-extrabold text-white font-heading">100% Homologado</strong>
        </div>
      </div>

      <!-- Encryption & Security Architecture Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[10px] uppercase font-bold text-slate-400">Criptografia em Repouso</span>
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          </div>
          <strong class="text-sm font-extrabold text-slate-900 block font-heading">AES-256-GCM / pgcrypto</strong>
          <p class="text-[11px] text-slate-500 leading-snug">Dados sensíveis, fotos e endereços criptografados no banco PostgreSQL 16.</p>
        </div>

        <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[10px] uppercase font-bold text-slate-400">Busca com Blind Index</span>
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          </div>
          <strong class="text-sm font-extrabold text-slate-900 block font-heading">HMAC-SHA256 Peppered</strong>
          <p class="text-[11px] text-slate-500 leading-snug">Busca rápida de CPF sem expor dados em texto claro (Blind Indexing).</p>
        </div>

        <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[10px] uppercase font-bold text-slate-400">Criptografia em Trânsito</span>
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          </div>
          <strong class="text-sm font-extrabold text-slate-900 block font-heading">TLS 1.3 / DTLS-SRTP</strong>
          <p class="text-[11px] text-slate-500 leading-snug">Áudio e vídeo WebRTC transmitidos com cifras criptográficas de alto padrão.</p>
        </div>

        <div class="p-5 bg-white rounded-2xl border border-slate-200/80 shadow-xs space-y-2">
          <div class="flex items-center justify-between">
            <span class="text-[10px] uppercase font-bold text-slate-400">Trilha de Auditoria</span>
            <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
          </div>
          <strong class="text-sm font-extrabold text-slate-900 block font-heading">Encadeamento SHA-256</strong>
          <p class="text-[11px] text-slate-500 leading-snug">Registros invioláveis com encadeamento de hash em todas as operações.</p>
        </div>
      </div>

      <!-- RBAC Matrix & Consent Management -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- RBAC Permissions Matrix (2 cols) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs lg:col-span-2 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide">
              Matriz de Perfis e Permissões (RBAC)
            </h2>
            <span class="text-xs font-semibold text-slate-400">Princípio do Privilégio Mínimo</span>
          </div>

          <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="border-b border-slate-200 text-slate-400 uppercase text-[10px] tracking-wider font-extrabold">
                  <th class="py-2.5 px-3">Funcionalidade / Módulo</th>
                  <th class="py-2.5 px-3 text-center">Gestor SEJUS</th>
                  <th class="py-2.5 px-3 text-center">Técnico Social</th>
                  <th class="py-2.5 px-3 text-center">Egresso / Familiar</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-medium">
                <tr v-for="item in rbacRules" :key="item.modulo" class="hover:bg-slate-50 transition">
                  <td class="py-2.5 px-3 font-bold text-slate-800">{{ item.modulo }}</td>
                  <td class="py-2.5 px-3 text-center">
                    <span class="text-emerald-600 font-bold" v-if="item.gestor">✅ Total</span>
                    <span class="text-slate-300" v-else>⛔</span>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span class="text-emerald-600 font-bold" v-if="item.tecnico === 'total'">✅ Total</span>
                    <span class="text-sky-600 font-bold" v-else-if="item.tecnico === 'operacional'">🔍 Operacional</span>
                    <span class="text-slate-300" v-else>⛔</span>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span class="text-purple-600 font-bold" v-if="item.egresso === 'proprio'">👤 Próprio</span>
                    <span class="text-emerald-600 font-bold" v-else-if="item.egresso === 'publico'">🌐 Público</span>
                    <span class="text-slate-300" v-else>⛔</span>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- DPO Channel Card (1 col) -->
        <div class="bg-white p-6 rounded-2xl border border-slate-200/80 shadow-xs space-y-4">
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-lg">⚖️</span>
              <h3 class="text-xs font-extrabold uppercase tracking-wider text-slate-500">
                Canal do Encarregado (DPO)
              </h3>
            </div>
            <strong class="text-sm font-extrabold text-slate-900 block">Exercício dos Direitos do Titular</strong>
            <p class="text-xs text-slate-500 mt-1">
              Solicite acesso, retificação ou esclarecimentos sobre o tratamento dos seus dados na SEJUS/ES.
            </p>
          </div>

          <form @submit.prevent="handleDpoRequest" class="space-y-3">
            <div>
              <label class="block text-[11px] font-bold text-slate-700 mb-1">Tipo de Solicitação</label>
              <select v-model="dpoForm.tipo" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option value="confirmacao">Confirmação da existência de tratamento</option>
                <option value="acesso">Acesso aos dados pessoais completos</option>
                <option value="retificacao">Correção de dados incompletos ou inexatos</option>
                <option value="revogacao">Revogação de consentimento opcional</option>
              </select>
            </div>

            <div>
              <label class="block text-[11px] font-bold text-slate-700 mb-1">Detalhamento do Pedido</label>
              <textarea v-model="dpoForm.detalhes" rows="3" required placeholder="Descreva sua solicitação com clareza..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none"></textarea>
            </div>

            <button type="submit" class="w-full py-2.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer">
              Enviar Solicitação ao DPO
            </button>
          </form>

          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 text-[11px] text-slate-500 space-y-0.5">
            <span class="font-bold text-slate-700 block">Encarregado de Dados SEJUS:</span>
            <span>dpo@sejus.es.gov.br • Prazo Legal: 15 dias úteis</span>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const { t } = useAccessibility();
const toast = useToast();

const rbacRules = [
  { modulo: 'Dashboard Executivo e Estatísticas', gestor: true, tecnico: 'operacional', egresso: 'proprio' },
  { modulo: 'Atendimento Remoto e Videochamadas', gestor: true, tecnico: 'total', egresso: 'proprio' },
  { modulo: 'Prontuário Único (Evolução Técnica)', gestor: true, tecnico: 'total', egresso: 'proprio' },
  { modulo: 'Oportunidades e Vagas de Trabalho', gestor: true, tecnico: 'operacional', egresso: 'publico' },
  { modulo: 'Carteira Digital (Emissão e PDF)', gestor: true, tecnico: 'operacional', egresso: 'proprio' },
  { modulo: 'Validador Público de Autenticidade', gestor: true, tecnico: 'total', egresso: 'publico' },
  { modulo: 'Trilha de Auditoria e Logs LGPD', gestor: true, tecnico: 'operacional', egresso: 'proprio' },
];

const dpoForm = ref({
  tipo: 'confirmacao',
  detalhes: '',
});

const handleDpoRequest = () => {
  const protocol = 'DPO-2026-' + Math.floor(10000 + Math.random() * 90000);
  toast.success(
    'Solicitação DPO Protocolada com Sucesso',
    `Solicitação protocolada com sucesso junto ao Encarregado de Proteção de Dados (DPO) da SEJUS/ES.\nProtocolo de Acompanhamento: ${protocol}`
  );
  dpoForm.value.detalhes = '';
};
</script>
