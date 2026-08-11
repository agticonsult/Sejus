/* ==========================================================================
   CONECTA EGRESSO - SEJUS / GOVERNO DO ESTADO DO ESPÍRITO SANTO
   Application Logic & UI Controllers (Vanilla JavaScript)
   ========================================================================== */

document.addEventListener('DOMContentLoaded', () => {
  initNavigation();
  initRoleSwitcher();
  initAccessibility();
  renderCharts();
});

/* --------------------------------------------------------------------------
   1. NAVIGATION SYSTEM (SPA ROUTING)
   -------------------------------------------------------------------------- */
function initNavigation() {
  const navItems = document.querySelectorAll('.nav-item');
  
  navItems.forEach(item => {
    item.addEventListener('click', (e) => {
      e.preventDefault();
      const targetView = item.getAttribute('data-view');
      switchView(targetView);
    });
  });

  // Sidebar collapse toggle
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebar = document.getElementById('sidebar');

  sidebarToggleBtn.addEventListener('click', () => {
    sidebar.classList.toggle('collapsed');
  });
}

function switchView(viewId) {
  // Update active tab in sidebar
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach(item => {
    if (item.getAttribute('data-view') === viewId) {
      item.classList.add('active');
    } else {
      item.classList.remove('active');
    }
  });

  // Update view panel visibility
  const viewPanels = document.querySelectorAll('.view-panel');
  viewPanels.forEach(panel => {
    if (panel.id === `view-${viewId}`) {
      panel.classList.add('active');
    } else {
      panel.classList.remove('active');
    }
  });

  // Scroll to top smooth
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

/* --------------------------------------------------------------------------
   2. PROFILE SWITCHER (USER ROLE CONTROL)
   -------------------------------------------------------------------------- */
function initRoleSwitcher() {
  const userRoleSelect = document.getElementById('userRoleSelect');
  const userNameHeader = document.getElementById('userNameHeader');
  const userCpfHeader = document.getElementById('userCpfHeader');
  const sidebarAvatar = document.getElementById('sidebarAvatar');
  const sidebarRoleTitle = document.getElementById('sidebarRoleTitle');
  const sidebarRoleScope = document.getElementById('sidebarRoleScope');

  userRoleSelect.addEventListener('change', (e) => {
    const role = e.target.value;

    if (role === 'gestor') {
      userNameHeader.textContent = 'Carlos Eduardo Silva (Gestor)';
      userCpfHeader.textContent = 'SEJUS / Subsecretaria de Reintegração';
      sidebarAvatar.textContent = 'CS';
      sidebarRoleTitle.textContent = 'Visão Gestor Estadual';
      sidebarRoleScope.textContent = '78 Municípios • SEJUS/ES';
      alert('🔒 Perfil alterado para: GESTOR SEJUS. Acesso total a estatísticas, relatórios e auditoria da política pública.');
    } else if (role === 'tecnico') {
      userNameHeader.textContent = 'Dra. Márcia Oliveira';
      userCpfHeader.textContent = 'Assistente Social • CRESS 4891/ES';
      sidebarAvatar.textContent = 'MO';
      sidebarRoleTitle.textContent = 'Técnico Escritório Social';
      sidebarRoleScope.textContent = 'Atendimento Remoto / Presencial';
      alert('🩺 Perfil alterado para: TÉCNICO / ATENDENTE. Foco na fila de videochamadas e registros de prontuário.');
    } else if (role === 'egresso') {
      userNameHeader.textContent = 'Lucas Santos (Egresso)';
      userCpfHeader.textContent = 'CPF: ***.192.830-** • Gov.br';
      sidebarAvatar.textContent = 'LS';
      sidebarRoleTitle.textContent = 'Visão Egresso / Familiar';
      sidebarRoleScope.textContent = 'São Mateus / ES (Acesso Remoto)';
      alert('👤 Perfil alterado para: EGRESSO / FAMILIAR. Interface simplificada para agendamentos, vagas e documentos.');
    }
  });
}

/* --------------------------------------------------------------------------
   3. ACCESSIBILITY CONTROLS
   -------------------------------------------------------------------------- */
function initAccessibility() {
  const contrastBtn = document.getElementById('contrastBtn');
  const fontSizeBtn = document.getElementById('fontSizeBtn');
  const simplifiedTextBtn = document.getElementById('simplifiedTextBtn');

  contrastBtn.addEventListener('click', () => {
    document.body.classList.toggle('high-contrast');
  });

  let fontEnlarged = false;
  fontSizeBtn.addEventListener('click', () => {
    fontEnlarged = !fontEnlarged;
    if (fontEnlarged) {
      document.documentElement.style.setProperty('--font-scale', '1.18');
      fontSizeBtn.style.backgroundColor = '#0284c7';
      fontSizeBtn.style.color = '#ffffff';
    } else {
      document.documentElement.style.setProperty('--font-scale', '1');
      fontSizeBtn.style.backgroundColor = '';
      fontSizeBtn.style.color = '';
    }
  });

  simplifiedTextBtn.addEventListener('click', () => {
    document.body.classList.toggle('simplified-lang');
    alert('💬 Modo Linguagem Simplificada ativado! Textos com fontes ampliadas e vocabulário acessível para pessoas com variados níveis de letramento digital.');
  });
}

/* --------------------------------------------------------------------------
   4. CANVAS CHARTS SIMULATION (HTML5 CANVAS)
   -------------------------------------------------------------------------- */
function renderCharts() {
  renderMunicipiosChart();
  renderReintegracaoChart();
}

function renderMunicipiosChart() {
  const canvas = document.getElementById('chartMunicipios');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  
  // Set resolution
  canvas.width = canvas.parentElement.clientWidth;
  canvas.height = 230;
  
  const width = canvas.width;
  const height = canvas.height;

  const data = [
    { label: 'Vitória', val: 3420, color: '#003366' },
    { label: 'Serra', val: 2910, color: '#0284c7' },
    { label: 'Vila Velha', val: 2450, color: '#38bdf8' },
    { label: 'Cariacica', val: 2100, color: '#10b981' },
    { label: 'Linhares*', val: 1150, color: '#8b5cf6' },
    { label: 'Cachoeiro*', val: 980, color: '#f59e0b' },
    { label: 'Colatina*', val: 740, color: '#ec4899' },
    { label: 'São Mateus*', val: 610, color: '#14b8a6' }
  ];

  const maxVal = 4000;
  const barWidth = (width - 60) / data.length;

  ctx.clearRect(0, 0, width, height);

  // Draw grid lines
  ctx.strokeStyle = '#e2e8f0';
  ctx.lineWidth = 1;
  for (let i = 0; i <= 4; i++) {
    const y = height - 40 - (i * (height - 60) / 4);
    ctx.beginPath();
    ctx.moveTo(40, y);
    ctx.lineTo(width - 10, y);
    ctx.stroke();
    
    ctx.fillStyle = '#94a3b8';
    ctx.font = '10px Inter';
    ctx.fillText(`${(maxVal / 4) * i}`, 5, y + 3);
  }

  // Draw bars
  data.forEach((item, index) => {
    const x = 45 + index * barWidth;
    const barH = (item.val / maxVal) * (height - 60);
    const y = height - 40 - barH;

    ctx.fillStyle = item.color;
    ctx.beginPath();
    ctx.roundRect(x + 6, y, barWidth - 12, barH, [4, 4, 0, 0]);
    ctx.fill();

    // Value text
    ctx.fillStyle = '#1e293b';
    ctx.font = 'bold 10px Inter';
    ctx.fillText(item.val, x + 8, y - 6);

    // Label text
    ctx.fillStyle = '#64748b';
    ctx.font = '10px Inter';
    ctx.fillText(item.label, x + 4, height - 20);
  });
}

function renderReintegracaoChart() {
  const canvas = document.getElementById('chartReintegracao');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  
  canvas.width = canvas.parentElement.clientWidth;
  canvas.height = 230;
  
  const width = canvas.width;
  const height = canvas.height;

  // Donut chart simulation
  const centerX = width / 3;
  const centerY = height / 2;
  const radius = 75;

  const slices = [
    { label: 'Emprego & Renda (42%)', val: 0.42, color: '#10b981' },
    { label: 'Cursos & Capacitação (28%)', val: 0.28, color: '#8b5cf6' },
    { label: 'Apoio Psicossocial (18%)', val: 0.18, color: '#0284c7' },
    { label: 'Documentação Emitida (12%)', val: 0.12, color: '#f59e0b' }
  ];

  let startAngle = -Math.PI / 2;

  slices.forEach(slice => {
    const sliceAngle = slice.val * 2 * Math.PI;
    
    ctx.fillStyle = slice.color;
    ctx.beginPath();
    ctx.moveTo(centerX, centerY);
    ctx.arc(centerX, centerY, radius, startAngle, startAngle + sliceAngle);
    ctx.closePath();
    ctx.fill();

    startAngle += sliceAngle;
  });

  // Donut hole
  ctx.fillStyle = '#ffffff';
  ctx.beginPath();
  ctx.arc(centerX, centerY, 45, 0, 2 * Math.PI);
  ctx.fill();

  // Center text
  ctx.fillStyle = '#0f172a';
  ctx.font = 'bold 14px Outfit';
  ctx.textAlign = 'center';
  ctx.fillText('100%', centerX, centerY - 2);
  ctx.font = '10px Inter';
  ctx.fillStyle = '#64748b';
  ctx.fillText('Efetividade', centerX, centerY + 12);

  // Draw Legend on right side
  ctx.textAlign = 'left';
  slices.forEach((slice, index) => {
    const lx = width / 1.7;
    const ly = 50 + index * 36;

    ctx.fillStyle = slice.color;
    ctx.beginPath();
    ctx.arc(lx, ly, 6, 0, 2 * Math.PI);
    ctx.fill();

    ctx.fillStyle = '#1e293b';
    ctx.font = 'bold 11px Inter';
    ctx.fillText(slice.label, lx + 14, ly + 4);
  });
}

/* --------------------------------------------------------------------------
   5. INTERACTIVE MAP FUNCTIONS
   -------------------------------------------------------------------------- */
function selectMunicipality(name, demandCount, typeText) {
  document.getElementById('selectedMuniName').textContent = `${name}`;
  document.getElementById('selectedMuniDemand').textContent = demandCount.toLocaleString('pt-BR');
  document.getElementById('selectedMuniType').textContent = typeText;
  
  // Highlight active button
  const btns = document.querySelectorAll('.map-muni-btn');
  btns.forEach(btn => {
    if (btn.textContent.includes(name)) {
      btn.classList.add('active');
    } else {
      btn.classList.remove('active');
    }
  });
}

/* --------------------------------------------------------------------------
   6. VIDEO CALL MODAL SIMULATION
   -------------------------------------------------------------------------- */
function openVideoCallModal(userName, municipality) {
  document.getElementById('simUserName').textContent = userName;
  document.getElementById('simMuni').textContent = `Conectado via Smartphone • ${municipality}/ES (Sinal 4G OK)`;
  document.getElementById('videoModal').classList.add('active');
}

function closeVideoCallModal() {
  document.getElementById('videoModal').classList.remove('active');
}

function endCallMock() {
  alert('📞 Atendimento por vídeo encerrado com sucesso. Os registros de duração e atendimento foram gravados no Prontuário.');
}

function saveCallRecord() {
  const enc = document.getElementById('callEncaminhamentoType').value;
  alert(`💾 Registro salvo com sucesso no Prontuário do Egresso!\nEncaminhamento: ${enc}\nAutenticado com carimbo de data, hora e responsável.`);
}

/* --------------------------------------------------------------------------
   7. USER ACTION SIMULATIONS
   -------------------------------------------------------------------------- */
function applyOpportunity(oppTitle) {
  alert(`✉️ Egresso encaminhado com sucesso para a oportunidade: "${oppTitle}"!\nSua inscrição foi enviada para o parceiro conveniado SEJUS.`);
}

function requestDoc(docType) {
  alert(`💳 Requisição de 2ª via para "${docType}" gerada com sucesso!\nO egresso receberá notificação por SMS/WhatsApp com a data de retirada no ponto mais próximo.`);
}

function showAddOpportunityModal() {
  alert('➕ Modal de Cadastro de Oportunidade: Permite a empresas parceiras do ES cadastrar novas vagas com isenção fiscal/parceria SEJUS.');
}
