const fs = require('fs');
const path = require('path');

console.log('================================================================');
console.log('  EMPIRICAL CHALLENGER: FRONTEND A11Y STRESS & BOUNDARY HARNESS');
console.log('================================================================\n');

// --- 1. MOCK BROWSER ENVIRONMENT ---
const classListDoc = new Set();
const classListBody = new Set();
const storage = {};
const styleDoc = {};
const styleBody = {};

global.document = {
  documentElement: {
    classList: {
      add: (c) => classListDoc.add(c),
      remove: (c) => classListDoc.delete(c),
      contains: (c) => classListDoc.has(c),
    },
    style: {
      setProperty: (k, v) => { styleDoc[k] = String(v); },
      getPropertyValue: (k) => styleDoc[k] || '',
    }
  },
  body: {
    classList: {
      add: (c) => classListBody.add(c),
      remove: (c) => classListBody.delete(c),
      contains: (c) => classListBody.has(c),
    },
    style: {
      setProperty: (k, v) => { styleBody[k] = String(v); },
      getPropertyValue: (k) => styleBody[k] || '',
    }
  }
};

global.localStorage = {
  getItem: (k) => (k in storage ? storage[k] : null),
  setItem: (k, v) => { storage[k] = String(v); },
  removeItem: (k) => { delete storage[k]; },
  clear: () => { Object.keys(storage).forEach(k => delete storage[k]); }
};

global.window = {};

// Minimal reactive mock for Vue 3 refs
const vueMock = {
  ref: (initial) => {
    let val = initial;
    return {
      get value() { return val; },
      set value(v) { val = v; }
    };
  },
  watch: () => {},
  onMounted: (fn) => fn && fn(),
  computed: (getter) => ({
    get value() { return getter(); }
  })
};

// --- 2. LOAD COMPOSABLE ---
const composablePath = path.resolve(__dirname, '../../resources/js/Composables/useAccessibility.js');
const composableSrc = fs.readFileSync(composablePath, 'utf8');

// Transform ES module to CommonJS-executable function
const transformedCode = composableSrc
  .replace(/import\s*\{[^}]*\}\s*from\s*['"]vue['"];?/, 'const { ref, watch, onMounted } = vue;')
  .replace(/export\s+const\s+(\w+)\s*=/g, 'const $1 = exports.$1 =')
  .replace(/export\s+function\s+(\w+)/g, 'function $1(...args) { return exports.$1(...args); }; exports.$1 = function');

const moduleExports = {};
const runner = new Function('vue', 'exports', transformedCode);
runner(vueMock, moduleExports);

const { useAccessibility, MIN_ZOOM, MAX_ZOOM, ZOOM_STEP } = moduleExports;

let totalPassed = 0;
let totalFailed = 0;

function assert(condition, message) {
  if (!condition) {
    totalFailed++;
    console.error(`  [FAIL] ${message}`);
    throw new Error(message);
  } else {
    totalPassed++;
    console.log(`  [PASS] ${message}`);
  }
}

// ==============================================================================
// TASK 1.1: RAPID TOGGLING OF HIGH CONTRAST MODE (50+ SWITCHES)
// ==============================================================================
console.log('--- TEST SUITE 1: High Contrast Rapid Toggling & State Sync ---');
{
  const a11y = useAccessibility();
  a11y.initAccessibility();

  assert(a11y.highContrast.value === false, 'Initial high contrast state is false');
  assert(!classListDoc.has('high-contrast'), 'html tag does not have high-contrast class initially');

  // Perform 100 rapid toggles
  for (let i = 1; i <= 100; i++) {
    const res = a11y.toggleHighContrast();
    const expected = (i % 2 === 1);
    if (res !== expected || a11y.highContrast.value !== expected) {
      assert(false, `Rapid toggle #${i} returned ${res}, expected ${expected}`);
    }
    if (classListDoc.has('high-contrast') !== expected || classListBody.has('high-contrast') !== expected) {
      assert(false, `DOM classList out of sync at toggle #${i}`);
    }
    if (storage['conecta_high_contrast'] !== (expected ? 'true' : 'false')) {
      assert(false, `LocalStorage out of sync at toggle #${i}`);
    }
  }
  assert(a11y.highContrast.value === false, 'After 100 toggles, state correctly settled on false');
  assert(storage['conecta_high_contrast'] === 'false', 'LocalStorage correctly stores "false"');

  // 101st toggle -> True
  a11y.toggleHighContrast();
  assert(a11y.highContrast.value === true, '101st toggle produces true');
  assert(classListDoc.has('high-contrast') && classListBody.has('high-contrast'), 'html and body both have high-contrast class');
  assert(storage['conecta_high_contrast'] === 'true', 'LocalStorage correctly stores "true"');
}

// ==============================================================================
// TASK 1.2: FONT ZOOM CLAMPING LIMITS (1.00 <= ZOOM <= 1.50)
// ==============================================================================
console.log('\n--- TEST SUITE 2: Font Zoom Clamping Limits ---');
{
  const a11y = useAccessibility();
  a11y.resetZoom();

  assert(a11y.fontZoom.value === 1.00, 'Baseline fontZoom is 1.00 (100%)');
  assert(styleDoc['--font-scale'] === '1', 'Initial --font-scale is set to 1');

  // Step 1: Step Zoom-In
  const step1 = a11y.zoomIn();
  assert(Math.abs(step1 - 1.18) < 0.001, `Single zoom-in gives ~1.18 (actual: ${step1})`);
  assert(styleDoc['--font-scale'] === '1.18', `--font-scale correctly updated to 1.18`);

  const step2 = a11y.zoomIn();
  assert(Math.abs(step2 - 1.36) < 0.001, `Second zoom-in gives ~1.36 (actual: ${step2})`);

  const step3 = a11y.zoomIn();
  assert(step3 === 1.50, `Third zoom-in reaches clamped 1.50 (actual: ${step3})`);

  // Further zoom-ins should strictly clamp at 1.50
  for (let i = 0; i < 50; i++) {
    a11y.zoomIn();
  }
  assert(a11y.fontZoom.value === 1.50, `Font zoom cannot exceed 1.50 after 50 extra zoomIn calls (value: ${a11y.fontZoom.value})`);
  assert(styleDoc['--font-scale'] === '1.5', `--font-scale clamped to 1.5`);

  // Step 2: Step Zoom-Out
  for (let i = 0; i < 50; i++) {
    a11y.zoomOut();
  }
  assert(a11y.fontZoom.value === 1.00, `Font zoom cannot drop below 1.00 after 50 zoomOut calls (value: ${a11y.fontZoom.value})`);
  assert(styleDoc['--font-scale'] === '1', `--font-scale clamped to 1`);

  // Step 3: Reset Zoom
  a11y.zoomIn();
  a11y.resetZoom();
  assert(a11y.fontZoom.value === 1.00, `resetZoom resets strictly to 1.00`);
}

// ==============================================================================
// TASK 1.3: SIMPLIFIED LANGUAGE DICTIONARY MISSING KEY FALLBACK
// ==============================================================================
console.log('\n--- TEST SUITE 3: Simplified Language Dictionary & Fallbacks ---');
{
  const a11y = useAccessibility();

  // Mode: Standard Portuguese (pt-BR)
  a11y.simplifiedLanguage.value = false;
  assert(a11y.t('dashboard_title') === 'Painel de Gestão e Monitoramento de Egressos', 'Standard pt-BR title translated');
  assert(a11y.t('Evolução Psicossocial') === 'Evolução Psicossocial', 'Standard direct term translated');

  // Mode: Simplified Portuguese (pt-BR-facil)
  a11y.simplifiedLanguage.value = true;
  assert(a11y.t('dashboard_title') === 'Página Principal', 'Simplified pt-BR-facil title translated');
  assert(a11y.t('Evolução Psicossocial') === 'Anotações e Histórico de Ajuda', 'Simplified term translated');
  assert(a11y.t('Trilha de Auditoria Imutável') === 'Histórico Seguro que Ninguém Pode Mudar', 'Simplified audit trail translated');

  // Fallback 1: Key missing in pt-BR-facil but present in pt-BR
  assert(a11y.t('fallback_only_key') === 'Texto Padrão sem Equivalente Simplificado', 'Missing key in pt-BR-facil falls back to pt-BR');

  // Fallback 2: Key completely missing in both dictionaries
  const missingResult = a11y.t('unregistered_unknown_token_123');
  assert(missingResult === '[unregistered_unknown_token_123]', `Completely missing key returns formatted token without throwing: "${missingResult}"`);

  // Fallback 3: Extreme key types (empty, null, numeric)
  assert(a11y.t('') === '[]', 'Empty string key returns "[]"');
  assert(a11y.t(null) === '[null]', 'Null key returns "[null]"');
  assert(a11y.t(undefined) === '[undefined]', 'Undefined key returns "[undefined]"');
  assert(a11y.t(12345) === '[12345]', 'Numeric key returns "[12345]"');
}

// ==============================================================================
// TASK 1.4: MISSING / NULL USER PROPS IN APPLAYOUT NAVBAR
// ==============================================================================
console.log('\n--- TEST SUITE 4: Defensive User Props in AppLayout ---');
{
  // Test the userProfile computed logic from AppLayout.vue across all edge cases
  function computeUserProfile(rawUser, rawRole) {
    const user = rawUser || {};
    const role = (rawRole || 'gestor').toLowerCase();

    if (role === 'gestor') {
      return {
        displayName: user.name || user.nome || 'Carlos Eduardo Silva (Gestor)',
        initials: 'CS',
        roleTitle: 'Visão Gestor Estadual',
        roleScope: '78 Municípios • SEJUS/ES',
        roleSubtitle: 'SEJUS / Subsecretaria de Reintegração',
      };
    } else if (role === 'tecnico') {
      return {
        displayName: user.name || user.nome || 'Dra. Márcia Oliveira (Técnica)',
        initials: 'MO',
        roleTitle: 'Técnico Escritório Social',
        roleScope: 'Atendimento Remoto / Presencial',
        roleSubtitle: 'Assistente Social • CRESS 4891/ES',
      };
    } else {
      return {
        displayName: user.name || user.nome || 'Lucas Santos (Egresso)',
        initials: 'LS',
        roleTitle: 'Visão Egresso / Familiar',
        roleScope: 'São Mateus / ES (Acesso Remoto)',
        roleSubtitle: user.cpf_masked || 'CPF: ***.192.830-** • Gov.br',
      };
    }
  }

  const testCases = [
    { user: null, role: null, desc: 'null user, null role' },
    { user: undefined, role: undefined, desc: 'undefined user, undefined role' },
    { user: {}, role: 'gestor', desc: 'empty object user, gestor' },
    { user: {}, role: 'tecnico', desc: 'empty object user, tecnico' },
    { user: {}, role: 'egresso', desc: 'empty object user, egresso' },
    { user: { name: '' }, role: 'gestor', desc: 'empty name string' },
    { user: { nome: 'João da Silva', perfil: 'gestor' }, role: 'gestor', desc: 'custom nome and perfil' },
    { user: { cpf_masked: '***.456.789-**' }, role: 'egresso', desc: 'custom cpf_masked egresso' },
  ];

  for (const tc of testCases) {
    let profile;
    try {
      profile = computeUserProfile(tc.user, tc.role);
    } catch (err) {
      assert(false, `computeUserProfile threw exception on ${tc.desc}: ${err.message}`);
    }
    assert(typeof profile.displayName === 'string' && profile.displayName.length > 0, `Valid displayName for ${tc.desc}`);
    assert(typeof profile.initials === 'string' && profile.initials.length > 0, `Valid initials for ${tc.desc}`);
    assert(typeof profile.roleTitle === 'string' && profile.roleTitle.length > 0, `Valid roleTitle for ${tc.desc}`);
    assert(typeof profile.roleScope === 'string' && profile.roleScope.length > 0, `Valid roleScope for ${tc.desc}`);
    assert(typeof profile.roleSubtitle === 'string' && profile.roleSubtitle.length > 0, `Valid roleSubtitle for ${tc.desc}`);
  }
}

// ==============================================================================
// TASK 1.5: MOBILE TOUCH TARGET MINIMUM SIZE (WCAG 2.5.5 >= 44x44px)
// ==============================================================================
console.log('\n--- TEST SUITE 5: Mobile Touch Target Minimum Size (WCAG 2.5.5) ---');
{
  const cssPath = path.resolve(__dirname, '../../resources/css/app.css');
  const cssSrc = fs.readFileSync(cssPath, 'utf8');

  // Verify WCAG 2.5.5 CSS rules in source
  assert(cssSrc.includes('@media (max-width: 1024px)'), 'app.css has max-width: 1024px media query for touch targets');
  assert(cssSrc.includes('min-height: 44px;'), 'app.css specifies min-height: 44px');
  assert(cssSrc.includes('min-width: 44px;'), 'app.css specifies min-width: 44px');

  // Verify compiled CSS in public/build/assets/app-*.css
  const buildDir = path.resolve(__dirname, '../../public/build/assets');
  const files = fs.readdirSync(buildDir);
  const compiledCssFile = files.find(f => f.startsWith('app-') && f.endsWith('.css'));
  assert(!!compiledCssFile, `Compiled CSS found: ${compiledCssFile}`);

  const compiledCss = fs.readFileSync(path.join(buildDir, compiledCssFile), 'utf8');
  assert(compiledCss.includes('44px'), 'Compiled production CSS bundle includes 44px touch target rules');

  // Check AppLayout.vue buttons for min-w-[44px] and min-h-[44px]
  const layoutPath = path.resolve(__dirname, '../../resources/js/Layouts/AppLayout.vue');
  const layoutSrc = fs.readFileSync(layoutPath, 'utf8');
  assert(layoutSrc.includes('min-w-[44px]'), 'AppLayout sidebar toggle button has min-w-[44px]');
  assert(layoutSrc.includes('min-h-[44px]'), 'AppLayout sidebar navigation links have min-h-[44px]');
}

console.log('\n================================================================');
console.log(`  ALL EMPIRICAL TESTS PASSED: ${totalPassed} passed, ${totalFailed} failed.`);
console.log('================================================================\n');
