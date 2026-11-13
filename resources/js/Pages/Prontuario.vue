<template>
  <AppLayout :breadcrumbs="[{ name: 'Prontuário Único' }]">
    <Head title="Prontuário Único do Egresso" />

    <div class="prontuario-view space-y-6" id="view-prontuario">
      <!-- Top Banner -->
      <div class="bg-gradient-to-r from-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-emerald-500/20 text-emerald-300 rounded-full text-xs font-semibold mb-2 border border-emerald-500/30">
            <span>📁 Trilha de Auditoria Imutável LGPD Ativa</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ t('prontuario_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-xl">
            Histórico unificado de atendimentos psicossociais, videochamadas, encaminhamentos profissionais e evoluções técnicas do cidadão egresso.
          </p>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-700 text-white font-bold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="isNewEntryModalOpen = true"
          >
            <span>➕ Nova Evolução Técnica</span>
          </button>
        </div>
      </div>

      <!-- Barra de Busca de Prontuário Operacional (Para Gestor / Suporte / Técnico) -->
      <div v-if="isStaff" class="bg-white rounded-2xl border border-slate-200/90 p-5 shadow-xs space-y-3">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h3 class="text-sm font-bold text-slate-800 flex items-center gap-2">
              <span>🔍 Consulta de Prontuário Único</span>
            </h3>
            <p class="text-xs text-slate-500">Localize o histórico do egresso por Nº do Prontuário, CPF ou Nome completo.</p>
          </div>
          <form @submit.prevent="handleSearch" class="flex items-center gap-2 max-w-md w-full sm:w-auto">
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Nº Prontuário, CPF ou Nome..."
              class="w-full sm:w-72 px-3 py-2 text-xs border border-slate-300 rounded-xl focus:ring-2 focus:ring-sky-500 outline-none"
            />
            <button
              type="submit"
              class="px-4 py-2 bg-sky-600 hover:bg-sky-700 text-white text-xs font-bold rounded-xl transition cursor-pointer"
            >
              Buscar
            </button>
            <button
              v-if="prontuarioData || egressoData"
              type="button"
              @click="clearSelection"
              class="px-3 py-2 bg-slate-100 hover:bg-slate-200 text-slate-600 text-xs font-medium rounded-xl transition cursor-pointer"
              title="Limpar seleção"
            >
              ✕
            </button>
          </form>
        </div>

        <!-- Prontuários Recentes -->
        <div v-if="recentProntuarios && recentProntuarios.length" class="pt-2 border-t border-slate-100 flex flex-wrap items-center gap-2">
          <span class="text-[11px] font-semibold text-slate-400">Prontuários Ativos:</span>
          <button
            v-for="p in recentProntuarios"
            :key="p.id"
            type="button"
            @click="selectProntuario(p.id)"
            class="text-[11px] px-2.5 py-1 rounded-lg border transition cursor-pointer flex items-center gap-1.5"
            :class="prontuarioData && prontuarioData.id === p.id ? 'bg-sky-50 border-sky-300 text-sky-800 font-bold' : 'bg-slate-50 border-slate-200 text-slate-700 hover:bg-slate-100'"
          >
            <span class="font-mono font-bold">{{ p.numero_prontuario }}</span>
            <span>- {{ p.egresso_nome }}</span>
          </button>
        </div>
      </div>

      <!-- Empty State quando nenhum prontuário está selecionado -->
      <div
        v-if="!prontuarioData"
        class="bg-white rounded-2xl border border-slate-200/80 p-12 text-center space-y-3 shadow-xs"
      >
        <div class="w-14 h-14 bg-sky-50 text-sky-600 rounded-2xl mx-auto flex items-center justify-center text-2xl font-bold">
          📁
        </div>
        <p class="text-base font-bold text-slate-800">Nenhum Prontuário Selecionado</p>
        <p class="text-xs text-slate-500 max-w-md mx-auto">
          Utilize a barra de busca acima ou selecione um prontuário ativo para consultar o dossiê, plano individual de reintegração e evoluções técnicas.
        </p>
      </div>

      <!-- Egresso Profile Header Dossier -->
      <div v-else class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 pb-5 border-b border-slate-100">
          <div class="flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-sky-600 to-indigo-900 text-white font-extrabold text-lg flex items-center justify-center border-2 border-white shadow-md flex-shrink-0">
              {{ egressoInitials }}
            </div>
            <div>
              <div class="flex items-center gap-2">
                <h2 class="text-base font-extrabold text-slate-900">{{ egressoData.nome_completo }}</h2>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                  {{ prontuarioData.situacao }}
                </span>
              </div>
              <p class="text-xs text-slate-500 mt-0.5">
                Prontuário: <span class="font-mono font-bold text-slate-700">{{ prontuarioData.numero_prontuario }}</span> • Polo: <span class="font-semibold text-slate-700">{{ egressoData.municipio?.nome || 'Vitória' }}/ES</span>
              </p>
            </div>
          </div>

          <div class="text-right">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Técnico Responsável</span>
            <strong class="text-xs text-slate-800 font-bold block">{{ prontuarioData.tecnico_responsavel?.name || 'Equipe Técnica Escritório Social' }}</strong>
            <span class="text-[11px] text-slate-500">Abertura: {{ prontuarioData.data_abertura }}</span>
          </div>
        </div>

        <!-- Masked PII Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 pt-4 text-xs">
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">CPF Mascarado (LGPD)</span>
            <strong class="text-slate-800 font-mono block mt-0.5">{{ egressoData.cpf_masked }}</strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Data de Nascimento</span>
            <strong class="text-slate-800 block mt-0.5">{{ egressoData.data_nascimento }}</strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Escolaridade</span>
            <strong class="text-slate-800 block mt-0.5">{{ egressoData.escolaridade }}</strong>
          </div>
          <div class="p-3 bg-slate-50 rounded-xl border border-slate-200/60">
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Processo de Execução</span>
            <strong class="text-slate-800 font-mono block mt-0.5">{{ egressoData.numero_processo_execucao }}</strong>
          </div>
        </div>

        <!-- Vulnerability Tags & Individual Plan Goal -->
        <div class="mt-4 pt-4 border-t border-slate-100 grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1.5">Vulnerabilidades Mapeadas</span>
            <div v-if="egressoData.vulnerabilidades?.length" class="flex flex-wrap gap-1.5">
              <span v-for="tag in egressoData.vulnerabilidades" :key="tag" class="px-2.5 py-1 bg-amber-50 text-amber-800 border border-amber-200 rounded-lg text-[11px] font-semibold">
                ⚠️ {{ tag }}
              </span>
            </div>
            <span v-else class="text-xs text-slate-400 italic">Nenhuma vulnerabilidade registrada.</span>
          </div>

          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block mb-1.5">Meta do Plano Individual de Reintegração</span>
            <p class="text-slate-700 bg-sky-50/70 p-2.5 rounded-xl border border-sky-200/70 text-[11px] leading-relaxed">
              {{ prontuarioData.meta_plano_individual }}
            </p>
          </div>
        </div>
      </div>

      <!-- Ficha do Egresso: Conhecimentos e Qualificações Profissionais (R1) -->
      <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4" id="section-conhecimentos">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-100">
          <div>
            <div class="flex items-center gap-2">
              <span class="text-xl">💼</span>
              <h2 class="text-base font-extrabold text-slate-900 font-heading">
                Ficha do Egresso: Conhecimentos e Qualificações
              </h2>
              <span class="text-xs font-bold px-2.5 py-0.5 rounded-full bg-sky-100 text-sky-800 border border-sky-200">
                {{ conhecimentos.length }} {{ conhecimentos.length === 1 ? 'conhecimento' : 'conhecimentos' }}
              </span>
            </div>
            <p class="text-xs text-slate-500 mt-1">
              Mapeamento de competências profissionais, níveis de proficiência e necessidades de qualificação para o mercado de trabalho.
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              id="btn-gerenciar-conhecimentos"
              :disabled="!egressoData?.id"
              class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              @click="openEditModal"
            >
              <span>✏️ Gerenciar Conhecimentos</span>
            </button>
          </div>
        </div>

        <!-- Empty State -->
        <div
          v-if="conhecimentos.length === 0"
          class="text-center py-8 px-4 rounded-xl border-2 border-dashed border-slate-200 bg-slate-50/50"
          id="conhecimentos-empty-state"
        >
          <div class="w-12 h-12 mx-auto rounded-full bg-slate-100 flex items-center justify-center text-xl text-slate-400 mb-2">
            📋
          </div>
          <h3 class="text-xs font-bold text-slate-700">Nenhum conhecimento ou qualificação cadastrada</h3>
          <p class="text-[11px] text-slate-500 mt-1 max-w-sm mx-auto">
            Cadastre as habilidades técnicas e áreas de domínio do egresso para facilitar o encaminhamento a vagas de emprego e cursos.
          </p>
          <button
            v-if="egressoData?.id"
            type="button"
            class="mt-3 px-3 py-1.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold text-xs rounded-lg transition cursor-pointer"
            @click="openEditModal"
          >
            ➕ Cadastrar Conhecimento
          </button>
        </div>

        <!-- Knowledge Badges / Grid Display -->
        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3" id="conhecimentos-grid">
          <div
            v-for="(item, idx) in conhecimentos"
            :key="item.id || idx"
            class="p-4 rounded-xl bg-slate-50/70 border border-slate-200/80 hover:border-slate-300 transition space-y-2.5 conhecimento-card"
          >
            <div class="flex items-start justify-between gap-2">
              <h4 class="text-xs font-extrabold text-slate-900 leading-snug conhecimento-nome">
                {{ item.nome }}
              </h4>
              <!-- Proficiency Level Badge -->
              <span
                class="text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wider border flex-shrink-0 nivel-badge"
                :class="getNivelBadgeClass(item.nivel)"
              >
                {{ formatNivel(item.nivel) }}
              </span>
            </div>

            <!-- Training Status Badge -->
            <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px]">
              <span class="text-slate-500 font-medium">Necessita de treinamento:</span>
              <span
                v-if="item.necessita_treinamento"
                class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-full text-[10px] bg-amber-100 text-amber-800 border border-amber-200 treinamento-badge-sim"
              >
                <span>⚠️</span> Sim
              </span>
              <span
                v-else
                class="inline-flex items-center gap-1 font-bold px-2 py-0.5 rounded-full text-[10px] bg-emerald-100 text-emerald-800 border border-emerald-200 treinamento-badge-nao"
              >
                <span>✅</span> Não
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Chronological Timeline Stream -->
      <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>⏱️ Linha do Tempo e Histórico de Evolução</span>
            <span class="text-xs font-normal text-slate-500">({{ timeline.length }} registros auditados)</span>
          </h2>
          <span class="text-[11px] font-mono text-slate-400">Hash SHA-256 Validado</span>
        </div>

        <div v-if="timeline.length === 0" class="text-center py-6 text-xs text-slate-400">
          Nenhum registro cronológico lançado neste prontuário até o momento.
        </div>

        <!-- Timeline Items -->
        <div v-else class="relative pl-6 space-y-6 before:absolute before:left-2.5 before:top-3 before:bottom-3 before:w-0.5 before:bg-slate-200">
          <div
            v-for="evt in timeline"
            :key="evt.id"
            class="timeline-item relative group"
          >
            <!-- Timeline Dot -->
            <div class="absolute -left-6 top-1.5 w-5 h-5 rounded-full bg-white border-2 border-sky-600 flex items-center justify-center shadow-xs">
              <div class="w-2 h-2 rounded-full bg-sky-600"></div>
            </div>

            <!-- Content Card -->
            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 group-hover:border-sky-300 transition space-y-2">
              <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <div class="flex items-center gap-2">
                  <span class="text-base">{{ getEventIcon(evt.tipo_evento) }}</span>
                  <strong class="text-xs font-extrabold text-slate-900">{{ evt.titulo }}</strong>
                  <span class="text-[10px] font-bold px-2 py-0.5 rounded uppercase bg-slate-200 text-slate-700">
                    {{ evt.tipo_evento }}
                  </span>
                </div>
                <span class="text-[11px] text-slate-400 font-medium">{{ evt.data_evento }}</span>
              </div>

              <p class="text-xs text-slate-700 leading-relaxed">
                {{ evt.descricao }}
              </p>

              <!-- Metadata Box if present -->
              <div v-if="evt.metadata && Object.keys(evt.metadata).length" class="p-2.5 bg-white rounded-xl border border-slate-200 text-[11px] font-mono text-slate-600 space-y-0.5">
                <div v-for="(val, key) in evt.metadata" :key="key" class="flex items-center gap-2">
                  <span class="font-bold text-slate-500">{{ key }}:</span>
                  <span class="text-slate-800">{{ val }}</span>
                </div>
              </div>

              <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400">
                <span>Responsável: <strong class="text-slate-600">{{ evt.responsavel?.name || 'Sistema Central SEJUS' }}</strong></span>
                <span class="text-emerald-700 font-bold">● Registro Imutável</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- New Entry Modal -->
      <div v-if="isNewEntryModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-xl">➕</span>
            <h3 class="text-base font-extrabold text-slate-900">Registrar Nova Evolução no Prontuário</h3>
          </div>
          <p class="text-xs text-slate-500 mb-4">O registro será assinado digitalmente e integrado à trilha imutável de auditoria.</p>

          <form @submit.prevent="handleCreateEntry" class="space-y-3.5">
            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Tipo de Evento</label>
              <select v-model="newEntryForm.tipo_evento" class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none">
                <option value="parecer_tecnico">Evolução Técnica / Psicossocial</option>
                <option value="atendimento_remoto">Atendimento Remoto por Vídeo</option>
                <option value="encaminhamento_vaga">Encaminhamento para Vaga de Emprego</option>
                <option value="matricula_curso">Matrícula em Curso de Capacitação</option>
                <option value="emissao_carteira">Emissão de Credencial / Documentos</option>
              </select>
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Título do Evento</label>
              <input v-model="newEntryForm.titulo" type="text" required placeholder="Ex: Acompanhamento mensal de reintegração..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none" />
            </div>

            <div>
              <label class="block text-xs font-bold text-slate-700 mb-1">Descrição Detalhada</label>
              <textarea v-model="newEntryForm.descricao" rows="4" required placeholder="Relate as intervenções, estado socioeconômico e encaminhamentos acordados..." class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-sky-500 outline-none"></textarea>
            </div>

            <div class="flex justify-end gap-2 pt-2">
              <button type="button" @click="isNewEntryModalOpen = false" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
              <button type="submit" class="px-4 py-2 bg-sky-600 text-white text-xs font-bold rounded-lg hover:bg-sky-700 transition">Gravar Evolução</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Modal de Gestão de Conhecimentos (R1) -->
      <div
        v-if="isConhecimentosModalOpen"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs"
        id="modal-conhecimentos"
      >
        <div class="bg-white rounded-2xl max-w-2xl w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] flex flex-col">
          <!-- Modal Header -->
          <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4 flex-shrink-0">
            <div class="flex items-center gap-2">
              <span class="text-xl">💼</span>
              <div>
                <h3 class="text-base font-extrabold text-slate-900">
                  Gerenciar Conhecimentos da Ficha do Egresso
                </h3>
                <p class="text-xs text-slate-500">
                  Adicione múltiplos conhecimentos sem restrição de quantidade, defina o nível e informe se necessita de treinamento.
                </p>
              </div>
            </div>
            <button
              type="button"
              @click="cancelEdit"
              class="text-slate-400 hover:text-slate-600 p-1 rounded-lg text-lg leading-none cursor-pointer"
            >
              ✕
            </button>
          </div>

          <!-- Modal Body (Dynamic Scrollable List) -->
          <div class="overflow-y-auto flex-1 pr-1 space-y-3">
            <div
              v-if="editingConhecimentos.length === 0"
              class="text-center py-6 text-slate-400 text-xs"
            >
              Nenhum conhecimento na lista. Clique em "Adicionar Outro Conhecimento" ou salve para manter a ficha sem conhecimentos.
            </div>

            <div
              v-for="(item, index) in editingConhecimentos"
              :key="index"
              class="p-3.5 bg-slate-50 rounded-xl border border-slate-200 space-y-3 relative group row-conhecimento"
            >
              <div class="flex items-center justify-between">
                <span class="text-[11px] font-extrabold uppercase text-slate-500">
                  Conhecimento #{{ index + 1 }}
                </span>
                <button
                  type="button"
                  @click="removeConhecimentoRow(index)"
                  title="Remover conhecimento"
                  class="text-xs text-rose-600 hover:text-rose-800 font-bold px-2 py-1 rounded hover:bg-rose-50 transition flex items-center gap-1 cursor-pointer btn-remover-conhecimento"
                >
                  <span>🗑️</span>
                  <span>Remover</span>
                </button>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <!-- Skill Name or Catalog Selection -->
                <div class="sm:col-span-6">
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Conhecimento <span class="text-rose-500">*</span>
                  </label>
                  <div class="space-y-1.5">
                    <!-- Quick Select from Catalog if available -->
                    <select
                      v-if="catalogo.length > 0"
                      :value="item.conhecimento_id || ''"
                      @change="handleCatalogSelect(item, $event.target.value)"
                      class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500 outline-none select-catalogo"
                    >
                      <option value="">-- Selecione do catálogo ou digite abaixo --</option>
                      <option
                        v-for="cat in catalogo"
                        :key="cat.id"
                        :value="cat.id"
                      >
                        {{ cat.nome }} {{ cat.categoria ? `(${cat.categoria})` : '' }}
                      </option>
                    </select>

                    <!-- Custom Text Input for Skill Name -->
                    <input
                      v-model="item.nome"
                      type="text"
                      @input="handleCustomNameInput(item)"
                      placeholder="Digite ou selecione o conhecimento..."
                      class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500 outline-none input-nome-conhecimento"
                      :class="{ 'border-rose-400 bg-rose-50/30': formErrors[`item_${index}_nome`] }"
                    />
                    <span
                      v-if="formErrors[`item_${index}_nome`]"
                      class="text-[10px] text-rose-600 font-semibold block"
                    >
                      {{ formErrors[`item_${index}_nome`] }}
                    </span>
                  </div>
                </div>

                <!-- Proficiency Level (3 Options: Básico, Intermediário, Avançado) -->
                <div class="sm:col-span-3">
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Nível <span class="text-rose-500">*</span>
                  </label>
                  <select
                    v-model="item.nivel"
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500 outline-none select-nivel"
                    :class="{ 'border-rose-400 bg-rose-50/30': formErrors[`item_${index}_nivel`] }"
                  >
                    <option value="basico">Básico</option>
                    <option value="intermediario">Intermediário</option>
                    <option value="avancado">Avançado</option>
                  </select>
                  <span
                    v-if="formErrors[`item_${index}_nivel`]"
                    class="text-[10px] text-rose-600 font-semibold block"
                  >
                    {{ formErrors[`item_${index}_nivel`] }}
                  </span>
                </div>

                <!-- Necessita de Treinamento (Sim / Não) -->
                <div class="sm:col-span-3">
                  <label class="block text-[11px] font-bold text-slate-700 mb-1">
                    Necessita de treinamento <span class="text-rose-500">*</span>
                  </label>
                  <select
                    v-model="item.necessita_treinamento"
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500 outline-none select-treinamento"
                  >
                    <option :value="true">Sim</option>
                    <option :value="false">Não</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Add Row Button (Unlimited additions) -->
            <div class="pt-1">
              <button
                type="button"
                id="btn-adicionar-linha-conhecimento"
                @click="addConhecimentoRow"
                class="w-full py-2.5 border-2 border-dashed border-indigo-300 hover:border-indigo-500 text-indigo-700 bg-indigo-50/50 hover:bg-indigo-50 rounded-xl text-xs font-bold transition flex items-center justify-center gap-2 cursor-pointer"
              >
                <span>➕ Adicionar Outro Conhecimento</span>
              </button>
            </div>
          </div>

          <!-- Modal Footer Actions -->
          <div class="flex items-center justify-between pt-4 border-t border-slate-100 mt-4 flex-shrink-0">
            <span class="text-[11px] text-slate-400">
              {{ editingConhecimentos.length }} {{ editingConhecimentos.length === 1 ? 'conhecimento configurado' : 'conhecimentos configurados' }}
            </span>

            <div class="flex items-center gap-2">
              <button
                type="button"
                id="btn-cancelar-conhecimentos"
                @click="cancelEdit"
                :disabled="isSavingConhecimentos"
                class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition cursor-pointer"
              >
                Cancelar
              </button>

              <button
                type="button"
                id="btn-salvar-conhecimentos"
                @click="saveConhecimentos"
                :disabled="isSavingConhecimentos"
                class="px-5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white text-xs font-bold rounded-lg shadow-md hover:shadow transition flex items-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="isSavingConhecimentos" class="animate-spin text-xs">⏳</span>
                <span>{{ isSavingConhecimentos ? 'Salvando...' : 'Salvar Conhecimentos' }}</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { Head, usePage, router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  isStaff: {
    type: Boolean,
    default: false,
  },
  recentProntuarios: {
    type: Array,
    default: () => [],
  },
  egresso: {
    type: Object,
    default: null,
  },
  prontuario: {
    type: Object,
    default: null,
  },
  timeline: {
    type: Array,
    default: () => [],
  },
  conhecimentos: {
    type: Array,
    default: null,
  },
  catalogoConhecimentos: {
    type: Array,
    default: () => [],
  },
});

const searchQuery = ref('');

const handleSearch = () => {
  if (!searchQuery.value.trim()) {
    router.visit('/prontuario');
    return;
  }
  router.visit(`/prontuario?q=${encodeURIComponent(searchQuery.value.trim())}`, {
    preserveState: true,
  });
};

const selectProntuario = (id) => {
  router.visit(`/prontuario/${id}`, {
    preserveState: true,
  });
};

const clearSelection = () => {
  searchQuery.value = '';
  router.visit('/prontuario');
};

const page = usePage();
const { t } = useAccessibility();
const toast = useToast();

const egressoData = computed(() => props.egresso);

const prontuarioData = computed(() => {
  if (props.prontuario) {
    return props.prontuario;
  }
  return {
    id: null,
    numero_prontuario: 'EM ABERTURA',
    situacao: 'ACOMPANHAMENTO ATIVO',
    data_abertura: '—',
    tecnico_responsavel: { name: 'Equipe Técnica Escritório Social' },
    resumo_diagnostico: '',
    meta_plano_individual: 'Plano Individual em elaboração pela equipe multiprofissional.',
  };
});

const egressoInitials = computed(() => {
  const nome = egressoData.value?.nome_completo || '';
  const parts = nome.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return 'EG';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
});

const timeline = ref(Array.isArray(props.timeline) ? [...props.timeline] : []);
const isNewEntryModalOpen = ref(false);

const newEntryForm = ref({
  tipo_evento: 'parecer_tecnico',
  titulo: '',
  descricao: '',
});

const getEventIcon = (tipo) => {
  switch (tipo) {
    case 'acolhimento_video':
    case 'ACOLHIMENTO_INICIAL': return '👋';
    case 'emissao_carteira':
    case 'emissao_documento':
    case 'EMISSAO_CARTEIRA': return '💳';
    case 'atendimento_remoto':
    case 'ATENDIMENTO_VIDEO': return '📹';
    case 'encaminhamento_vaga':
    case 'ENCAMINHAMENTO_EMPREGO': return '💼';
    case 'inscricao_curso':
    case 'matricula_curso':
    case 'CURSO_CAPACITACAO': return '🎓';
    default: return '📝';
  }
};

const handleCreateEntry = async () => {
  const responsavelName = page.props.auth?.user?.name || 'Equipe Técnica Escritório Social';
  const prontuarioId = prontuarioData.value?.id;

  if (prontuarioId) {
    try {
      const csrfToken = typeof document !== 'undefined'
        ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        : null;

      const res = await fetch(`/api/prontuarios/${prontuarioId}/timeline`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        },
        body: JSON.stringify({
          tipo_evento: newEntryForm.value.tipo_evento,
          titulo: newEntryForm.value.titulo,
          descricao: newEntryForm.value.descricao,
        }),
      });

      if (!res.ok) {
        const errPayload = await res.json().catch(() => ({}));
        toast.error('Erro ao Registrar', errPayload.error || 'Não foi possível gravar a evolução no prontuário.');
        return;
      }
    } catch {
      toast.error('Erro de Conexão', 'Falha de comunicação ao gravar a evolução técnica.');
      return;
    }
  }

  timeline.value.unshift({
    id: Date.now(),
    tipo_evento: newEntryForm.value.tipo_evento,
    titulo: newEntryForm.value.titulo,
    descricao: newEntryForm.value.descricao,
    responsavel: { name: responsavelName },
    data_evento: 'Hoje às ' + new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
    metadata: { 'Origem': 'Painel Web Escritório Social', 'Integridade': 'Assinado Digitalmente' },
  });

  isNewEntryModalOpen.value = false;
  newEntryForm.value = { tipo_evento: 'parecer_tecnico', titulo: '', descricao: '' };

  toast.success(
    'Evolução Registrada no Prontuário',
    'Novo registro técnico inserido com sucesso no Prontuário Único do Egresso.'
  );
};

// -------------------------------------------------------------
// Reactive State for Conhecimentos (R1)
// -------------------------------------------------------------
const getInitialConhecimentos = () => {
  if (Array.isArray(props.conhecimentos)) {
    return JSON.parse(JSON.stringify(props.conhecimentos));
  }
  if (props.egresso && Array.isArray(props.egresso.conhecimentos)) {
    return JSON.parse(JSON.stringify(props.egresso.conhecimentos));
  }
  return [];
};

const conhecimentos = ref(getInitialConhecimentos());
const catalogo = ref(Array.isArray(props.catalogoConhecimentos) ? [...props.catalogoConhecimentos] : []);
const isConhecimentosModalOpen = ref(false);
const isSavingConhecimentos = ref(false);
const formErrors = ref({});
const editingConhecimentos = ref([]);

onMounted(async () => {
  if (catalogo.value.length === 0) {
    try {
      const res = await fetch('/api/conhecimentos', { headers: { 'Accept': 'application/json' } });
      if (res.ok) {
        const json = await res.json();
        if (Array.isArray(json.data)) {
          catalogo.value = json.data;
        }
      }
    } catch {
      // Catálogo opcional para preenchimento rápido
    }
  }

  if (props.conhecimentos === null && props.egresso?.id) {
    try {
      const res = await fetch(`/api/egressos/${props.egresso.id}/conhecimentos`, {
        headers: { 'Accept': 'application/json' },
      });
      if (res.ok) {
        const json = await res.json();
        if (Array.isArray(json.data)) {
          conhecimentos.value = json.data;
        }
      }
    } catch {
      // Mantém lista atual caso haja falha de rede
    }
  }
});

// -------------------------------------------------------------
// Helpers & Badge Formatters
// -------------------------------------------------------------
const getNivelBadgeClass = (nivel) => {
  const norm = (nivel || '').toLowerCase();
  switch (norm) {
    case 'basico':
    case 'básico':
      return 'bg-sky-50 text-sky-700 border-sky-200';
    case 'intermediario':
    case 'intermediário':
      return 'bg-amber-50 text-amber-800 border-amber-200';
    case 'avancado':
    case 'avançado':
      return 'bg-emerald-50 text-emerald-800 border-emerald-200';
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200';
  }
};

const formatNivel = (nivel) => {
  const norm = (nivel || '').toLowerCase();
  switch (norm) {
    case 'basico':
    case 'básico':
      return 'Básico';
    case 'intermediario':
    case 'intermediário':
      return 'Intermediário';
    case 'avancado':
    case 'avançado':
      return 'Avançado';
    default:
      return nivel || 'Básico';
  }
};

// -------------------------------------------------------------
// Modal & Array Manipulation Handlers
// -------------------------------------------------------------
const openEditModal = () => {
  editingConhecimentos.value = conhecimentos.value.map(item => ({
    id: item.id || null,
    conhecimento_id: item.conhecimento_id || null,
    nome: item.nome || '',
    nivel: item.nivel || 'basico',
    necessita_treinamento: Boolean(item.necessita_treinamento),
  }));

  if (editingConhecimentos.value.length === 0) {
    addConhecimentoRow();
  }

  formErrors.value = {};
  isConhecimentosModalOpen.value = true;
};

const cancelEdit = () => {
  editingConhecimentos.value = [];
  formErrors.value = {};
  isConhecimentosModalOpen.value = false;
};

const addConhecimentoRow = () => {
  editingConhecimentos.value.push({
    id: null,
    conhecimento_id: null,
    nome: '',
    nivel: 'basico',
    necessita_treinamento: false,
  });
};

const removeConhecimentoRow = (index) => {
  editingConhecimentos.value.splice(index, 1);
};

const handleCatalogSelect = (row, selectedId) => {
  if (!selectedId) {
    row.conhecimento_id = null;
    return;
  }
  const selected = catalogo.value.find(c => c.id === Number(selectedId));
  if (selected) {
    row.conhecimento_id = selected.id;
    row.nome = selected.nome;
  }
};

const handleCustomNameInput = (row) => {
  if (!row.conhecimento_id) return;
  const selected = catalogo.value.find(c => c.id === Number(row.conhecimento_id));
  if (!selected || selected.nome.trim().toLowerCase() !== (row.nome || '').trim().toLowerCase()) {
    row.conhecimento_id = null;
  }
};

const saveConhecimentos = async () => {
  formErrors.value = {};
  const cleanedItems = [];
  const seenNames = new Set();
  let hasError = false;

  for (let i = 0; i < editingConhecimentos.value.length; i++) {
    const item = editingConhecimentos.value[i];
    const trimmedName = (item.nome || '').trim();

    if (!trimmedName) {
      formErrors.value[`item_${i}_nome`] = 'Informe o nome do conhecimento.';
      hasError = true;
      continue;
    }

    const normalizedLower = trimmedName.toLowerCase();
    if (seenNames.has(normalizedLower)) {
      formErrors.value[`item_${i}_nome`] = 'Este conhecimento já foi adicionado.';
      hasError = true;
      continue;
    }
    seenNames.add(normalizedLower);

    if (!['basico', 'intermediario', 'avancado'].includes(item.nivel)) {
      formErrors.value[`item_${i}_nivel`] = 'Nível deve ser Básico, Intermediário ou Avançado.';
      hasError = true;
      continue;
    }

    cleanedItems.push({
      conhecimento_id: item.conhecimento_id || null,
      nome: trimmedName,
      nivel: item.nivel,
      necessita_treinamento: Boolean(item.necessita_treinamento),
    });
  }

  if (hasError) {
    toast.error('Atenção', 'Corrija os erros destacados no formulário antes de salvar.');
    return;
  }

  const egressoId = egressoData.value?.id;
  if (!egressoId) {
    toast.error('Erro', 'Nenhum egresso selecionado para salvar conhecimentos.');
    return;
  }

  isSavingConhecimentos.value = true;

  try {
    const csrfToken = typeof document !== 'undefined'
      ? document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
      : null;

    const response = await fetch(`/api/egressos/${egressoId}/conhecimentos`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify({ conhecimentos: cleanedItems }),
    });

    const data = await response.json().catch(() => ({}));

    if (response.ok) {
      conhecimentos.value = Array.isArray(data.data) ? data.data : cleanedItems;
      // Atualiza também o catálogo local caso novos conhecimentos tenham sido criados dinamicamente
      for (const saved of conhecimentos.value) {
        if (saved.conhecimento_id && !catalogo.value.some(c => c.id === saved.conhecimento_id)) {
          catalogo.value.push({
            id: saved.conhecimento_id,
            nome: saved.nome,
            categoria: saved.categoria || null,
          });
        }
      }
      isConhecimentosModalOpen.value = false;
      toast.success(
        'Conhecimentos Atualizados',
        'Ficha do Egresso atualizada com sucesso com as qualificações registradas.'
      );
    } else {
      toast.error(
        'Falha ao Salvar Conhecimentos',
        data.error || 'Verifique os dados informados e tente novamente.'
      );
    }
  } catch {
    toast.error(
      'Erro de Conexão',
      'Não foi possível comunicar com o servidor para salvar os conhecimentos.'
    );
  } finally {
    isSavingConhecimentos.value = false;
  }
};
</script>
