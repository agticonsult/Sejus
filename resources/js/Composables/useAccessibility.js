/**
 * Central Accessibility Composable for CONECTA EGRESSO (SEJUS/ES)
 * Conforms to WCAG 2.1 AA / AAA and e-MAG guidelines.
 */
import { ref, watch, onMounted } from 'vue';

export const MIN_ZOOM = 1.00;
export const MAX_ZOOM = 1.50;
export const ZOOM_STEP = 0.18;

// Shared singleton state across all component instances
const highContrast = ref(false);
const fontZoom = ref(1.00);
const simplifiedLanguage = ref(false);

// Simplified Language Dictionary with fallback engine
const dictionary = {
  'pt-BR': {
    dashboard_title: 'Painel de Gestão e Monitoramento de Egressos',
    atendimento_title: 'Atendimento Remoto e Videochamadas Seguras',
    oportunidades_title: 'Painel de Oportunidades & Qualificação Profissional',
    carteira_title: 'Carteira de Identificação Digital do Egresso',
    carteira_digital: 'Carteira de Identificação Digital do Egresso',
    geolocalizacao_title: 'Mapeamento Territorial dos 78 Municípios do ES',
    prontuario_title: 'Prontuário Único do Egresso & Registros Automáticos',
    relatorios_title: 'Relatórios Sintéticos & Detalhados SEJUS',
    seguranca_title: 'Segurança da Informação, LGPD & Níveis de Acesso',
    prontuario_evolution: 'Registro de Evolução Técnica Multidisciplinar',
    affirmative_vacancy: 'Vaga Afirmativa com Cota Legal para Reintegração',
    audiencia_custodia: 'Audiência de Custódia e Acompanhamento Penal',
    validation_status_valid: 'Documento Oficial Autêntico e Homologado pela SEJUS/ES',
    fallback_only_key: 'Texto Padrão sem Equivalente Simplificado',
    
    // Direct terms
    'Evolução Psicossocial': 'Evolução Psicossocial',
    'Trilha de Auditoria Imutável': 'Trilha de Auditoria Imutável',
    'Telemetria WebRTC': 'Telemetria WebRTC',
    'Blind Index LGPD': 'Blind Index LGPD',
    'Geolocalização dos 78 Municípios': 'Geolocalização dos 78 Municípios',
    'Vagas Afirmativas': 'Vagas Afirmativas',
    'Livramento Condicional': 'Livramento Condicional',
    'Escritório Social': 'Escritório Social',
    'Sinalização SDP/ICE': 'Sinalização SDP/ICE',
  },
  'pt-BR-facil': {
    dashboard_title: 'Página Principal',
    atendimento_title: 'Conversa em Vídeo com Assistente Social',
    oportunidades_title: 'Vagas de Trabalho e Cursos Gratuitos',
    carteira_title: 'Seu Documento Digital',
    carteira_digital: 'Seu Documento Digital',
    geolocalizacao_title: 'Ajuda e Serviços Perto de Você',
    prontuario_title: 'Seu Histórico de Atendimentos',
    relatorios_title: 'Resumo das Atividades',
    seguranca_title: 'Proteção dos Seus Dados (LGPD)',
    prontuario_evolution: 'Anotações do seu Atendimento',
    affirmative_vacancy: 'Vaga de Trabalho Reservada para Você',
    validation_status_valid: 'Documento Verdadeiro e Válido',
    
    // Direct terms
    'Evolução Psicossocial': 'Anotações e Histórico de Ajuda',
    'Trilha de Auditoria Imutável': 'Histórico Seguro que Ninguém Pode Mudar',
    'Telemetria WebRTC': 'Qualidade da Conexão da Chamada',
    'Blind Index LGPD': 'Proteção Segura dos seus Dados Pessoais',
    'Geolocalização dos 78 Municípios': 'Mapa de Oportunidades e Cidades do ES',
    'Vagas Afirmativas': 'Empregos Reservados com Apoio SEJUS',
    'Livramento Condicional': 'Período de Acompanhamento em Liberdade',
    'Escritório Social': 'Lugar de Apoio e Atendimento ao Cidadão',
    'Sinalização SDP/ICE': 'Conexão Automática do Vídeo',
  }
};

export function useAccessibility() {
  const clampZoom = (val) => {
    return Math.min(MAX_ZOOM, Math.max(MIN_ZOOM, Math.round(Number(val) * 100) / 100));
  };

  const applyHighContrast = (active) => {
    if (typeof document === 'undefined') return;
    if (active) {
      document.documentElement.classList.add('high-contrast');
      document.body.classList.add('high-contrast');
    } else {
      document.documentElement.classList.remove('high-contrast');
      document.body.classList.remove('high-contrast');
    }
    try {
      localStorage.setItem('conecta_high_contrast', active ? 'true' : 'false');
    } catch (e) {
      // safe fallback
    }
  };

  const applyFontZoom = (val) => {
    if (typeof document === 'undefined') return;
    const clamped = clampZoom(val);
    document.documentElement.style.setProperty('--font-scale', clamped.toString());
    if (document.body) {
      document.body.style.setProperty('--font-scale', clamped.toString());
    }
    try {
      localStorage.setItem('conecta_font_zoom', clamped.toFixed(2));
    } catch (e) {
      // safe fallback
    }
  };

  const applySimplifiedLanguage = (active) => {
    if (typeof document === 'undefined') return;
    if (active) {
      document.documentElement.classList.add('simplified-lang');
      document.body.classList.add('simplified-lang');
    } else {
      document.documentElement.classList.remove('simplified-lang');
      document.body.classList.remove('simplified-lang');
    }
    try {
      localStorage.setItem('conecta_simplified_language', active ? 'true' : 'false');
    } catch (e) {
      // safe fallback
    }
  };

  const initAccessibility = () => {
    if (typeof window === 'undefined') return;

    // 1. High Contrast
    try {
      const savedContrast = localStorage.getItem('conecta_high_contrast');
      highContrast.value = savedContrast === 'true';
    } catch (e) {
      highContrast.value = false;
    }
    applyHighContrast(highContrast.value);

    // 2. Font Zoom
    try {
      const savedZoom = parseFloat(localStorage.getItem('conecta_font_zoom') || '1.00');
      fontZoom.value = isNaN(savedZoom) ? 1.00 : clampZoom(savedZoom);
    } catch (e) {
      fontZoom.value = 1.00;
    }
    applyFontZoom(fontZoom.value);

    // 3. Simplified Language
    try {
      const savedSimplified = localStorage.getItem('conecta_simplified_language');
      simplifiedLanguage.value = savedSimplified === 'true';
    } catch (e) {
      simplifiedLanguage.value = false;
    }
    applySimplifiedLanguage(simplifiedLanguage.value);
  };

  const toggleHighContrast = () => {
    highContrast.value = !highContrast.value;
    applyHighContrast(highContrast.value);
    return highContrast.value;
  };

  const zoomIn = () => {
    fontZoom.value = clampZoom(fontZoom.value + ZOOM_STEP);
    applyFontZoom(fontZoom.value);
    return fontZoom.value;
  };

  const zoomOut = () => {
    fontZoom.value = clampZoom(fontZoom.value - ZOOM_STEP);
    applyFontZoom(fontZoom.value);
    return fontZoom.value;
  };

  const resetZoom = () => {
    fontZoom.value = 1.00;
    applyFontZoom(1.00);
    return 1.00;
  };

  const toggleSimplifiedLanguage = () => {
    simplifiedLanguage.value = !simplifiedLanguage.value;
    applySimplifiedLanguage(simplifiedLanguage.value);
    return simplifiedLanguage.value;
  };

  const t = (key) => {
    const locale = simplifiedLanguage.value ? 'pt-BR-facil' : 'pt-BR';
    if (dictionary[locale] && dictionary[locale][key] !== undefined) {
      return dictionary[locale][key];
    }
    if (dictionary['pt-BR'] && dictionary['pt-BR'][key] !== undefined) {
      return dictionary['pt-BR'][key];
    }
    return `[${key}]`;
  };

  return {
    highContrast,
    fontZoom,
    simplifiedLanguage,
    initAccessibility,
    toggleHighContrast,
    zoomIn,
    zoomOut,
    resetZoom,
    toggleSimplifiedLanguage,
    t,
    MIN_ZOOM,
    MAX_ZOOM,
    ZOOM_STEP,
  };
}
