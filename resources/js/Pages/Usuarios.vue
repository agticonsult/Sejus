<template>
  <AppLayout :breadcrumbs="[{ name: 'Gestão & Governança' }, { name: 'Gerenciamento de Usuários' }]">
    <Head title="Gerenciamento de Usuários - Conecta Egresso" />

    <div class="usuarios-page space-y-6" id="view-usuarios">
      <!-- Top Institutional Banner -->
      <div class="bg-gradient-to-r from-slate-900 via-[#003366] to-[#0f172a] rounded-2xl p-6 text-white shadow-lg flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <div class="inline-flex items-center gap-2 px-3 py-1 bg-sky-500/20 text-sky-300 rounded-full text-xs font-semibold mb-2 border border-sky-500/30">
            <span>👥 Governança & Controle de Acesso SEJUS/ES</span>
          </div>
          <h1 class="text-2xl md:text-3xl font-extrabold font-heading tracking-tight">
            Gerenciamento de Usuários
          </h1>
          <p class="text-xs md:text-sm text-slate-300 mt-1 max-w-2xl">
            Administração centralizada de credenciais, perfis institucionais (RBAC), atribuição territorial nos 78 municípios e conformidade com a LGPD.
          </p>
        </div>

        <div class="flex items-center gap-3">
          <button
            id="btnNovoUsuario"
            type="button"
            class="px-4 py-2.5 bg-sky-600 hover:bg-sky-500 text-white text-xs font-bold rounded-xl shadow-md hover:shadow-lg transition flex items-center gap-2 cursor-pointer focus:ring-2 focus:ring-sky-300 min-h-[44px]"
            @click="openCreateModal"
          >
            <span class="text-base" aria-hidden="true">➕</span>
            <span>Novo Usuário</span>
          </button>
        </div>
      </div>

      <!-- Quick KPI Stats Cards -->
      <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-sky-100 text-sky-700 flex items-center justify-center text-lg font-bold">
            👥
          </div>
          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Total de Usuários</span>
            <strong class="text-lg font-extrabold text-slate-900">{{ stats.total || 0 }}</strong>
            <span class="text-[10px] text-emerald-600 font-semibold block">{{ stats.ativos || 0 }} ativos</span>
          </div>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-indigo-100 text-indigo-700 flex items-center justify-center text-lg font-bold">
            👔
          </div>
          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Gestores & Técnicos</span>
            <strong class="text-lg font-extrabold text-slate-900">{{ stats.gestores_tecnicos || 0 }}</strong>
            <span class="text-[10px] text-slate-500 block">Corpo Operacional</span>
          </div>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-700 flex items-center justify-center text-lg font-bold">
            🤝
          </div>
          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Egressos & Familiares</span>
            <strong class="text-lg font-extrabold text-slate-900">{{ stats.egressos_familiares || 0 }}</strong>
            <span class="text-[10px] text-slate-500 block">Cidadãos Beneficiários</span>
          </div>
        </div>

        <div class="p-4 bg-white rounded-2xl border border-slate-200 shadow-xs flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-rose-100 text-rose-700 flex items-center justify-center text-lg font-bold">
            🛡️
          </div>
          <div>
            <span class="text-[10px] uppercase font-bold text-slate-400 block">Suporte Técnico Agile</span>
            <strong class="text-lg font-extrabold text-slate-900">{{ stats.suporte || 0 }}</strong>
            <span class="text-[10px] text-rose-600 font-semibold block">Acesso Irrestrito</span>
          </div>
        </div>
      </div>

      <!-- Filter & Search Controls Bar -->
      <div class="bg-white p-4 rounded-2xl border border-slate-200 shadow-xs space-y-3">
        <div class="flex flex-col md:flex-row gap-3 items-stretch md:items-center justify-between">
          <!-- Search input -->
          <div class="relative flex-1 min-w-[240px]">
            <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 text-sm">🔍</span>
            <input
              id="searchUserFilter"
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por nome, email institucional ou CPF..."
              class="w-full pl-10 pr-4 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
              @keyup.enter="applyFilters"
            />
          </div>

          <!-- Role Filter -->
          <div class="w-full md:w-48">
            <label for="roleFilterSelect" class="sr-only">Filtrar por Perfil</label>
            <select
              id="roleFilterSelect"
              v-model="selectedRole"
              class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition cursor-pointer"
              @change="applyFilters"
            >
              <option value="">Todos os Perfis</option>
              <option value="gestor">Gestor SEJUS</option>
              <option value="tecnico">Técnico Escritório Social</option>
              <option value="egresso">Egresso</option>
              <option value="familiar">Familiar</option>
              <option value="suporte">Suporte Técnico Agile</option>
            </select>
          </div>

          <!-- Municipality Filter -->
          <div class="w-full md:w-56">
            <label for="municipioFilterSelect" class="sr-only">Filtrar por Município</label>
            <select
              id="municipioFilterSelect"
              v-model="selectedMunicipio"
              class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition cursor-pointer"
              @change="applyFilters"
            >
              <option value="">Todos os 78 Municípios</option>
              <option v-for="m in municipios" :key="m.id" :value="m.id">
                {{ m.nome }} ({{ m.microrregiao }})
              </option>
            </select>
          </div>

          <!-- Status Filter -->
          <div class="w-full md:w-36">
            <label for="statusFilterSelect" class="sr-only">Filtrar por Status</label>
            <select
              id="statusFilterSelect"
              v-model="selectedStatus"
              class="w-full px-3 py-2.5 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition cursor-pointer"
              @change="applyFilters"
            >
              <option value="">Status (Todos)</option>
              <option value="true">Ativos</option>
              <option value="false">Inativos</option>
            </select>
          </div>

          <!-- Reset Filter Button -->
          <button
            type="button"
            class="px-3 py-2.5 text-xs font-semibold text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer flex items-center justify-center gap-1 min-h-[40px]"
            title="Limpar Filtros"
            @click="clearFilters"
          >
            <span>🔄</span>
            <span>Limpar</span>
          </button>
        </div>
      </div>

      <!-- Users Data Table -->
      <div class="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
        <div class="p-4 border-b border-slate-100 flex items-center justify-between">
          <h2 class="text-sm font-extrabold text-slate-900 uppercase tracking-wide flex items-center gap-2">
            <span>📋 Lista de Usuários Cadastrados</span>
            <span class="text-xs text-slate-400 font-normal">({{ usersList.length }} exibidos de {{ totalUsers }})</span>
          </h2>
          <span class="text-[11px] text-slate-500 font-medium">Dados protegidos conforme Lei 13.709/2018 (LGPD)</span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="border-b border-slate-200 bg-slate-50 text-slate-500 uppercase text-[10px] tracking-wider font-extrabold">
                <th class="py-3 px-4">Usuário</th>
                <th class="py-3 px-4">Email</th>
                <th class="py-3 px-4">Perfil / Papel</th>
                <th class="py-3 px-4">CPF (LGPD)</th>
                <th class="py-3 px-4">Município (ES)</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Ações</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
              <tr
                v-for="user in usersList"
                :key="user.id"
                class="hover:bg-slate-50/80 transition"
              >
                <!-- Avatar & Name -->
                <td class="py-3 px-4">
                  <div class="flex items-center gap-3">
                    <div
                      class="w-8 h-8 rounded-full text-white font-extrabold flex items-center justify-center text-xs shadow-2xs flex-shrink-0"
                      :class="getAvatarBackground(user.role)"
                    >
                      {{ getUserInitials(user.name) }}
                    </div>
                    <div>
                      <strong class="text-slate-900 font-bold block">{{ user.name }}</strong>
                      <span v-if="user.telefone" class="text-[10px] text-slate-400 block">📞 {{ user.telefone }}</span>
                    </div>
                  </div>
                </td>

                <!-- Email -->
                <td class="py-3 px-4">
                  <span class="text-slate-600 font-mono text-[11px]">{{ user.email }}</span>
                </td>

                <!-- Role Badge -->
                <td class="py-3 px-4">
                  <span
                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-extrabold shadow-2xs"
                    :class="getRoleBadgeClass(user.role)"
                  >
                    <span>{{ getRoleIcon(user.role) }}</span>
                    <span>{{ user.role_name }}</span>
                  </span>
                </td>

                <!-- Masked CPF -->
                <td class="py-3 px-4 font-mono text-[11px] text-slate-600">
                  {{ user.cpf_masked || '***.***.***-**' }}
                </td>

                <!-- Municipality -->
                <td class="py-3 px-4">
                  <span class="text-slate-800 font-medium">{{ user.municipio_nome }}</span>
                  <span v-if="user.microrregiao" class="text-[10px] text-slate-400 block">{{ user.microrregiao }}</span>
                </td>

                <!-- Status -->
                <td class="py-3 px-4 text-center">
                  <span
                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold"
                    :class="user.ativo ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200'"
                  >
                    <span class="w-1.5 h-1.5 rounded-full" :class="user.ativo ? 'bg-emerald-500' : 'bg-rose-500'"></span>
                    <span>{{ user.ativo ? 'Ativo' : 'Inativo' }}</span>
                  </span>
                </td>

                <!-- Actions -->
                <td class="py-3 px-4 text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      type="button"
                      class="px-2.5 py-1.5 bg-slate-100 hover:bg-sky-50 text-slate-700 hover:text-sky-700 rounded-lg text-xs font-bold transition cursor-pointer min-h-[32px] flex items-center gap-1"
                      title="Editar Usuário"
                      @click="openEditModal(user)"
                    >
                      <span>✏️</span>
                      <span class="hidden sm:inline">Editar</span>
                    </button>

                    <button
                      type="button"
                      class="px-2.5 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer min-h-[32px] flex items-center gap-1"
                      :class="user.ativo ? 'bg-rose-50 hover:bg-rose-100 text-rose-700' : 'bg-emerald-50 hover:bg-emerald-100 text-emerald-700'"
                      :title="user.ativo ? 'Desativar Usuário' : 'Ativar Usuário'"
                      @click="confirmToggleStatus(user)"
                    >
                      <span>{{ user.ativo ? '🚫' : '✅' }}</span>
                      <span class="hidden sm:inline">{{ user.ativo ? 'Desativar' : 'Ativar' }}</span>
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-if="usersList.length === 0">
                <td colspan="7" class="py-8 text-center text-slate-400">
                  <div class="flex flex-col items-center justify-center gap-2">
                    <span class="text-3xl">🔍</span>
                    <strong class="text-sm text-slate-700">Nenhum usuário encontrado</strong>
                    <p class="text-xs text-slate-500">Tente ajustar os filtros ou cadastrar um novo usuário no sistema.</p>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Criação / Edição de Usuário -->
      <div
        v-if="isModalOpen"
        class="fixed inset-0 z-50 overflow-y-auto bg-slate-900/60 backdrop-blur-xs flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="modalUserTitle"
      >
        <div class="bg-white rounded-2xl shadow-2xl border border-slate-200 max-w-xl w-full p-6 space-y-5 animate-in fade-in zoom-in-95 duration-200">
          <div class="flex items-center justify-between border-b border-slate-100 pb-3">
            <div>
              <h3 id="modalUserTitle" class="text-base font-extrabold text-slate-900 font-heading">
                {{ isEditing ? 'Editar Usuário SEJUS' : 'Cadastrar Novo Usuário' }}
              </h3>
              <p class="text-xs text-slate-500 mt-0.5">
                {{ isEditing ? 'Atualize as credenciais e vínculos do usuário.' : 'Preencha os campos para provisionar acesso ao Conecta Egresso.' }}
              </p>
            </div>
            <button
              type="button"
              class="w-8 h-8 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 flex items-center justify-center cursor-pointer transition text-lg"
              aria-label="Fechar Modal"
              @click="closeModal"
            >
              ✕
            </button>
          </div>

          <form @submit.prevent="handleSubmit" class="space-y-4">
            <!-- Nome Completo -->
            <div>
              <label for="formUserName" class="block text-xs font-bold text-slate-700 mb-1">
                Nome Completo <span class="text-rose-500">*</span>
              </label>
              <input
                id="formUserName"
                v-model="userForm.name"
                type="text"
                required
                placeholder="Ex: Carlos Eduardo Silva"
                class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
              />
              <span v-if="formErrors.name" class="text-[10px] text-rose-600 font-semibold mt-1 block">{{ formErrors.name }}</span>
            </div>

            <!-- Email & Telefone Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label for="formUserEmail" class="block text-xs font-bold text-slate-700 mb-1">
                  Email Institucional / Pessoal <span class="text-rose-500">*</span>
                </label>
                <input
                  id="formUserEmail"
                  v-model="userForm.email"
                  type="email"
                  required
                  placeholder="usuario@sejus.es.gov.br"
                  class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
                />
                <span v-if="formErrors.email" class="text-[10px] text-rose-600 font-semibold mt-1 block">{{ formErrors.email }}</span>
              </div>

              <div>
                <label for="formUserTelefone" class="block text-xs font-bold text-slate-700 mb-1">
                  Telefone / WhatsApp
                </label>
                <input
                  id="formUserTelefone"
                  v-model="userForm.telefone"
                  type="text"
                  placeholder="(27) 99888-1122"
                  class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
                  @input="handleTelefoneInput"
                />
              </div>
            </div>

            <!-- CPF & Senha Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label for="formUserCpf" class="block text-xs font-bold text-slate-700 mb-1">
                  CPF <span class="text-rose-500">*</span>
                </label>
                <input
                  id="formUserCpf"
                  v-model="userForm.cpf"
                  type="text"
                  :required="!isEditing"
                  placeholder="000.000.000-00"
                  class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition font-mono"
                  @input="handleCpfInput"
                />
                <span v-if="formErrors.cpf" class="text-[10px] text-rose-600 font-semibold mt-1 block">{{ formErrors.cpf }}</span>
              </div>

              <div>
                <label for="formUserPassword" class="block text-xs font-bold text-slate-700 mb-1">
                  {{ isEditing ? 'Nova Senha (deixe vazio para manter)' : 'Senha de Acesso *' }}
                </label>
                <input
                  id="formUserPassword"
                  v-model="userForm.password"
                  type="password"
                  :required="!isEditing"
                  placeholder="Mínimo 6 caracteres"
                  class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition"
                />
                <span v-if="formErrors.password" class="text-[10px] text-rose-600 font-semibold mt-1 block">{{ formErrors.password }}</span>
              </div>
            </div>

            <!-- Perfil & Município Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div>
                <label for="formUserPerfil" class="block text-xs font-bold text-slate-700 mb-1">
                  Perfil de Acesso (RBAC) <span class="text-rose-500">*</span>
                </label>
                <select
                  id="formUserPerfil"
                  v-model="userForm.perfil_id"
                  required
                  class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition cursor-pointer"
                >
                  <option value="" disabled>Selecione um perfil</option>
                  <option v-for="p in perfis" :key="p.id" :value="p.id">
                    {{ p.nome }} ({{ p.slug }})
                  </option>
                </select>
                <span v-if="formErrors.perfil_id" class="text-[10px] text-rose-600 font-semibold mt-1 block">{{ formErrors.perfil_id }}</span>
              </div>

              <div>
                <label for="formUserMunicipio" class="block text-xs font-bold text-slate-700 mb-1">
                  Município de Atuação / Residência
                </label>
                <select
                  id="formUserMunicipio"
                  v-model="userForm.municipio_id"
                  class="w-full px-3.5 py-2 text-xs bg-slate-50 border border-slate-200 rounded-xl focus:bg-white focus:border-sky-500 focus:ring-2 focus:ring-sky-100 outline-none transition cursor-pointer"
                >
                  <option :value="null">Selecione o Município (78 cidades ES)</option>
                  <option v-for="m in municipios" :key="m.id" :value="m.id">
                    {{ m.nome }} ({{ m.microrregiao }})
                  </option>
                </select>
              </div>
            </div>

            <!-- Ativo Toggle -->
            <div class="flex items-center gap-2 pt-2">
              <input
                id="formUserAtivo"
                v-model="userForm.ativo"
                type="checkbox"
                class="w-4 h-4 rounded text-sky-600 focus:ring-sky-500 border-slate-300 cursor-pointer"
              />
              <label for="formUserAtivo" class="text-xs font-semibold text-slate-700 cursor-pointer">
                Usuário Ativo (permite autenticação e acesso ao Conecta Egresso)
              </label>
            </div>

            <!-- Modal Action Buttons -->
            <div class="flex items-center justify-end gap-3 border-t border-slate-100 pt-4 mt-4">
              <button
                type="button"
                class="px-4 py-2 text-xs font-bold text-slate-600 hover:text-slate-800 bg-slate-100 hover:bg-slate-200 rounded-xl transition cursor-pointer min-h-[38px]"
                @click="closeModal"
              >
                Cancelar
              </button>
              <button
                type="submit"
                :disabled="isSubmitting"
                class="px-5 py-2 text-xs font-bold text-white bg-sky-600 hover:bg-sky-500 disabled:opacity-50 rounded-xl shadow-sm transition cursor-pointer min-h-[38px] flex items-center gap-2"
              >
                <span v-if="isSubmitting" class="animate-spin text-xs">⏳</span>
                <span>{{ isEditing ? 'Atualizar Usuário' : 'Salvar Usuário' }}</span>
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import { useToast } from '../Composables/useToast';

const props = defineProps({
  users: {
    type: Object,
    default: () => ({ data: [], total: 0 }),
  },
  perfis: {
    type: Array,
    default: () => [],
  },
  municipios: {
    type: Array,
    default: () => [],
  },
  filters: {
    type: Object,
    default: () => ({}),
  },
  stats: {
    type: Object,
    default: () => ({
      total: 0,
      ativos: 0,
      inativos: 0,
      gestores_tecnicos: 0,
      egressos_familiares: 0,
      suporte: 0,
    }),
  },
});

const toast = useToast();

const searchQuery = ref(props.filters.q || '');
const selectedRole = ref(props.filters.role || '');
const selectedMunicipio = ref(props.filters.municipio_id || '');
const selectedStatus = ref(props.filters.ativo || '');

const usersList = computed(() => {
  return props.users?.data || [];
});

const totalUsers = computed(() => {
  return props.users?.total || usersList.value.length;
});

// Modal State
const isModalOpen = ref(false);
const isEditing = ref(false);
const editingUserId = ref(null);
const isSubmitting = ref(false);
const formErrors = ref({});

const userForm = ref({
  name: '',
  email: '',
  telefone: '',
  cpf: '',
  password: '',
  perfil_id: '',
  municipio_id: null,
  ativo: true,
});

// Helper for Initials
const getUserInitials = (name) => {
  if (!name) return 'US';
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

// Avatar backgrounds based on role
const getAvatarBackground = (role) => {
  switch (role) {
    case 'suporte':
      return 'bg-gradient-to-tr from-rose-600 to-red-900';
    case 'gestor':
      return 'bg-gradient-to-tr from-indigo-600 to-purple-900';
    case 'tecnico':
      return 'bg-gradient-to-tr from-emerald-600 to-teal-900';
    case 'egresso':
      return 'bg-gradient-to-tr from-sky-600 to-blue-900';
    case 'familiar':
      return 'bg-gradient-to-tr from-amber-600 to-orange-900';
    default:
      return 'bg-gradient-to-tr from-slate-600 to-slate-800';
  }
};

// Role badge styling
const getRoleBadgeClass = (role) => {
  switch (role) {
    case 'suporte':
      return 'bg-rose-50 text-rose-700 border border-rose-200';
    case 'gestor':
      return 'bg-indigo-50 text-indigo-700 border border-indigo-200';
    case 'tecnico':
      return 'bg-emerald-50 text-emerald-700 border border-emerald-200';
    case 'egresso':
      return 'bg-sky-50 text-sky-700 border border-sky-200';
    case 'familiar':
      return 'bg-amber-50 text-amber-700 border border-amber-200';
    default:
      return 'bg-slate-50 text-slate-700 border border-slate-200';
  }
};

const getRoleIcon = (role) => {
  switch (role) {
    case 'suporte':
      return '🛡️';
    case 'gestor':
      return '👔';
    case 'tecnico':
      return '💼';
    case 'egresso':
      return '👤';
    case 'familiar':
      return '🤝';
    default:
      return '👤';
  }
};

// Mask input formatters
const handleCpfInput = (e) => {
  let val = e.target.value.replace(/\D/g, '');
  if (val.length > 11) val = val.substring(0, 11);
  if (val.length > 9) {
    val = val.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
  } else if (val.length > 6) {
    val = val.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
  } else if (val.length > 3) {
    val = val.replace(/(\d{3})(\d{1,3})/, '$1.$2');
  }
  userForm.value.cpf = val;
};

const handleTelefoneInput = (e) => {
  let val = e.target.value.replace(/\D/g, '');
  if (val.length > 11) val = val.substring(0, 11);
  if (val.length > 10) {
    val = val.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
  } else if (val.length > 6) {
    val = val.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
  } else if (val.length > 2) {
    val = val.replace(/(\d{2})(\d{0,5})/, '($1) $2');
  }
  userForm.value.telefone = val;
};

// Filter actions
const applyFilters = () => {
  router.get(
    '/usuarios',
    {
      q: searchQuery.value || undefined,
      role: selectedRole.value || undefined,
      municipio_id: selectedMunicipio.value || undefined,
      ativo: selectedStatus.value !== '' ? selectedStatus.value : undefined,
    },
    { preserveState: true, replace: true }
  );
};

const clearFilters = () => {
  searchQuery.value = '';
  selectedRole.value = '';
  selectedMunicipio.value = '';
  selectedStatus.value = '';
  router.get('/usuarios', {}, { preserveState: true, replace: true });
  toast.info('Filtros Limpos', 'Exibindo todos os usuários cadastrados.');
};

// Modal Open/Close
const openCreateModal = () => {
  isEditing.value = false;
  editingUserId.value = null;
  formErrors.value = {};
  userForm.value = {
    name: '',
    email: '',
    telefone: '',
    cpf: '',
    password: '',
    perfil_id: props.perfis.length ? props.perfis[0].id : '',
    municipio_id: null,
    ativo: true,
  };
  isModalOpen.value = true;
};

const openEditModal = (user) => {
  isEditing.value = true;
  editingUserId.value = user.id;
  formErrors.value = {};
  userForm.value = {
    name: user.name,
    email: user.email,
    telefone: user.telefone || '',
    cpf: '', // Sensitive: kept blank unless changed
    password: '', // Blank unless reset
    perfil_id: user.perfil_id,
    municipio_id: user.municipio_id || null,
    ativo: (bool) => user.ativo,
  };
  userForm.value.ativo = user.ativo;
  isModalOpen.value = true;
};

const closeModal = () => {
  isModalOpen.value = false;
  formErrors.value = {};
};

// Submit handler
const handleSubmit = () => {
  isSubmitting.value = true;
  formErrors.value = {};

  if (isEditing.value) {
    const payload = {
      name: userForm.value.name,
      email: userForm.value.email,
      perfil_id: userForm.value.perfil_id,
      municipio_id: userForm.value.municipio_id,
      telefone: userForm.value.telefone,
      ativo: userForm.value.ativo,
    };
    if (userForm.value.password) payload.password = userForm.value.password;
    if (userForm.value.cpf) payload.cpf = userForm.value.cpf;

    router.put(`/usuarios/${editingUserId.value}`, payload, {
      onSuccess: () => {
        isSubmitting.value = false;
        closeModal();
        toast.success('Usuário Atualizado', `Os dados de ${userForm.value.name} foram atualizados com sucesso.`);
      },
      onError: (errors) => {
        isSubmitting.value = false;
        formErrors.value = errors;
        toast.error('Erro ao Atualizar', 'Verifique os dados informados no formulário.');
      },
    });
  } else {
    router.post('/usuarios', userForm.value, {
      onSuccess: () => {
        isSubmitting.value = false;
        closeModal();
        toast.success('Usuário Criado', `O usuário ${userForm.value.name} foi provisionado com sucesso.`);
      },
      onError: (errors) => {
        isSubmitting.value = false;
        formErrors.value = errors;
        toast.error('Erro ao Criar Usuário', 'Não foi possível cadastrar o usuário. Verifique os erros.');
      },
    });
  }
};

// Toggle status handler
const confirmToggleStatus = (user) => {
  const action = user.ativo ? 'desativar' : 'reativar';
  if (confirm(`Deseja realmente ${action} o usuário ${user.name}?`)) {
    if (user.ativo) {
      router.delete(`/usuarios/${user.id}`, {
        onSuccess: () => {
          toast.success('Usuário Desativado', `O acesso de ${user.name} foi revogado.`);
        },
        onError: () => {
          toast.error('Erro na Operação', 'Não foi possível desativar o usuário.');
        },
      });
    } else {
      router.put(`/usuarios/${user.id}`, { ativo: true }, {
        onSuccess: () => {
          toast.success('Usuário Ativado', `O acesso de ${user.name} foi reativado.`);
        },
        onError: () => {
          toast.error('Erro na Operação', 'Não foi possível reativar o usuário.');
        },
      });
    }
  }
};
</script>
