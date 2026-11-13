<template>
  <AppLayout :breadcrumbs="[{ name: activeTab === 'inserir-vaga' ? 'Inserir Vaga' : 'Oportunidades & Trabalho' }]">
    <Head :title="activeTab === 'inserir-vaga' ? 'Inserir Vaga — Perfil Empregador' : 'Oportunidades & Trabalho'" />

    <div class="oportunidades-view space-y-6" id="view-oportunidades">
      <!-- Header Banner -->
      <div class="bg-gradient-to-r from-purple-900 via-indigo-900 to-[#003366] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-white/10 text-purple-200 rounded-full text-xs font-semibold mb-2 border border-white/20">
            <span>{{ isEmpregador ? '🏢 Portal do Empregador Parceiro SEJUS/ES' : '✨ Programa Estadual de Inserção Produtiva e Cidadania' }}</span>
          </div>
          <h1 class="text-2xl font-extrabold font-heading">
            {{ activeTab === 'inserir-vaga' ? 'Inserir Vaga de Emprego' : t('oportunidades_title') }}
          </h1>
          <p class="text-xs md:text-sm text-slate-200 mt-1 max-w-xl">
            {{ isEmpregador
              ? 'Cadastre novas oportunidades de trabalho, baixe a planilha modelo CSV e importe vagas em lote vinculadas aos conhecimentos do sistema.'
              : 'Vagas de emprego com cotas afirmativas SEJUS, cursos gratuitos de qualificação e encaminhamento profissional nos 78 municípios capixabas.'
            }}
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
          <button
            v-if="canManageVagas"
            type="button"
            id="btn-abrir-inserir-vaga"
            class="px-4 py-3 bg-amber-500 hover:bg-amber-600 text-slate-950 font-extrabold text-xs rounded-xl shadow-md transition flex items-center gap-2 cursor-pointer"
            @click="activeTab = 'inserir-vaga'"
          >
            <span>➕ Inserir Vaga</span>
          </button>

          <div class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-xl border border-white/20 text-center">
            <span class="text-[10px] uppercase font-bold tracking-wider text-purple-200 block">Vagas Abertas</span>
            <strong class="text-2xl font-extrabold text-white font-heading">{{ vagasList.length }}</strong>
          </div>
          <div v-if="!isEmpregador" class="bg-white/10 backdrop-blur-md px-4 py-3 rounded-xl border border-white/20 text-center">
            <span class="text-[10px] uppercase font-bold tracking-wider text-purple-200 block">Cursos Gratuitos</span>
            <strong class="text-2xl font-extrabold text-white font-heading">{{ cursosList.length }}</strong>
          </div>
        </div>
      </div>

      <!-- Navigation Tabs & Filter Bar -->
      <div class="bg-white p-5 rounded-2xl border border-slate-200/80 shadow-xs space-y-3">
        <!-- Category Tabs -->
        <div class="flex items-center gap-2 overflow-x-auto pb-2 border-b border-slate-100 text-xs">
          <span class="text-slate-400 font-bold text-[11px] flex-shrink-0">Painel:</span>
          <button
            v-if="canManageVagas"
            type="button"
            id="tab-inserir-vaga"
            class="px-3.5 py-1.5 rounded-full text-xs font-bold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'inserir-vaga' ? 'bg-amber-500 text-slate-950 shadow-xs' : 'bg-amber-50 text-amber-900 border border-amber-200 hover:bg-amber-100'"
            @click="activeTab = 'inserir-vaga'"
          >
            ➕ Inserir Vaga (CSV / Manual)
          </button>
          <button
            type="button"
            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'vagas' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = 'vagas'"
          >
            💼 Vagas de Emprego ({{ filteredVagas.length }})
          </button>
          <button
            v-if="!isEmpregador"
            type="button"
            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'cursos' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = 'cursos'"
          >
            🎓 Cursos de Capacitação ({{ filteredCursos.length }})
          </button>
          <button
            v-if="!isEmpregador"
            type="button"
            class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition cursor-pointer flex-shrink-0"
            :class="activeTab === 'todos' ? 'bg-purple-700 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200'"
            @click="activeTab = 'todos'"
          >
            Todos ({{ filteredVagas.length + filteredCursos.length }})
          </button>
          <!-- Aba Minhas Inscrições para o Cidadão Egresso -->
          <button
            v-if="!isEmpregador"
            type="button"
            id="tab-minhas-inscricoes"
            class="px-3.5 py-1.5 rounded-full text-xs font-bold transition cursor-pointer flex-shrink-0 flex items-center gap-1.5"
            :class="activeTab === 'minhas-inscricoes' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-emerald-50 text-emerald-800 border border-emerald-200 hover:bg-emerald-100'"
            @click="activeTab = 'minhas-inscricoes'"
          >
            <span>📋 Minhas Inscrições ({{ minhasCandidaturasList.length + meusCursosList.length }})</span>
          </button>
        </div>

        <!-- Search & Filters when browsing Vagas/Cursos -->
        <div v-if="activeTab !== 'inserir-vaga' && activeTab !== 'minhas-inscricoes'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 pt-1">
          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Buscar por Cargo ou Empresa</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">🔍</span>
              <input
                v-model="filters.search"
                type="text"
                placeholder="Ex: Almoxarife, Soldador, Logística..."
                class="w-full pl-8 pr-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
              />
            </div>
          </div>

          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Município do ES</label>
            <select
              v-model="filters.municipio"
              class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
            >
              <option value="">Todos os Municípios</option>
              <option v-for="muni in municipiosOptions" :key="muni.id" :value="muni.nome">{{ muni.nome }}</option>
            </select>
          </div>

          <div>
            <label class="block text-[11px] font-extrabold uppercase text-slate-500 mb-1">Buscar por CEP</label>
            <div class="relative">
              <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">📍</span>
              <input
                v-model="filters.cep"
                type="text"
                maxlength="9"
                placeholder="Ex: 29050-000"
                class="w-full pl-8 pr-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-purple-500 outline-none"
              />
            </div>
            <p v-if="isResolvingCep" class="text-[10px] text-purple-600 font-semibold mt-1">Buscando CEP no ES...</p>
            <div v-else-if="cepResolvedLabel" class="flex items-center justify-between text-[10px] text-emerald-700 font-bold mt-1">
              <span>{{ cepResolvedLabel }}</span>
              <button type="button" @click="filters.cep = ''" class="text-rose-600 hover:underline cursor-pointer">Limpar</button>
            </div>
          </div>

          <div class="flex items-end">
            <label class="flex items-center gap-2 p-2 rounded-lg bg-purple-50 border border-purple-200 text-purple-900 text-xs font-bold w-full cursor-pointer">
              <input
                v-model="filters.somenteAfirmativas"
                type="checkbox"
                class="w-4 h-4 rounded text-purple-600 focus:ring-purple-500"
              />
              <span>Apenas Afirmativas SEJUS</span>
            </label>
          </div>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- PAINEL INSERIR VAGA (R2: CSV Download, CSV Import & Form) -->
      <!-- ========================================================= -->
      <div v-if="canManageVagas && activeTab === 'inserir-vaga'" id="section-inserir-vaga" class="space-y-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
          <!-- Left Column: CSV Download & Import -->
          <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-5">
            <div class="flex items-start justify-between gap-3 border-b border-slate-100 pb-4">
              <div>
                <h2 class="text-base font-extrabold text-slate-900 font-heading flex items-center gap-2">
                  <span>📄 Importação de Vagas via Arquivo CSV</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                  Baixe o modelo CSV oficial, preencha as vagas e realize a importação validada em lote.
                </p>
              </div>
              <a
                href="/vagas/modelo-csv"
                download="modelo_importacao_vagas.csv"
                id="btn-download-modelo-csv"
                class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center gap-1.5 flex-shrink-0"
              >
                <span>⬇️ Baixar CSV Modelo</span>
              </a>
            </div>

            <!-- CSV Layout Specification Box -->
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs space-y-2">
              <div class="flex items-center justify-between">
                <strong class="text-slate-800 block">Estrutura de colunas da planilha:</strong>
                <span class="text-[10px] bg-sky-100 text-sky-800 font-bold px-2 py-0.5 rounded border border-sky-200">100% Autônomo</span>
              </div>
              <ul class="space-y-1 text-[11px] text-slate-600 list-disc pl-4">
                <li><strong>Nome vaga:</strong> Título ou cargo da vaga (ex: <em>Auxiliar de Logística</em>)</li>
                <li><strong>Descrição vaga:</strong> Atribuições e requisitos da oportunidade</li>
                <li><strong>Quantidade vaga:</strong> Número de vagas (ex: <em>3</em>)</li>
                <li><strong>Data início:</strong> <code>imediato</code> ou data (<em>DD/MM/AAAA</em>)</li>
                <li><strong>Data fim:</strong> <code>permanente</code> ou data (<em>DD/MM/AAAA</em>)</li>
                <li><strong>Conhecimento:</strong> Habilidades requeridas (ex: <em>Soldagem | Eletricista</em>)</li>
                <li><strong>Município / CEP (Opcional):</strong> Nome da cidade (ex: <em>Linhares</em>) ou CEP (ex: <em>29900-000</em>). Permite cidades diferentes em cada linha!</li>
              </ul>
              <p class="text-[11px] text-emerald-700 font-semibold bg-emerald-50 p-2 rounded-lg border border-emerald-200">
                💡 Importando via CSV, todas as vagas são cadastradas de uma só vez. Não precisa preencher o formulário manual ao lado!
              </p>
            </div>

            <!-- CSV Upload Form -->
            <form @submit.prevent="handleCsvImport" class="space-y-3" id="form-importar-csv">
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Selecionar Arquivo CSV (.csv) <span class="text-rose-500">*</span>
                </label>
                <input
                  ref="csvFileInput"
                  id="input-importar-csv"
                  type="file"
                  accept=".csv,text/csv"
                  @change="onCsvFileSelected"
                  class="w-full text-xs text-slate-700 file:mr-3 file:py-2 file:px-3.5 file:rounded-lg file:border-0 file:text-xs file:font-bold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-slate-300 rounded-xl p-1.5 bg-white cursor-pointer"
                />
              </div>

              <div v-if="municipiosOptions.length > 0">
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Município Padrão das Vagas Importadas
                </label>
                <select
                  v-model="csvMunicipioId"
                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
                >
                  <option value="">Município do Empregador / Vitória (Padrão)</option>
                  <option v-for="m in municipiosOptions" :key="m.id" :value="m.id">{{ m.nome }}</option>
                </select>
              </div>

              <button
                type="submit"
                id="btn-importar-csv"
                :disabled="isImportingCsv || !selectedCsvFile"
                class="w-full py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed"
              >
                <span v-if="isImportingCsv" class="animate-spin">⏳</span>
                <span>{{ isImportingCsv ? 'Validando e Importando CSV...' : '📤 Importar Arquivo CSV' }}</span>
              </button>
            </form>

            <!-- Import Validation / Success Feedback Box -->
            <div
              v-if="importResult"
              id="csv-import-feedback"
              class="p-4 rounded-xl border text-xs space-y-2"
              :class="importResult.ok ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : 'bg-rose-50 border-rose-200 text-rose-900'"
            >
              <div class="font-extrabold flex items-center gap-1.5">
                <span>{{ importResult.ok ? '✅' : '⚠️' }}</span>
                <span>{{ importResult.message }}</span>
              </div>

              <div v-if="importResult.erros && importResult.erros.length > 0" class="space-y-1 pt-1">
                <p class="text-[11px] font-bold">Detalhes para correção:</p>
                <ul class="list-disc pl-4 space-y-0.5 text-[11px] max-h-40 overflow-y-auto">
                  <li v-for="(err, idx) in importResult.erros" :key="idx">
                    {{ err.erro || `Linha ${err.linha} (${err.campo}): valor inválido` }}
                  </li>
                </ul>
              </div>
            </div>

            <!-- System Conhecimentos Reference List for CSV -->
            <div class="pt-3 border-t border-slate-100 space-y-2">
              <div class="flex items-center justify-between">
                <span class="text-xs font-extrabold text-slate-800">
                  📋 Listagem de Conhecimentos Cadastrados no Sistema ({{ catalogoList.length }})
                </span>
                <span class="text-[10px] text-slate-400">Clique para adicionar ao formulário</span>
              </div>
              <div class="flex flex-wrap gap-1.5 max-h-40 overflow-y-auto p-2 bg-slate-50 rounded-xl border border-slate-200/80" id="catalogo-conhecimentos-sistema">
                <button
                  v-for="skill in catalogoList"
                  :key="skill.id"
                  type="button"
                  @click="toggleManualSkill(skill.id)"
                  class="text-[11px] px-2.5 py-1 rounded-lg border font-medium transition cursor-pointer"
                  :class="manualForm.conhecimentos.includes(skill.id)
                    ? 'bg-indigo-600 text-white border-indigo-700'
                    : 'bg-white text-slate-700 border-slate-200 hover:border-indigo-300'"
                >
                  {{ skill.nome }}
                </button>
              </div>
            </div>
          </div>

          <!-- Right Column: Manual Job Insertion / Edit Form -->
          <div class="lg:col-span-6 bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
            <div class="border-b border-slate-100 pb-4 flex items-start justify-between gap-2">
              <div>
                <h2 class="text-base font-extrabold text-slate-900 font-heading flex items-center gap-2">
                  <span>{{ editingVagaId ? '✏️ Editar Vaga de Emprego' : '✍️ Cadastrar Nova Vaga Manualmente' }}</span>
                </h2>
                <p class="text-xs text-slate-500 mt-1">
                  Preencha os dados da oportunidade e selecione os conhecimentos requeridos da listagem do sistema.
                </p>
              </div>
              <button
                v-if="editingVagaId"
                type="button"
                @click="cancelVagaEdit"
                class="px-2.5 py-1 text-[11px] font-bold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition cursor-pointer"
              >
                Cancelar Edição
              </button>
            </div>

            <form @submit.prevent="handleManualSubmit" class="space-y-3.5" id="form-inserir-vaga-manual">
              <div class="grid grid-cols-1 sm:grid-cols-12 gap-3">
                <div class="sm:col-span-6">
                  <label class="block text-xs font-bold text-slate-700 mb-1">
                    Nome da vaga <span class="text-rose-500">*</span>
                  </label>
                  <input
                    v-model="manualForm.nome_vaga"
                    type="text"
                    required
                    maxlength="150"
                    placeholder="Ex: Oficial Eletricista de Manutenção"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>

                <div class="sm:col-span-4">
                  <label class="block text-xs font-bold text-slate-700 mb-1">
                    Empresa / Empregador
                  </label>
                  <input
                    v-model="manualForm.empresa"
                    type="text"
                    maxlength="150"
                    placeholder="Ex: Construtora Capixaba Ltda"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>

                <div class="sm:col-span-2">
                  <label class="block text-xs font-bold text-slate-700 mb-1">
                    Qtd. <span class="text-rose-500">*</span>
                  </label>
                  <input
                    v-model.number="manualForm.quantidade_vaga"
                    type="number"
                    min="1"
                    max="10000"
                    required
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>
              </div>

              <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Salário Base (R$)</label>
                  <input
                    v-model="manualForm.salario"
                    type="number"
                    step="0.01"
                    min="0"
                    placeholder="Ex: 2100.00"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Regime</label>
                  <select
                    v-model="manualForm.regime_contratacao"
                    class="w-full px-2.5 py-2 text-xs border border-slate-300 rounded-lg bg-white outline-none"
                  >
                    <option value="CLT">CLT</option>
                    <option value="Temporário">Temporário</option>
                    <option value="Jovem Aprendiz">Estágio / Jovem Aprendiz</option>
                    <option value="PJ / MEI">PJ / MEI</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Área / Categoria</label>
                  <select
                    v-model="manualForm.categoria"
                    class="w-full px-2.5 py-2 text-xs border border-slate-300 rounded-lg bg-white outline-none"
                  >
                    <option value="geral">Geral</option>
                    <option value="construcao_civil">Construção Civil</option>
                    <option value="logistica">Logística & Estoque</option>
                    <option value="servicos">Serviços Gerais</option>
                    <option value="industria">Indústria & Manutenção</option>
                    <option value="administrativo">Administrativo</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-bold text-slate-700 mb-1">Escolaridade Mínima</label>
                  <input
                    v-model="manualForm.escolaridade_minima"
                    type="text"
                    maxlength="100"
                    placeholder="Ex: Fundamental Completo"
                    class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                  />
                </div>
              </div>

              <label class="inline-flex items-center gap-2 text-xs font-bold text-purple-900 bg-purple-50 border border-purple-200 px-3 py-2 rounded-lg cursor-pointer">
                <input v-model="manualForm.afirmativa_egresso" type="checkbox" class="w-4 h-4 rounded text-purple-600" />
                <span>Cota Afirmativa SEJUS (Prioridade para Egressos e Reintegração Social)</span>
              </label>

              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Descrição da vaga <span class="text-rose-500">*</span>
                </label>
                <textarea
                  v-model="manualForm.descricao"
                  rows="3"
                  required
                  placeholder="Descreva as atividades, horário de trabalho e benefícios oferecidos..."
                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg focus:ring-2 focus:ring-indigo-500 outline-none"
                ></textarea>
              </div>

              <!-- Data início & Data fim -->
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                  <label class="block text-xs font-bold text-slate-700">
                    Data de início <span class="text-rose-500">*</span>
                  </label>
                  <select
                    v-model="manualForm.tipo_inicio"
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white outline-none"
                  >
                    <option value="imediato">Imediato</option>
                    <option value="data">Data específica</option>
                  </select>
                  <input
                    v-if="manualForm.tipo_inicio === 'data'"
                    v-model="manualForm.data_inicio"
                    type="date"
                    required
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white outline-none"
                  />
                </div>

                <div class="p-3 bg-slate-50 rounded-xl border border-slate-200 space-y-2">
                  <label class="block text-xs font-bold text-slate-700">
                    Data de fim <span class="text-rose-500">*</span>
                  </label>
                  <select
                    v-model="manualForm.tipo_fim"
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white outline-none"
                  >
                    <option value="permanente">Permanente</option>
                    <option value="data">Data específica</option>
                  </select>
                  <input
                    v-if="manualForm.tipo_fim === 'data'"
                    v-model="manualForm.data_fim"
                    type="date"
                    required
                    class="w-full px-2.5 py-1.5 text-xs border border-slate-300 rounded-lg bg-white outline-none"
                  />
                </div>
              </div>

              <!-- Município -->
              <div v-if="municipiosOptions.length > 0">
                <label class="block text-xs font-bold text-slate-700 mb-1">Município de Atuação</label>
                <select
                  v-model="manualForm.municipio_id"
                  class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-indigo-500 outline-none"
                >
                  <option value="">Vitória / Município do Empregador</option>
                  <option v-for="m in municipiosOptions" :key="m.id" :value="m.id">{{ m.nome }}</option>
                </select>
              </div>

              <!-- Conhecimentos do Sistema (Multi-Select) -->
              <div>
                <label class="block text-xs font-bold text-slate-700 mb-1">
                  Conhecimentos Requeridos (Listagem do Sistema)
                </label>
                <div class="max-h-40 overflow-y-auto p-2.5 rounded-xl border border-slate-300 bg-slate-50/60 space-y-1.5">
                  <label
                    v-for="skill in catalogoList"
                    :key="skill.id"
                    class="flex items-center gap-2 text-xs text-slate-700 cursor-pointer hover:bg-white p-1 rounded"
                  >
                    <input
                      type="checkbox"
                      :value="skill.id"
                      v-model="manualForm.conhecimentos"
                      class="w-3.5 h-3.5 rounded text-indigo-600 focus:ring-indigo-500"
                    />
                    <span>{{ skill.nome }}</span>
                    <span v-if="skill.categoria" class="text-[10px] text-slate-400">({{ skill.categoria }})</span>
                  </label>
                </div>
              </div>

              <button
                type="submit"
                id="btn-salvar-vaga-manual"
                :disabled="isSavingManual"
                class="w-full py-2.5 bg-purple-700 hover:bg-purple-800 text-white font-bold text-xs rounded-xl shadow-xs transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
              >
                <span v-if="isSavingManual" class="animate-spin">⏳</span>
                <span>{{ isSavingManual ? 'Salvando Vaga...' : (editingVagaId ? '💾 Salvar Alterações na Vaga' : '✅ Publicar Vaga de Emprego') }}</span>
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- Vagas de Emprego Grid -->
      <div v-if="activeTab === 'todos' || activeTab === 'vagas'" class="space-y-4">
        <div class="flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>💼 Vagas de Trabalho Disponíveis</span>
            <span class="text-xs font-normal text-slate-500">({{ filteredVagas.length }} encontradas)</span>
          </h2>
        </div>

        <div v-if="filteredVagas.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center text-xs text-slate-500">
          Nenhuma vaga encontrada com os filtros selecionados.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="vaga in filteredVagas"
            :key="vaga.id"
            class="vaga-card bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition flex flex-col justify-between"
          >
            <div>
              <div class="flex items-start justify-between gap-2 mb-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ vaga.empresa }}</span>
                <span
                  v-if="vaga.afirmativa_egresso"
                  class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-purple-100 text-purple-800 border border-purple-200"
                >
                  Cota SEJUS
                </span>
              </div>

              <h3 class="text-sm font-extrabold text-slate-900 leading-snug">
                {{ vaga.titulo }}
              </h3>

              <div class="flex flex-wrap items-center gap-2 my-2 text-xs font-semibold text-slate-600">
                <span>📍 {{ vaga.municipioNome }}</span>
                <template v-if="vaga.salario !== null && vaga.salario > 0">
                  <span>•</span>
                  <span class="text-emerald-700 font-bold">R$ {{ Number(vaga.salario).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}</span>
                </template>
                <span>•</span>
                <span>{{ vaga.regime_contratacao || 'CLT' }}</span>
              </div>

              <!-- Vigência da Vaga (Início / Fim) -->
              <div v-if="vaga.data_inicio_formatada || vaga.data_fim_formatada" class="text-[11px] text-slate-500 mb-2 flex flex-wrap gap-2">
                <span v-if="vaga.data_inicio_formatada" class="bg-slate-100 px-2 py-0.5 rounded">
                  Início: <strong>{{ vaga.data_inicio_formatada }}</strong>
                </span>
                <span v-if="vaga.data_fim_formatada" class="bg-slate-100 px-2 py-0.5 rounded">
                  Vigência: <strong>{{ vaga.data_fim_formatada }}</strong>
                </span>
              </div>

              <p class="text-xs text-slate-600 line-clamp-2 mb-3">
                {{ vaga.descricao }}
              </p>

              <!-- Conhecimentos associados -->
              <div v-if="vaga.conhecimentos && vaga.conhecimentos.length > 0" class="flex flex-wrap gap-1 mb-3">
                <span
                  v-for="c in vaga.conhecimentos"
                  :key="c.id"
                  class="text-[10px] bg-indigo-50 text-indigo-700 border border-indigo-200 px-2 py-0.5 rounded-full font-semibold"
                >
                  🛠️ {{ c.nome }}
                </span>
              </div>

              <div v-if="vaga.beneficios && vaga.beneficios.length > 0" class="flex flex-wrap gap-1 mb-4">
                <span v-for="b in vaga.beneficios" :key="b" class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded font-medium">
                  {{ b }}
                </span>
              </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
              <span class="text-[11px] text-slate-500 font-semibold">
                {{ Math.max(0, (vaga.vagas_totais || 1) - (vaga.vagas_preenchidas || 0)) }} vagas disponíveis
              </span>
              <div class="flex items-center gap-1.5">
                <button
                  v-if="canManageVagas"
                  type="button"
                  @click="openVagaEditor(vaga)"
                  class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-lg transition cursor-pointer"
                >
                  ✏️ Editar
                </button>
                <button
                  v-if="canManageVagas"
                  type="button"
                  @click="toggleVagaStatus(vaga)"
                  class="px-2.5 py-1.5 bg-amber-50 hover:bg-amber-100 text-amber-800 border border-amber-200 text-[11px] font-bold rounded-lg transition cursor-pointer"
                >
                  {{ vaga.status === 'aberta' ? '🔒 Encerrar' : '🔓 Reabrir' }}
                </button>
                <button
                  v-if="!isEmpregador && hasAppliedToVaga(vaga.id)"
                  type="button"
                  disabled
                  class="px-4 py-2 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-300 flex items-center gap-1 opacity-90 cursor-default"
                >
                  <span>✓ Já Candidatado</span>
                </button>
                <button
                  v-else-if="!isEmpregador"
                  type="button"
                  class="px-4 py-2 bg-purple-700 hover:bg-purple-800 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer"
                  @click="openApplicationModal(vaga)"
                >
                  <span>Candidatar-se</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Cursos de Capacitação Grid -->
      <div v-if="!isEmpregador && (activeTab === 'todos' || activeTab === 'cursos')" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>🎓 Cursos Gratuitos de Qualificação (SENAI / IFES / SEJUS)</span>
            <span class="text-xs font-normal text-slate-500">({{ filteredCursos.length }} encontrados)</span>
          </h2>
          <button
            v-if="isStaff"
            type="button"
            @click="openCourseEditor()"
            class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
          >
            ➕ Cadastrar Curso
          </button>
        </div>

        <div v-if="filteredCursos.length === 0" class="bg-white rounded-2xl border border-slate-200/80 p-8 text-center text-xs text-slate-500">
          Nenhum curso de capacitação encontrado com os filtros aplicados.
        </div>

        <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="curso in filteredCursos"
            :key="curso.id"
            class="curso-card bg-white rounded-2xl border border-slate-200/80 p-5 shadow-xs hover:shadow-md transition flex flex-col justify-between"
          >
            <div>
              <div class="flex items-start justify-between gap-2 mb-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">{{ curso.instituicao }}</span>
                <span class="text-[10px] font-extrabold px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-800 border border-emerald-200">
                  100% Gratuito
                </span>
              </div>

              <h3 class="text-sm font-extrabold text-slate-900 leading-snug">
                {{ curso.titulo }}
              </h3>

              <div class="flex items-center gap-2 my-2 text-xs font-semibold text-slate-600">
                <span>📍 {{ curso.municipioNome || 'Estadual / EAD' }}</span>
                <span>•</span>
                <span>⏱️ {{ curso.carga_horaria }}h</span>
                <span>•</span>
                <span class="text-purple-700 font-bold">{{ curso.modalidade }}</span>
              </div>

              <p class="text-xs text-slate-600 line-clamp-2 mb-3">
                {{ curso.descricao }}
              </p>

              <div v-if="curso.bolsa_auxilio > 0" class="p-2 rounded-lg bg-emerald-50 text-emerald-800 text-xs font-bold mb-4 flex items-center gap-1.5">
                <span>💰 Bolsa-Auxílio: R$ {{ Number(curso.bolsa_auxilio).toFixed(2) }}/mês</span>
              </div>
            </div>

            <div class="pt-3 border-t border-slate-100 flex flex-wrap items-center justify-between gap-2">
              <span class="text-[11px] text-slate-400 font-medium">
                {{ curso.vagas_disponiveis }} vagas abertas
              </span>
              <div class="flex items-center gap-1.5">
                <button
                  v-if="isStaff"
                  type="button"
                  @click="openCourseEditor(curso)"
                  class="px-2.5 py-1.5 bg-slate-100 hover:bg-slate-200 text-slate-700 text-[11px] font-bold rounded-lg transition cursor-pointer"
                >
                  ✏️ Editar
                </button>
                <button
                  v-if="hasEnrolledInCourse(curso.id)"
                  type="button"
                  disabled
                  class="px-4 py-2 bg-emerald-100 text-emerald-800 text-xs font-bold rounded-xl border border-emerald-300 flex items-center gap-1 opacity-90 cursor-default"
                >
                  <span>✓ Já Inscrito</span>
                </button>
                <button
                  v-else
                  type="button"
                  class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center gap-1 cursor-pointer"
                  @click="openCourseModal(curso)"
                >
                  <span>Inscrever-se</span>
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================================= -->
      <!-- ABA MINHAS INSCRIÇÕES & CANDIDATURAS (Cidadão Egresso)    -->
      <!-- ========================================================= -->
      <div v-if="!isEmpregador && activeTab === 'minhas-inscricoes'" class="space-y-6">
        <!-- Vagas Candidatadas -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
              <span>💼 Vagas de Emprego onde me Candidatei</span>
              <span class="text-xs font-normal text-slate-500">({{ minhasCandidaturasList.length }} registradas)</span>
            </h2>
            <span class="text-[11px] text-emerald-700 bg-emerald-50 px-2.5 py-0.5 rounded-full font-bold border border-emerald-200">
              Protocolo Oficial SEJUS
            </span>
          </div>

          <div v-if="minhasCandidaturasList.length === 0" class="text-center py-8 text-xs text-slate-400">
            Você ainda não realizou candidaturas em vagas de emprego. Navegue na aba "Vagas de Emprego" e candidate-se!
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="item in minhasCandidaturasList"
              :key="item.id"
              class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white hover:shadow-xs transition space-y-2"
            >
              <div class="flex items-start justify-between gap-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase">{{ item.empresa }}</span>
                <span class="text-[10px] bg-sky-100 text-sky-800 font-bold px-2 py-0.5 rounded-full border border-sky-200">
                  {{ item.status }}
                </span>
              </div>
              <h3 class="text-sm font-extrabold text-slate-900 leading-snug">{{ item.titulo }}</h3>
              <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>📍 {{ item.municipio_nome }}</span>
                <span v-if="item.salario">•</span>
                <span v-if="item.salario" class="text-emerald-700 font-bold">R$ {{ Number(item.salario).toLocaleString('pt-BR', { minimumFractionDigits: 2 }) }}</span>
              </div>
              <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400">
                <span>Data do Encaminhamento:</span>
                <strong class="text-slate-700">{{ item.data_evento }}</strong>
              </div>
            </div>
          </div>
        </div>

        <!-- Cursos Inscritos -->
        <div class="bg-white rounded-2xl border border-slate-200/80 p-6 shadow-xs space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
              <span>🎓 Cursos e Qualificações onde estou Inscrito</span>
              <span class="text-xs font-normal text-slate-500">({{ meusCursosList.length }} matriculados)</span>
            </h2>
            <span class="text-[11px] text-purple-700 bg-purple-50 px-2.5 py-0.5 rounded-full font-bold border border-purple-200">
              SENAI / IFES / SEJUS
            </span>
          </div>

          <div v-if="meusCursosList.length === 0" class="text-center py-8 text-xs text-slate-400">
            Você ainda não se inscreveu em cursos gratuitos. Acesse a aba "Cursos de Capacitação" para se qualificar!
          </div>

          <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div
              v-for="item in meusCursosList"
              :key="item.id"
              class="p-4 rounded-xl border border-slate-200 bg-slate-50/50 hover:bg-white hover:shadow-xs transition space-y-2"
            >
              <div class="flex items-start justify-between gap-2">
                <span class="text-[11px] font-bold text-slate-500 uppercase">{{ item.instituicao }}</span>
                <span class="text-[10px] bg-emerald-100 text-emerald-800 font-bold px-2 py-0.5 rounded-full border border-emerald-200">
                  {{ item.status }}
                </span>
              </div>
              <h3 class="text-sm font-extrabold text-slate-900 leading-snug">{{ item.titulo }}</h3>
              <div class="flex items-center gap-2 text-xs text-slate-500">
                <span>⏱️ {{ item.carga_horaria }}h</span>
                <span>•</span>
                <span class="text-purple-700 font-semibold">{{ item.modalidade }}</span>
              </div>
              <div class="pt-2 border-t border-slate-200/60 flex items-center justify-between text-[11px] text-slate-400">
                <span>Inscrição Confirmada em:</span>
                <strong class="text-slate-700">{{ item.data_evento }}</strong>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Application Confirmation Modal -->
      <div v-if="selectedOpportunity" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
          <div class="flex items-center gap-2 mb-2">
            <span class="text-2xl">✉️</span>
            <h3 class="text-base font-extrabold text-slate-900">Confirmar Inscrição</h3>
          </div>

          <p class="text-xs text-slate-600 mb-4">
            Você está se candidatando para a oportunidade: <br />
            <strong class="text-slate-900 text-sm block mt-1">{{ selectedOpportunity.titulo }}</strong>
            <span class="text-slate-500 text-xs block">Instituição / Empresa: {{ selectedOpportunity.empresa || selectedOpportunity.instituicao }} ({{ selectedOpportunity.municipioNome || 'ES' }})</span>
          </p>

          <div v-if="isStaff" class="mb-4">
            <label class="block text-xs font-bold text-slate-700 mb-1">
              Selecionar Egresso para Encaminhamento <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="selectedEgressoId"
              class="w-full px-3 py-2 text-xs border border-slate-300 rounded-lg bg-white outline-none focus:ring-2 focus:ring-purple-500"
            >
              <option value="" disabled>Escolha um egresso cadastrado...</option>
              <option v-for="eg in egressosList" :key="eg.id" :value="eg.id">{{ eg.nome }}</option>
            </select>
          </div>

          <div class="p-3 bg-purple-50 rounded-xl border border-purple-200 text-xs text-purple-900 mb-4 space-y-1">
            <p class="font-bold">Protocolo de Encaminhamento SEJUS:</p>
            <p>Seus dados cadastrais básicos e o comprovante da Carteira Digital serão compartilhados de forma segura conforme a LGPD.</p>
          </div>

          <div class="flex justify-end gap-2">
            <button type="button" @click="selectedOpportunity = null" class="px-4 py-2 text-xs font-bold text-slate-600 hover:bg-slate-100 rounded-lg transition">Cancelar</button>
            <button type="button" @click="confirmApplication" class="px-4 py-2 bg-purple-700 text-white text-xs font-bold rounded-lg hover:bg-purple-800 transition">Confirmar Encaminhamento</button>
          </div>
        </div>
      </div>

      <!-- Course Creation & Editing Modal (Staff) -->
      <div v-if="isStaff && courseModal.open" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-xs">
        <div class="bg-white rounded-2xl max-w-lg w-full p-6 shadow-2xl border border-slate-200 max-h-[90vh] overflow-y-auto space-y-4">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <h3 class="text-base font-extrabold text-slate-900">
              {{ courseModal.id ? '✏️ Editar Curso de Capacitação' : '🎓 Cadastrar Novo Curso / Qualificação' }}
            </h3>
            <button type="button" @click="courseModal.open = false" class="text-slate-400 hover:text-slate-600 font-bold text-sm">✕</button>
          </div>

          <form @submit.prevent="handleCourseSubmit" class="space-y-3 text-xs">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Instituição Parceira *</label>
                <input v-model="courseModal.instituicao" type="text" required placeholder="Ex: SENAI / IFES" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Categoria *</label>
                <input v-model="courseModal.categoria" type="text" required placeholder="Ex: Construção Civil" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500" />
              </div>
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Título do Curso *</label>
              <input v-model="courseModal.titulo" type="text" required placeholder="Ex: Instalador Hidráulico e Elétrico" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500" />
            </div>

            <div>
              <label class="block font-bold text-slate-700 mb-1">Ementa / Descrição *</label>
              <textarea v-model="courseModal.descricao" rows="2" required class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Carga (h) *</label>
                <input v-model.number="courseModal.carga_horaria" type="number" min="1" required class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Modalidade *</label>
                <select v-model="courseModal.modalidade" class="w-full px-2.5 py-2 border border-slate-300 rounded-lg bg-white outline-none">
                  <option value="presencial">Presencial</option>
                  <option value="ead">EAD</option>
                  <option value="hibrido">Híbrido</option>
                </select>
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Vagas *</label>
                <input v-model.number="courseModal.vagas_disponiveis" type="number" min="1" required class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none" />
              </div>
              <div>
                <label class="block font-bold text-slate-700 mb-1">Bolsa (R$)</label>
                <input v-model="courseModal.bolsa_auxilio" type="number" step="0.01" min="0" placeholder="0.00" class="w-full px-3 py-2 border border-slate-300 rounded-lg outline-none" />
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label class="block font-bold text-slate-700 mb-1">Município</label>
                <select v-model="courseModal.municipio_id" class="w-full px-2.5 py-2 border border-slate-300 rounded-lg bg-white outline-none">
                  <option value="">Estadual / EAD</option>
                  <option v-for="m in municipiosOptions" :key="m.id" :value="m.id">{{ m.nome }}</option>
                </select>
              </div>
              <div class="flex items-end pb-1.5">
                <label class="flex items-center gap-2 font-bold text-slate-700 cursor-pointer">
                  <input v-model="courseModal.afirmativa_egresso" type="checkbox" class="w-4 h-4 rounded text-emerald-600" />
                  <span>Reserva de Vagas Afirmativas</span>
                </label>
              </div>
            </div>

            <div class="flex justify-end gap-2 pt-3 border-t border-slate-100">
              <button type="button" @click="courseModal.open = false" class="px-4 py-2 font-bold text-slate-600 hover:bg-slate-100 rounded-lg">Cancelar</button>
              <button type="submit" :disabled="isSavingCourse" class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-lg disabled:opacity-50">
                {{ isSavingCourse ? 'Salvando...' : (courseModal.id ? 'Salvar Alterações' : 'Publicar Curso') }}
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useAccessibility } from '../Composables/useAccessibility';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  vagas: {
    type: Array,
    default: () => [],
  },
  cursos: {
    type: Array,
    default: () => [],
  },
  municipios: {
    type: Array,
    default: () => [],
  },
  catalogoConhecimentos: {
    type: Array,
    default: () => [],
  },
  initialTab: {
    type: String,
    default: 'todos',
  },
  minhasCandidaturas: {
    type: Array,
    default: () => [],
  },
  meusCursos: {
    type: Array,
    default: () => [],
  },
  egressosList: {
    type: Array,
    default: () => [],
  },
});

const page = usePage();
const { t } = useAccessibility();
const toast = useToast();

const userRole = computed(() => page.props.auth?.user?.role || 'egresso');
const isEmpregador = computed(() => userRole.value === 'empregador');
const isStaff = computed(() => ['gestor', 'tecnico', 'suporte'].includes(userRole.value));
const canManageVagas = computed(() =>
  ['empregador', 'gestor', 'suporte', 'tecnico'].includes(userRole.value)
);

const activeTab = ref(
  props.initialTab || (isEmpregador.value ? 'inserir-vaga' : 'todos')
);
const selectedOpportunity = ref(null);

const filters = ref({
  search: '',
  municipio: '',
  cep: '',
  somenteAfirmativas: false,
});

const minhasCandidaturasList = ref([...(props.minhasCandidaturas || [])]);
const meusCursosList = ref([...(props.meusCursos || [])]);

const hasAppliedToVaga = (vagaId) => {
  return minhasCandidaturasList.value.some(item => Number(item.vaga_id) === Number(vagaId));
};

const hasEnrolledInCourse = (cursoId) => {
  return meusCursosList.value.some(item => Number(item.curso_id) === Number(cursoId));
};

const normalizeVaga = (v) => ({
  ...v,
  municipioNome: typeof v.municipio === 'string' ? v.municipio : (v.municipio?.nome || 'Vitória'),
  data_inicio_formatada: v.data_inicio_formatada || (v.inicio_imediato ? 'Imediato' : v.data_inicio),
  data_fim_formatada: v.data_fim_formatada || (v.fim_permanente ? 'Permanente' : v.data_fim),
  conhecimentos: Array.isArray(v.conhecimentos) ? v.conhecimentos : [],
  beneficios: Array.isArray(v.beneficios) ? v.beneficios : [],
});

const normalizeCurso = (c) => ({
  ...c,
  municipioNome: typeof c.municipio === 'string' ? c.municipio : (c.municipio?.nome || 'Estadual / EAD'),
});

const vagasList = ref((props.vagas || []).map(normalizeVaga));
const cursosList = ref((props.cursos || []).map(normalizeCurso));
const municipiosOptions = computed(() => props.municipios || []);
const catalogoList = ref([...(props.catalogoConhecimentos || [])]);

// CSV Import State
const csvFileInput = ref(null);
const selectedCsvFile = ref(null);
const csvMunicipioId = ref('');
const isImportingCsv = ref(false);
const importResult = ref(null);

// Manual Creation & Editing Form State
const isSavingManual = ref(false);
const editingVagaId = ref(null);
const defaultManualForm = () => ({
  empresa: '',
  nome_vaga: '',
  descricao: '',
  categoria: 'geral',
  salario: '',
  regime_contratacao: 'CLT',
  escolaridade_minima: '',
  afirmativa_egresso: true,
  quantidade_vaga: 1,
  tipo_inicio: 'imediato',
  data_inicio: '',
  tipo_fim: 'permanente',
  data_fim: '',
  municipio_id: '',
  conhecimentos: [],
});
const manualForm = ref(defaultManualForm());

// CEP Filter State
const cepMatchedIds = ref(null);
const cepResolvedLabel = ref('');
const isResolvingCep = ref(false);

// Staff Egresso Selector inside Application Confirmation Modal
const selectedEgressoId = ref('');

// Course Modal State (Staff CRUD)
const isSavingCourse = ref(false);
const courseModal = ref({
  open: false,
  id: null,
  instituicao: '',
  titulo: '',
  descricao: '',
  categoria: 'Qualificação Profissional',
  carga_horaria: 40,
  modalidade: 'presencial',
  vagas_disponiveis: 20,
  municipio_id: '',
  bolsa_auxilio: '',
  afirmativa_egresso: true,
  status: 'aberto',
});

const toggleManualSkill = (skillId) => {
  const idx = manualForm.value.conhecimentos.indexOf(skillId);
  if (idx >= 0) {
    manualForm.value.conhecimentos.splice(idx, 1);
  } else {
    manualForm.value.conhecimentos.push(skillId);
  }
};

const onCsvFileSelected = (event) => {
  const file = event.target.files?.[0] || null;
  selectedCsvFile.value = file;
  importResult.value = null;
};

const getCsrfToken = () => {
  if (typeof document === 'undefined') return null;
  return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || null;
};

watch(() => filters.value.cep, async (val) => {
  const digits = String(val || '').replace(/\D/g, '');
  if (digits.length === 0) {
    cepMatchedIds.value = null;
    cepResolvedLabel.value = '';
    return;
  }
  if (digits.length < 8) {
    return;
  }

  isResolvingCep.value = true;
  try {
    const res = await fetch(`/api/vagas?cep=${digits}&per_page=100`, {
      headers: { Accept: 'application/json' },
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok && Array.isArray(data.data)) {
      cepMatchedIds.value = data.data.map(v => Number(v.id));
      const firstCity = data.data[0]?.municipio?.nome;
      cepResolvedLabel.value = firstCity
        ? `Município: ${firstCity} (${cepMatchedIds.value.length} vagas)`
        : `${cepMatchedIds.value.length} vaga(s) para o CEP`;
    } else {
      cepMatchedIds.value = null;
      cepResolvedLabel.value = '';
    }
  } catch {
    cepMatchedIds.value = null;
    cepResolvedLabel.value = '';
  } finally {
    isResolvingCep.value = false;
  }
});

const openVagaEditor = (vaga) => {
  editingVagaId.value = vaga.id;
  manualForm.value = {
    empresa: vaga.empresa || '',
    nome_vaga: vaga.titulo || '',
    descricao: vaga.descricao || '',
    categoria: vaga.categoria || 'geral',
    salario: vaga.salario ?? '',
    regime_contratacao: vaga.regime_contratacao || 'CLT',
    escolaridade_minima: vaga.escolaridade_minima || '',
    afirmativa_egresso: vaga.afirmativa_egresso !== false,
    quantidade_vaga: vaga.vagas_totais || 1,
    tipo_inicio: vaga.inicio_imediato ? 'imediato' : 'data',
    data_inicio: vaga.data_inicio ? String(vaga.data_inicio).slice(0, 10) : '',
    tipo_fim: vaga.fim_permanente !== false && !vaga.data_fim ? 'permanente' : 'data',
    data_fim: vaga.data_fim ? String(vaga.data_fim).slice(0, 10) : '',
    municipio_id: vaga.municipio_id || vaga.municipio?.id || '',
    conhecimentos: Array.isArray(vaga.conhecimentos) ? vaga.conhecimentos.map(c => c.id) : [],
  };
  activeTab.value = 'inserir-vaga';
};

const cancelVagaEdit = () => {
  editingVagaId.value = null;
  manualForm.value = defaultManualForm();
};

const toggleVagaStatus = async (vaga) => {
  const newStatus = vaga.status === 'aberta' ? 'pausada' : 'aberta';
  const csrfToken = getCsrfToken();
  try {
    const res = await fetch(`/api/vagas/${vaga.id}`, {
      method: 'PUT',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify({ status: newStatus }),
    });
    const data = await res.json().catch(() => ({}));
    if (res.ok) {
      const idx = vagasList.value.findIndex(item => item.id === vaga.id);
      if (idx >= 0) {
        if (newStatus === 'pausada') {
          vagasList.value.splice(idx, 1);
        } else if (data.data) {
          vagasList.value[idx] = normalizeVaga(data.data);
        }
      }
      toast.success('Status Atualizado', `Vaga "${vaga.titulo}" marcada como ${newStatus}.`);
    } else {
      toast.error('Falha', data.error || 'Não foi possível atualizar o status da vaga.');
    }
  } catch {
    toast.error('Erro de Conexão', 'Falha ao atualizar status da vaga.');
  }
};

const openCourseEditor = (curso = null) => {
  if (curso) {
    courseModal.value = {
      open: true,
      id: curso.id,
      instituicao: curso.instituicao || '',
      titulo: curso.titulo || '',
      descricao: curso.descricao || '',
      categoria: curso.categoria || 'Qualificação Profissional',
      carga_horaria: curso.carga_horaria || 40,
      modalidade: String(curso.modalidade || 'presencial').toLowerCase(),
      vagas_disponiveis: curso.vagas_disponiveis || 20,
      municipio_id: curso.municipio_id || '',
      bolsa_auxilio: curso.bolsa_auxilio || '',
      afirmativa_egresso: curso.afirmativa_egresso !== false,
      status: curso.status || 'aberto',
    };
  } else {
    courseModal.value = {
      open: true,
      id: null,
      instituicao: 'SENAI / ES',
      titulo: '',
      descricao: '',
      categoria: 'Qualificação Profissional',
      carga_horaria: 40,
      modalidade: 'presencial',
      vagas_disponiveis: 25,
      municipio_id: '',
      bolsa_auxilio: '',
      afirmativa_egresso: true,
      status: 'aberto',
    };
  }
};

const handleCourseSubmit = async () => {
  isSavingCourse.value = true;
  const csrfToken = getCsrfToken();
  const isEdit = Boolean(courseModal.value.id);
  const url = isEdit ? `/api/cursos/${courseModal.value.id}` : '/api/cursos';

  try {
    const payload = {
      instituicao: courseModal.value.instituicao,
      titulo: courseModal.value.titulo,
      descricao: courseModal.value.descricao,
      categoria: courseModal.value.categoria,
      carga_horaria: Number(courseModal.value.carga_horaria),
      modalidade: courseModal.value.modalidade,
      vagas_disponiveis: Number(courseModal.value.vagas_disponiveis),
      municipio_id: courseModal.value.municipio_id ? Number(courseModal.value.municipio_id) : null,
      bolsa_auxilio: courseModal.value.bolsa_auxilio ? Number(courseModal.value.bolsa_auxilio) : 0,
      afirmativa_egresso: Boolean(courseModal.value.afirmativa_egresso),
      status: courseModal.value.status || 'aberto',
    };

    const res = await fetch(url, {
      method: isEdit ? 'PUT' : 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify(payload),
    });

    const data = await res.json().catch(() => ({}));
    if (res.ok && data.data) {
      const munObj = municipiosOptions.value.find(m => Number(m.id) === Number(data.data.municipio_id));
      const normalized = normalizeCurso({
        ...data.data,
        municipio: munObj ? munObj.nome : (data.data.municipio || 'Estadual / EAD'),
      });
      if (isEdit) {
        const idx = cursosList.value.findIndex(c => c.id === courseModal.value.id);
        if (idx >= 0) cursosList.value[idx] = normalized;
      } else {
        cursosList.value.unshift(normalized);
      }
      courseModal.value.open = false;
      toast.success(isEdit ? 'Curso Atualizado' : 'Curso Cadastrado', `"${normalized.titulo}" salvo com sucesso.`);
    } else {
      toast.error('Erro ao Salvar Curso', data.error || data.message || 'Verifique os campos informados.');
    }
  } catch {
    toast.error('Erro de Conexão', 'Não foi possível salvar o curso de capacitação.');
  } finally {
    isSavingCourse.value = false;
  }
};

const handleCsvImport = async () => {
  if (!selectedCsvFile.value) {
    toast.error('Arquivo Obrigatório', 'Selecione um arquivo CSV para realizar a importação.');
    return;
  }

  isImportingCsv.value = true;
  importResult.value = null;

  try {
    const formData = new FormData();
    formData.append('arquivo', selectedCsvFile.value);
    if (csvMunicipioId.value) {
      formData.append('municipio_id', String(csvMunicipioId.value));
    }

    const csrfToken = getCsrfToken();
    const response = await fetch('/api/vagas/importar-csv', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: formData,
    });

    const data = await response.json().catch(() => ({}));

    if (response.ok) {
      const importedItems = Array.isArray(data.data) ? data.data.map(normalizeVaga) : [];
      vagasList.value = [...importedItems, ...vagasList.value];

      importResult.value = {
        ok: true,
        message: data.message || `${importedItems.length} vagas importadas com sucesso.`,
        erros: [],
      };

      selectedCsvFile.value = null;
      if (csvFileInput.value) {
        csvFileInput.value.value = '';
      }

      toast.success('Importação Concluída', importResult.value.message);
    } else {
      importResult.value = {
        ok: false,
        message: data.error || data.message || 'Foram encontrados erros no arquivo CSV.',
        erros: Array.isArray(data.erros) ? data.erros : [],
      };
      toast.error('Falha na Importação CSV', importResult.value.message);
    }
  } catch {
    importResult.value = {
      ok: false,
      message: 'Erro de comunicação ao enviar o arquivo CSV.',
      erros: [],
    };
    toast.error('Erro de Conexão', 'Não foi possível enviar o arquivo CSV ao servidor.');
  } finally {
    isImportingCsv.value = false;
  }
};

const handleManualSubmit = async () => {
  isSavingManual.value = true;
  const isEdit = Boolean(editingVagaId.value);
  const url = isEdit ? `/api/vagas/${editingVagaId.value}` : '/api/vagas';

  try {
    const payload = {
      empresa: manualForm.value.empresa || undefined,
      titulo: manualForm.value.nome_vaga,
      nome_vaga: manualForm.value.nome_vaga,
      descricao: manualForm.value.descricao,
      categoria: manualForm.value.categoria || 'geral',
      salario: manualForm.value.salario !== '' && manualForm.value.salario !== null ? Number(manualForm.value.salario) : null,
      regime_contratacao: manualForm.value.regime_contratacao || 'CLT',
      escolaridade_minima: manualForm.value.escolaridade_minima || null,
      afirmativa_egresso: Boolean(manualForm.value.afirmativa_egresso),
      vagas_totais: Number(manualForm.value.quantidade_vaga),
      quantidade_vaga: Number(manualForm.value.quantidade_vaga),
      inicio_imediato: manualForm.value.tipo_inicio === 'imediato',
      data_inicio: manualForm.value.tipo_inicio === 'data' ? manualForm.value.data_inicio : null,
      fim_permanente: manualForm.value.tipo_fim === 'permanente',
      data_fim: manualForm.value.tipo_fim === 'data' ? manualForm.value.data_fim : null,
      municipio_id: manualForm.value.municipio_id ? Number(manualForm.value.municipio_id) : null,
      conhecimentos: manualForm.value.conhecimentos,
    };

    const csrfToken = getCsrfToken();
    const response = await fetch(url, {
      method: isEdit ? 'PUT' : 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json().catch(() => ({}));

    if (response.ok) {
      if (data.data) {
        const normalized = normalizeVaga(data.data);
        if (isEdit) {
          const idx = vagasList.value.findIndex(v => v.id === editingVagaId.value);
          if (idx >= 0) vagasList.value[idx] = normalized;
        } else {
          vagasList.value.unshift(normalized);
        }
      }
      editingVagaId.value = null;
      manualForm.value = defaultManualForm();
      toast.success(
        isEdit ? 'Vaga Atualizada' : 'Vaga Cadastrada',
        isEdit ? 'Os dados da vaga foram atualizados.' : 'A vaga de emprego foi publicada com sucesso.'
      );
      if (isEdit) {
        activeTab.value = 'vagas';
      }
    } else {
      toast.error('Erro na Validação', data.error || data.message || 'Verifique os campos informados.');
    }
  } catch {
    toast.error('Erro de Conexão', 'Não foi possível salvar a vaga no momento.');
  } finally {
    isSavingManual.value = false;
  }
};

const filteredVagas = computed(() => {
  return vagasList.value.filter((v) => {
    if (cepMatchedIds.value !== null && !cepMatchedIds.value.includes(Number(v.id))) {
      return false;
    }
    if (filters.value.search) {
      const q = filters.value.search.toLowerCase();
      const match =
        (v.titulo || '').toLowerCase().includes(q) ||
        (v.empresa || '').toLowerCase().includes(q) ||
        (v.descricao || '').toLowerCase().includes(q);
      if (!match) return false;
    }
    if (filters.value.municipio && v.municipioNome !== filters.value.municipio) return false;
    if (filters.value.somenteAfirmativas && !v.afirmativa_egresso) return false;
    return true;
  });
});

const filteredCursos = computed(() => {
  return cursosList.value.filter((c) => {
    if (filters.value.search) {
      const q = filters.value.search.toLowerCase();
      const match =
        (c.titulo || '').toLowerCase().includes(q) ||
        (c.instituicao || '').toLowerCase().includes(q) ||
        (c.descricao || '').toLowerCase().includes(q);
      if (!match) return false;
    }
    if (filters.value.municipio && c.municipioNome !== filters.value.municipio) return false;
    return true;
  });
});

const openApplicationModal = (vaga) => {
  selectedEgressoId.value = isStaff.value && props.egressosList?.length ? props.egressosList[0].id : '';
  selectedOpportunity.value = { ...vaga, kind: 'vaga' };
};

const openCourseModal = (curso) => {
  selectedEgressoId.value = isStaff.value && props.egressosList?.length ? props.egressosList[0].id : '';
  selectedOpportunity.value = { ...curso, kind: 'curso' };
};

const confirmApplication = async () => {
  const opp = selectedOpportunity.value;
  if (!opp) return;

  if (isStaff.value && !selectedEgressoId.value) {
    toast.error('Selecione o Egresso', 'Escolha o egresso que receberá este encaminhamento.');
    return;
  }

  try {
    const csrfToken = getCsrfToken();
    const url = opp.kind === 'curso'
      ? `/api/cursos/${opp.id}/inscrever`
      : `/api/vagas/${opp.id}/candidatar`;

    const bodyPayload = isStaff.value && selectedEgressoId.value
      ? { egresso_id: Number(selectedEgressoId.value) }
      : {};

    const res = await fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      },
      body: JSON.stringify(bodyPayload),
    });

    const data = await res.json().catch(() => ({}));
    selectedOpportunity.value = null;

    if (res.ok) {
      if (opp.kind === 'curso') {
        const cIdx = cursosList.value.findIndex(c => c.id === opp.id);
        if (cIdx >= 0 && cursosList.value[cIdx].vagas_disponiveis > 0) {
          cursosList.value[cIdx].vagas_disponiveis -= 1;
        }
        meusCursosList.value.unshift({
          id: Date.now(),
          curso_id: opp.id,
          titulo: opp.titulo,
          instituicao: opp.instituicao || 'SENAI / SEJUS',
          carga_horaria: opp.carga_horaria,
          modalidade: opp.modalidade || 'Presencial',
          data_evento: 'Hoje',
          status: 'Matrícula Confirmada',
        });
      } else {
        const vIdx = vagasList.value.findIndex(v => v.id === opp.id);
        if (vIdx >= 0) {
          vagasList.value[vIdx].vagas_preenchidas = (vagasList.value[vIdx].vagas_preenchidas || 0) + 1;
        }
        minhasCandidaturasList.value.unshift({
          id: Date.now(),
          vaga_id: opp.id,
          titulo: opp.titulo,
          empresa: opp.empresa || 'Empresa Parceira',
          municipio_nome: opp.municipioNome || 'Vitória',
          salario: opp.salario,
          data_evento: 'Hoje',
          status: 'Encaminhado ao Empregador',
        });
      }

      toast.success(
        'Encaminhamento Realizado com Sucesso',
        data.message || `Inscrição registrada para "${opp.titulo}".`
      );
    } else {
      toast.error(
        'Não foi possível concluir a inscrição',
        data.error || 'Verifique seu perfil ou tente novamente.'
      );
    }
  } catch {
    selectedOpportunity.value = null;
    toast.error('Erro de Conexão', 'Falha de comunicação ao registrar encaminhamento.');
  }
};
</script>
