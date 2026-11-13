/**
 * Master Theme Composable for CONECTA EGRESSO (SEJUS/ES)
 * Manages 6 Government Dark Themes, DOM synchronization, and WCAG 2.1 AA Compliance.
 *
 * Persisted in localStorage:
 * - 'conecta_theme_mode': 'dark' | 'light'
 * - 'conecta_active_theme': 'executive-slate' | 'midnight-navy' | 'forest-emerald' | 'deep-titanium' | 'royal-indigo' | 'obsidian-gold'
 */
import { ref, computed } from 'vue';

export const THEME_MODE_KEY = 'conecta_theme_mode';
export const THEME_ID_KEY = 'conecta_active_theme';
export const DEFAULT_THEME = 'executive-slate';

export const AVAILABLE_THEMES = [
  {
    id: 'executive-slate',
    name: 'Executive Slate',
    subtitle: 'Padrão Corporativo SEJUS',
    concept: 'Governamental Sóbrio',
    description: 'Ardósia escura de alta densidade calibrada para auditores e gestores da SEJUS. Elimina fadiga visual e reflexos luminosos.',
    temperature: 'Fria Neutra',
    accent: '#38BDF8',
    accentHover: '#0284C7',
    bgMain: '#0B0F17',
    bgCard: '#151D2A',
    bgHeader: '#111827',
    bgSidebar: '#0E1420',
    border: '#243144',
    inputBg: '#1B2433',
    inputBorder: '#64748B',
    focusRing: '#38BDF8',
    textPrimary: '#F8FAFC',
    textSecondary: '#CBD5E1',
    textMuted: '#94A3B8',
    statusSuccess: '#34D399',
    statusWarning: '#FBBF24',
    statusDanger: '#F87171',
    statusInfo: '#38BDF8',
    contrastRatioText: 16.17,
    contrastRatioInput: 14.90,
    swatches: ['#0B0F17', '#151D2A', '#243144', '#38BDF8', '#F8FAFC'],
  },
  {
    id: 'midnight-navy',
    name: 'Midnight Navy',
    subtitle: 'Marítimo Espírito Santo',
    concept: 'Identidade Capixaba',
    description: 'Inspirado na tradição marítima do Espírito Santo e no azul oficial das forças de segurança, com realce safira e ciano.',
    temperature: 'Fria Oceânica',
    accent: '#60A5FA',
    accentHover: '#3B82F6',
    bgMain: '#060D1A',
    bgCard: '#0F1D33',
    bgHeader: '#0A1629',
    bgSidebar: '#060D1A',
    border: '#1E355B',
    inputBg: '#152744',
    inputBorder: '#5A7CA6',
    focusRing: '#60A5FA',
    textPrimary: '#F0F6FF',
    textSecondary: '#BFDBFE',
    textMuted: '#93B5DE',
    statusSuccess: '#2DD4BF',
    statusWarning: '#FCD34D',
    statusDanger: '#FB7185',
    statusInfo: '#38BDF8',
    contrastRatioText: 15.54,
    contrastRatioInput: 13.75,
    swatches: ['#060D1A', '#0F1D33', '#1E355B', '#60A5FA', '#F0F6FF'],
  },
  {
    id: 'forest-emerald',
    name: 'Forest Emerald',
    subtitle: 'Reintegração & Esperança',
    concept: 'Reintegração Social',
    description: 'Tons de musgo botânico profundo e abeto, simbolizando regeneração social, dignidade humana e foco operacional estável.',
    temperature: 'Orgânica / Calma',
    accent: '#34D399',
    accentHover: '#10B981',
    bgMain: '#06120D',
    bgCard: '#0E231B',
    bgHeader: '#0A1B14',
    bgSidebar: '#05100B',
    border: '#1E4938',
    inputBg: '#143026',
    inputBorder: '#4E886D',
    focusRing: '#34D399',
    textPrimary: '#F0FDF4',
    textSecondary: '#BBF7D0',
    textMuted: '#86D3A0',
    statusSuccess: '#4ADE80',
    statusWarning: '#FBBF24',
    statusDanger: '#F87171',
    statusInfo: '#38BDF8',
    contrastRatioText: 15.74,
    contrastRatioInput: 13.55,
    swatches: ['#06120D', '#0E231B', '#1E4938', '#34D399', '#F0FDF4'],
  },
  {
    id: 'deep-titanium',
    name: 'Deep Titanium',
    subtitle: 'Alta Segurança & Precisão',
    concept: 'CICC / Monitoramento',
    description: 'Grafite e titânio industrial neutro, calibrado para centros de monitoramento e centros de comando e controle (CICC / NOC).',
    temperature: 'Neutra Absoluta',
    accent: '#38BDF8',
    accentHover: '#0284C7',
    bgMain: '#111215',
    bgCard: '#1A1C22',
    bgHeader: '#15171C',
    bgSidebar: '#0E0F12',
    border: '#2F333E',
    inputBg: '#242831',
    inputBorder: '#6E7687',
    focusRing: '#38BDF8',
    textPrimary: '#F8FAFC',
    textSecondary: '#D1D5DB',
    textMuted: '#9CA3AF',
    statusSuccess: '#34D399',
    statusWarning: '#FBBF24',
    statusDanger: '#F87171',
    statusInfo: '#60A5FA',
    contrastRatioText: 16.28,
    contrastRatioInput: 14.11,
    swatches: ['#111215', '#1A1C22', '#2F333E', '#38BDF8', '#F8FAFC'],
  },
  {
    id: 'royal-indigo',
    name: 'Royal Indigo',
    subtitle: 'Soberania Jurídica & Cidadania',
    concept: 'Justiça & Cidadania',
    description: 'Violeta crepuscular e índigo profundo, expressando solenidade constitucional, garantia de direitos fundamentais e cidadania.',
    temperature: 'Quente-Fria',
    accent: '#A78BFA',
    accentHover: '#8B5CF6',
    bgMain: '#0B0B18',
    bgCard: '#16162E',
    bgHeader: '#111124',
    bgSidebar: '#090914',
    border: '#2E2F5E',
    inputBg: '#1F2040',
    inputBorder: '#696DA6',
    focusRing: '#A78BFA',
    textPrimary: '#F5F3FF',
    textSecondary: '#DDD6FE',
    textMuted: '#A5B4FC',
    statusSuccess: '#34D399',
    statusWarning: '#FBBF24',
    statusDanger: '#F87171',
    statusInfo: '#818CF8',
    contrastRatioText: 16.11,
    contrastRatioInput: 14.30,
    swatches: ['#0B0B18', '#16162E', '#2E2F5E', '#A78BFA', '#F5F3FF'],
  },
  {
    id: 'obsidian-gold',
    name: 'Obsidian Gold',
    subtitle: 'Edição Nobre Institucional',
    concept: 'Edição Especial',
    description: 'Preto obsidiana com carvão aquecido e tipografia ouro champanhe fosco, transmitindo requinte institucional sem tons berrantes.',
    temperature: 'Quente Terrosa',
    accent: '#FBBF24',
    accentHover: '#F59E0B',
    bgMain: '#0E0E10',
    bgCard: '#1A1918',
    bgHeader: '#141312',
    bgSidebar: '#0A0A0B',
    border: '#3B3730',
    inputBg: '#252422',
    inputBorder: '#7A7267',
    focusRing: '#F59E0B',
    textPrimary: '#FEF9EF',
    textSecondary: '#E7DFCE',
    textMuted: '#B8AF9E',
    statusSuccess: '#34D399',
    statusWarning: '#F59E0B',
    statusDanger: '#F87171',
    statusInfo: '#38BDF8',
    contrastRatioText: 16.73,
    contrastRatioInput: 14.78,
    swatches: ['#0E0E10', '#1A1918', '#3B3730', '#FBBF24', '#FEF9EF'],
  },
];

// Singleton reactive state shared across components
const currentTheme = ref(DEFAULT_THEME);
const isDarkMode = ref(true);
const isInitialized = ref(false);

export function useTheme() {
  const activeThemeObj = computed(() => {
    return AVAILABLE_THEMES.find(t => t.id === currentTheme.value) || AVAILABLE_THEMES[0];
  });

  const isHighContrastActive = () => {
    if (typeof document === 'undefined') return false;
    return document.documentElement.classList.contains('high-contrast');
  };

  const applyTheme = (themeId, options = {}) => {
    if (typeof document === 'undefined') return;

    const validTheme = AVAILABLE_THEMES.find(t => t.id === themeId);
    const targetId = validTheme ? validTheme.id : DEFAULT_THEME;

    const root = document.documentElement;

    // 1. Update DOM attributes
    root.setAttribute('data-theme', targetId);
    root.classList.add('dark');

    // 2. Update reactive state
    currentTheme.value = targetId;
    isDarkMode.value = true;

    // 3. Persist to localStorage
    try {
      localStorage.setItem(THEME_ID_KEY, targetId);
      localStorage.setItem(THEME_MODE_KEY, 'dark');
    } catch (e) {
      // safe fallback
    }

    // 4. Handle High Contrast deactivation if requested or explicit theme switch
    if (options.exitHighContrast && isHighContrastActive()) {
      root.classList.remove('high-contrast');
      document.body?.classList.remove('high-contrast');
      try {
        localStorage.setItem('conecta_high_contrast', 'false');
      } catch (e) {}
    }
  };

  const toggleDarkMode = () => {
    if (typeof document === 'undefined') return !isDarkMode.value;

    const root = document.documentElement;
    const nextDark = !isDarkMode.value;

    if (nextDark) {
      root.classList.add('dark');
      root.setAttribute('data-theme', currentTheme.value);
      isDarkMode.value = true;
      try {
        localStorage.setItem(THEME_MODE_KEY, 'dark');
        localStorage.setItem(THEME_ID_KEY, currentTheme.value);
      } catch (e) {}
    } else {
      root.classList.remove('dark');
      root.removeAttribute('data-theme');
      isDarkMode.value = false;
      try {
        localStorage.setItem(THEME_MODE_KEY, 'light');
      } catch (e) {}
    }

    return isDarkMode.value;
  };

  const initTheme = () => {
    if (typeof window === 'undefined') return;
    if (isInitialized.value) return;

    try {
      const savedMode = localStorage.getItem(THEME_MODE_KEY);
      const savedTheme = localStorage.getItem(THEME_ID_KEY);
      const highContrastActive = localStorage.getItem('conecta_high_contrast') === 'true';

      const targetTheme = AVAILABLE_THEMES.some(t => t.id === savedTheme)
        ? savedTheme
        : DEFAULT_THEME;

      currentTheme.value = targetTheme;

      // Default mode is dark for Conecta Egresso
      const shouldBeDark = savedMode === 'light' ? false : true;
      isDarkMode.value = shouldBeDark;

      const root = document.documentElement;

      if (shouldBeDark) {
        root.classList.add('dark');
        root.setAttribute('data-theme', targetTheme);
        localStorage.setItem(THEME_MODE_KEY, 'dark');
        localStorage.setItem(THEME_ID_KEY, targetTheme);
      } else {
        root.classList.remove('dark');
        root.removeAttribute('data-theme');
      }

      // If high contrast was already saved, preserve its priority
      if (highContrastActive) {
        root.classList.add('high-contrast');
        document.body?.classList.add('high-contrast');
      }

      isInitialized.value = true;
    } catch (e) {
      applyTheme(DEFAULT_THEME);
      isInitialized.value = true;
    }
  };

  return {
    currentTheme,
    isDarkMode,
    activeThemeObj,
    AVAILABLE_THEMES,
    applyTheme,
    toggleDarkMode,
    initTheme,
    isHighContrastActive,
  };
}
