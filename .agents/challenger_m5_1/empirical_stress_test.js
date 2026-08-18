import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

console.log('================================================================');
console.log('  EMPIRICAL CHALLENGER: FRONTEND A11Y STRESS & BOUNDARY HARNESS (ESM)');
console.log('================================================================\n');

// Mock browser globals
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

const composablePath = path.resolve(__dirname, '../../resources/js/Composables/useAccessibility.js');
const composableSrc = fs.readFileSync(composablePath, 'utf8');

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

// 1. High Contrast Toggling
console.log('--- TEST SUITE 1: High Contrast Rapid Toggling & State Sync ---');
{
  const a11y = useAccessibility();
  a11y.initAccessibility();

  assert(a11y.highContrast.value === false, 'Initial high contrast state is false');

  for (let i = 1; i <= 100; i++) {
    const res = a11y.toggleHighContrast();
    const expected = (i % 2 === 1);
    if (res !== expected || a11y.highContrast.value !== expected) {
      assert(false, `Rapid toggle #${i} returned ${res}, expected ${expected}`);
    }
  }
  assert(a11y.highContrast.value === false, 'After 100 toggles, state correctly settled on false');
  a11y.toggleHighContrast();
  assert(a11y.highContrast.value === true, '101st toggle produces true');
}

// 2. Font Zoom Clamping
console.log('\n--- TEST SUITE 2: Font Zoom Clamping Limits ---');
{
  const a11y = useAccessibility();
  a11y.resetZoom();
  assert(a11y.fontZoom.value === 1.00, 'Baseline fontZoom is 1.00');

  for (let i = 0; i < 50; i++) a11y.zoomIn();
  assert(a11y.fontZoom.value === 1.50, `Font zoom cannot exceed 1.50 (value: ${a11y.fontZoom.value})`);

  for (let i = 0; i < 50; i++) a11y.zoomOut();
  assert(a11y.fontZoom.value === 1.00, `Font zoom cannot drop below 1.00 (value: ${a11y.fontZoom.value})`);
}

// 3. Simplified Language Fallbacks
console.log('\n--- TEST SUITE 3: Simplified Language Dictionary & Fallbacks ---');
{
  const a11y = useAccessibility();
  a11y.simplifiedLanguage.value = true;
  assert(a11y.t('dashboard_title') === 'Página Principal', 'Simplified title translated');
  assert(a11y.t('fallback_only_key') === 'Texto Padrão sem Equivalente Simplificado', 'Fallback to standard pt-BR');
  assert(a11y.t('missing_key_999') === '[missing_key_999]', 'Missing token formatted');
}

// 4. Touch Target Rules
console.log('\n--- TEST SUITE 4: Mobile Touch Target Minimum Size ---');
{
  const cssPath = path.resolve(__dirname, '../../resources/css/app.css');
  const cssSrc = fs.readFileSync(cssPath, 'utf8');
  assert(cssSrc.includes('min-height: 44px;'), 'app.css specifies min-height: 44px');
  assert(cssSrc.includes('min-width: 44px;'), 'app.css specifies min-width: 44px');
}

console.log(`\nALL ESM STRESS TESTS PASSED (${totalPassed} tests).`);
