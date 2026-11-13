import asyncio
import os
from playwright.async_api import async_playwright

BASE_URL = "http://127.0.0.1:8000"
SHOTS_DIR = os.path.abspath("videos/screenshots")

async def take_screenshots():
    os.makedirs(SHOTS_DIR, exist_ok=True)
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1920, "height": 1080})
        page = await context.new_page()

        # 1. Login como Suporte/Gestor para acessar gerenciamento e cadastro
        print(">>> Acessando sistema como Suporte/Gestor...")
        await page.goto(f"{BASE_URL}/login", wait_until="networkidle")
        await page.fill("#loginIdentifier", "suporte.agile@sejus.es.gov.br")
        await page.fill("#loginPassword", "secret123")
        await page.click("#loginSubmitBtn")
        await page.wait_for_url("**/dashboard", timeout=15000)

        # 2. Ir para /usuarios e abrir o modal de cadastro com perfil Egresso selecionado
        print(">>> Acessando Gestão de Usuários e abrindo modal de cadastro...")
        await page.goto(f"{BASE_URL}/usuarios", wait_until="networkidle")
        await page.wait_for_timeout(2000)
        
        # Clica em Novo Usuário
        await page.click("button:has-text('Novo Usuário')")
        await page.wait_for_timeout(1000)

        # Preenche os dados simulando o cadastro de um novo egresso
        await page.fill("#formUserName", "Marcos Vinícius de Alencar")
        await page.fill("#formUserEmail", "marcos.alencar@cidadao.es.gov.br")
        await page.fill("#formUserCpf", "123.456.789-00")
        await page.fill("#formUserTelefone", "(27) 99876-5432")
        await page.select_option("#formUserPerfil", label="Egresso (egresso)")
        
        # Tira print do Modal de Cadastro do Egresso
        modal_shot = os.path.join(SHOTS_DIR, "01_cadastro_novo_egresso_modal.png")
        await page.screenshot(path=modal_shot)
        print(f"Salvo: {modal_shot}")

        # Fecha modal e vai para o Prontuário do Egresso (Ficha cadastral completa)
        await page.click("button:has-text('Cancelar')")
        await page.wait_for_timeout(1000)

        # 3. Prontuário Eletrônico / Ficha Cadastral do Egresso
        print(">>> Acessando Ficha e Prontuário do Egresso...")
        await page.goto(f"{BASE_URL}/prontuario", wait_until="networkidle")
        await page.wait_for_timeout(2500)
        
        prontuario_shot = os.path.join(SHOTS_DIR, "02_ficha_cadastral_egresso_prontuario.png")
        await page.screenshot(path=prontuario_shot)
        print(f"Salvo: {prontuario_shot}")

        # 4. Carteira Digital Oficial do Egresso
        print(">>> Acessando Carteira Digital do Egresso...")
        await page.goto(f"{BASE_URL}/carteira", wait_until="networkidle")
        await page.wait_for_timeout(2500)
        
        carteira_shot = os.path.join(SHOTS_DIR, "03_carteira_digital_egresso.png")
        await page.screenshot(path=carteira_shot)
        print(f"Salvo: {carteira_shot}")

        await browser.close()
        print("Screenshots capturadas com sucesso!")

if __name__ == "__main__":
    asyncio.run(take_screenshots())
