import asyncio
from playwright.async_api import async_playwright

async def verify_ui():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context(viewport={"width": 1920, "height": 1080})
        page = await context.new_page()

        # 1. Login e verificar banner de cookies e modal de termos
        await page.goto("http://127.0.0.1:8000/login")
        await page.wait_for_timeout(1000)
        cookie_banner = await page.locator("#cookieConsentBanner").is_visible()
        print("Cookie banner visivel no login:", cookie_banner)

        # Clicar em Termos e LGPD no cookie banner
        await page.click("button:has-text('Termos')")
        await page.wait_for_timeout(1000)
        modal_visible = await page.locator("#privacyTermsModal").is_visible()
        print("Modal de Termos e LGPD aberto:", modal_visible)
        await page.screenshot(path="videos/screenshots/04_modal_lgpd_termos.png")

        # Fechar modal e aceitar cookies
        await page.click("button:has-text('Entendido e Fechar')")
        await page.wait_for_timeout(500)
        await page.click("button:has-text('Aceitar Todos')")
        await page.wait_for_timeout(500)

        # 2. Login como Egresso Lucas Santos
        await page.fill("#loginIdentifier", "lucas.santos@cidadao.es.gov.br")
        await page.fill("#loginPassword", "secret123")
        await page.click("#loginSubmitBtn")
        await page.wait_for_url("**/dashboard")

        # 3. Acessar Oportunidades e verificar aba Minhas Inscricoes
        await page.goto("http://127.0.0.1:8000/oportunidades")
        await page.wait_for_timeout(2000)

        minhas_tab = page.locator("#tab-minhas-inscricoes")
        await minhas_tab.click()
        await page.wait_for_timeout(1500)
        await page.screenshot(path="videos/screenshots/05_egresso_minhas_inscricoes.png")
        print("Aba Minhas Inscricoes aberta e capturada!")

        # 4. Login como Empresa para verificar layout CSV simplificado
        await page.goto("http://127.0.0.1:8000/login")
        await page.fill("#loginIdentifier", "rh@parceirosejus.com.br")
        await page.fill("#loginPassword", "secret123")
        await page.click("#loginSubmitBtn")
        await page.wait_for_url("**/oportunidades**")
        await page.wait_for_timeout(2000)
        await page.screenshot(path="videos/screenshots/06_empresa_csv_multicidade.png")
        print("Painel da Empresa com CSV multi-cidade capturado!")

        await browser.close()

if __name__ == "__main__":
    asyncio.run(verify_ui())
