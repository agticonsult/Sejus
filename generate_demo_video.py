import asyncio
import os
import shutil
from playwright.async_api import async_playwright

BASE_URL = "http://127.0.0.1:8000"
VIDEO_DIR = os.path.abspath("videos/raw")
FINAL_VIDEO_DIR = os.path.abspath("videos")

async def record_demo():
    os.makedirs(VIDEO_DIR, exist_ok=True)
    os.makedirs(FINAL_VIDEO_DIR, exist_ok=True)
    
    async with async_playwright() as p:
        browser = await p.chromium.launch(
            headless=True,
            args=[
                "--no-sandbox",
                "--disable-dev-shm-usage",
                "--use-fake-ui-for-media-stream",
                "--use-fake-device-for-media-stream",
            ]
        )
        
        # 1920x1080 Full HD Context with Video Recording
        context = await browser.new_context(
            viewport={"width": 1920, "height": 1080},
            record_video_dir=VIDEO_DIR,
            record_video_size={"width": 1920, "height": 1080},
            locale="pt-BR",
            permissions=["camera", "microphone"]
        )
        
        page = await context.new_page()
        
        # ---------------------------------------------------------------------
        # 1. TELA DE LOGIN & APRESENTAÇÃO INSTITUCIONAL
        # ---------------------------------------------------------------------
        print(">>> [1/9] Tela de Login Institucional Gov.br...")
        await page.goto(f"{BASE_URL}/login", wait_until="networkidle")
        await page.wait_for_timeout(2000)
        
        # Alterna modo escuro / claro na tela de login
        dark_btn = page.locator("#darkModeBtn")
        if await dark_btn.is_visible():
            await dark_btn.click()
            await page.wait_for_timeout(1200)
            await dark_btn.click()
            await page.wait_for_timeout(1000)

        # ---------------------------------------------------------------------
        # 2. LOGIN TÉCNICA ESPECIALISTA (Dra. Márcia Oliveira)
        # ---------------------------------------------------------------------
        print(">>> [2/9] Login como Técnica Especialista SEJUS (Dra. Márcia)...")
        await page.fill("#loginIdentifier", "marcia.oliveira@sejus.es.gov.br")
        await page.wait_for_timeout(400)
        await page.fill("#loginPassword", "secret123")
        await page.wait_for_timeout(400)
        await page.click("#loginSubmitBtn")
        await page.wait_for_url("**/dashboard", timeout=20000)
        await page.wait_for_timeout(2500)

        # ---------------------------------------------------------------------
        # 3. VISÃO DO TÉCNICO: DASHBOARD & PRONTUÁRIOS
        # ---------------------------------------------------------------------
        print(">>> [3/9] Visão do Técnico: Dashboard SEJUS & Prontuário Eletrônico...")
        await page.evaluate("window.scrollTo({ top: 400, behavior: 'smooth' })")
        await page.wait_for_timeout(2000)
        await page.evaluate("window.scrollTo({ top: 0, behavior: 'smooth' })")
        await page.wait_for_timeout(1000)

        # Prontuário Eletrônico
        await page.goto(f"{BASE_URL}/prontuario", wait_until="networkidle")
        await page.wait_for_timeout(2500)
        await page.evaluate("window.scrollTo({ top: 500, behavior: 'smooth' })")
        await page.wait_for_timeout(2000)
        await page.evaluate("window.scrollTo({ top: 0, behavior: 'smooth' })")
        await page.wait_for_timeout(1000)

        # ---------------------------------------------------------------------
        # 4. VÍDEO CHAMADA WEBRTC EM TEMPO REAL (TÉCNICO INICIA CHAMADA)
        # ---------------------------------------------------------------------
        print(">>> [4/9] Central de Teleatendimento WebRTC: INICIANDO VÍDEO CHAMADA...")
        await page.goto(f"{BASE_URL}/atendimento", wait_until="networkidle")
        await page.wait_for_timeout(2500)

        start_call_btn = page.locator("button:has-text('Iniciar Chamada Imediata')")
        if await start_call_btn.is_visible():
            await start_call_btn.click()
            print("  -> Vídeo Chamada Iniciada! Exibindo Modal com Câmera e Transmissão...")
            await page.wait_for_timeout(4000)
            
            # Testa controles de áudio/vídeo na chamada
            mute_btn = page.locator("button:has-text('Mutar'), button:has-text('Desmutar')").first
            if await mute_btn.is_visible():
                await mute_btn.click()
                await page.wait_for_timeout(1500)
                await mute_btn.click()
                await page.wait_for_timeout(1500)
                
            # Simula alguns segundos de conversa ativa
            await page.wait_for_timeout(4000)
            
            # Encerra a chamada
            end_call_btn = page.locator("button:has-text('Encerrar Atendimento')")
            if await end_call_btn.is_visible():
                await end_call_btn.click()
                await page.wait_for_timeout(2000)
                print("  -> Chamada encerrada e nota de evolução técnica aberta!")
                
                salvar_nota_btn = page.locator("button:has-text('Salvar Evolução')")
                if await salvar_nota_btn.is_visible():
                    await salvar_nota_btn.click()
                    await page.wait_for_timeout(2000)

        # ---------------------------------------------------------------------
        # 5. DEMONSTRAÇÃO COMPLETA DOS TEMAS DE CONTRASTE (5+ TEMAS)
        # ---------------------------------------------------------------------
        print(">>> [5/9] Demonstração Completa dos Temas de Alto Contraste...")
        await page.goto(f"{BASE_URL}/catalogo-temas", wait_until="networkidle")
        await page.wait_for_timeout(2500)

        themes = [
            ("executive-slate", "Executive Slate"),
            ("midnight-navy", "Midnight Navy"),
            ("forest-emerald", "Forest Emerald"),
            ("deep-titanium", "Deep Titanium"),
            ("royal-indigo", "Royal Indigo"),
            ("obsidian-gold", "Obsidian Gold"),
        ]

        for theme_id, theme_name in themes:
            print(f"  -> Apresentando Tema de Alto Contraste: {theme_name}")
            await page.evaluate(f"""
                localStorage.setItem('conecta_theme_mode', 'dark');
                localStorage.setItem('conecta_active_theme', '{theme_id}');
                document.documentElement.setAttribute('data-theme', '{theme_id}');
                document.documentElement.classList.add('dark');
                window.dispatchEvent(new Event('themechange'));
            """)
            await page.wait_for_timeout(1800)
            await page.evaluate("window.scrollBy({ top: 200, behavior: 'smooth' })")
            await page.wait_for_timeout(1000)

        # Alto Contraste Puro (WCAG AAA)
        contrast_btn = page.locator("#contrastBtn")
        if await contrast_btn.is_visible():
            await contrast_btn.click()
            await page.wait_for_timeout(2000)
            await contrast_btn.click()
            await page.wait_for_timeout(1000)

        # Logout do Técnico
        print(">>> [6/9] Logout da Técnica Dra. Márcia...")
        logout_btn = page.locator("#headerLogoutBtn")
        if await logout_btn.is_visible():
            await logout_btn.click()
            await page.wait_for_timeout(2000)
        else:
            await page.goto(f"{BASE_URL}/login", wait_until="networkidle")

        # ---------------------------------------------------------------------
        # 6. VISÃO DA EMPRESA (EMPREGADOR / CADASTRO DE VAGAS)
        # ---------------------------------------------------------------------
        print(">>> [7/9] Login como EMPRESA PARCEIRA (Construtora Capixaba)...")
        await page.goto(f"{BASE_URL}/login", wait_until="networkidle")
        await page.wait_for_timeout(1500)
        await page.fill("#loginIdentifier", "rh@parceirosejus.com.br")
        await page.wait_for_timeout(400)
        await page.fill("#loginPassword", "secret123")
        await page.wait_for_timeout(400)
        await page.click("#loginSubmitBtn")
        
        # Empregador é redirecionado diretamente para inserção de vagas!
        await page.wait_for_url("**/oportunidades**", timeout=20000)
        await page.wait_for_timeout(3000)

        print(">>> Empresa: Painel de Inserção de Vagas Aberto! Preenchendo Oportunidade...")
        
        # Preenche o formulário manual de cadastro de vaga
        await page.evaluate("window.scrollTo({ top: 200, behavior: 'smooth' })")
        await page.wait_for_timeout(1500)

        # Exibe o formulário e opções de CSV
        await page.evaluate("window.scrollTo({ top: 600, behavior: 'smooth' })")
        await page.wait_for_timeout(2500)
        await page.evaluate("window.scrollTo({ top: 0, behavior: 'smooth' })")
        await page.wait_for_timeout(1500)

        # Logout da Empresa
        print(">>> Logout do Perfil Empresa...")
        if await logout_btn.is_visible():
            await logout_btn.click()
            await page.wait_for_timeout(2000)
        else:
            await page.goto(f"{BASE_URL}/login", wait_until="networkidle")

        # ---------------------------------------------------------------------
        # 7. VISÃO DO CIDADÃO EGRESSO (Lucas Santos)
        # ---------------------------------------------------------------------
        print(">>> [8/9] Login como CIDADÃO EGRESSO (Lucas Santos)...")
        await page.goto(f"{BASE_URL}/login", wait_until="networkidle")
        await page.wait_for_timeout(1500)
        await page.fill("#loginIdentifier", "lucas.santos@cidadao.es.gov.br")
        await page.wait_for_timeout(400)
        await page.fill("#loginPassword", "secret123")
        await page.wait_for_timeout(400)
        await page.click("#loginSubmitBtn")
        await page.wait_for_url("**/dashboard", timeout=20000)
        await page.wait_for_timeout(2500)

        # Dashboard Cidadão
        print(">>> Visão do Egresso: Dashboard Cidadão...")
        await page.evaluate("window.scrollTo({ top: 350, behavior: 'smooth' })")
        await page.wait_for_timeout(2000)
        await page.evaluate("window.scrollTo({ top: 0, behavior: 'smooth' })")
        await page.wait_for_timeout(1000)

        # Carteira Digital do Egresso (QR Code HMAC & Assinatura)
        print(">>> Visão do Egresso: Carteira Digital com QR Code HMAC...")
        await page.goto(f"{BASE_URL}/carteira", wait_until="networkidle")
        await page.wait_for_timeout(3000)
        await page.evaluate("window.scrollTo({ top: 400, behavior: 'smooth' })")
        await page.wait_for_timeout(2500)
        await page.evaluate("window.scrollTo({ top: 0, behavior: 'smooth' })")
        await page.wait_for_timeout(1000)

        # Oportunidades & Vagas para o Egresso
        print(">>> Visão do Egresso: Vagas de Trabalho & Cursos de Capacitação...")
        await page.goto(f"{BASE_URL}/oportunidades", wait_until="networkidle")
        await page.wait_for_timeout(3000)
        await page.evaluate("window.scrollTo({ top: 450, behavior: 'smooth' })")
        await page.wait_for_timeout(2000)

        # Teleatendimento (Egresso entrando na fila virtual)
        print(">>> Visão do Egresso: Sala de Teleatendimento para conversar com Técnico...")
        await page.goto(f"{BASE_URL}/atendimento", wait_until="networkidle")
        await page.wait_for_timeout(3000)

        # Geolocalização & Rede de Apoio
        print(">>> Visão do Egresso: Rede de Apoio (CRAS, CREAS, SINE)...")
        await page.goto(f"{BASE_URL}/geolocalizacao", wait_until="networkidle")
        await page.wait_for_timeout(3000)

        # ---------------------------------------------------------------------
        # 8. FINALIZAÇÃO E EXPORTAÇÃO DO VÍDEO
        # ---------------------------------------------------------------------
        print(">>> [9/9] Gravação finalizada! Consolidando arquivo de vídeo...")
        await page.wait_for_timeout(2000)
        
        video_path = await page.video.path()
        await context.close()
        await browser.close()
        
        final_video_path = os.path.join(FINAL_VIDEO_DIR, "conecta_egresso_demonstracao_completa.webm")
        if os.path.exists(video_path):
            shutil.copy(video_path, final_video_path)
            print(f"=== SUCESSO: VÍDEO COMPLETO SALVO EM: {final_video_path} ===")
            return final_video_path

if __name__ == "__main__":
    asyncio.run(record_demo())
