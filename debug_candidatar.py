import asyncio
from playwright.async_api import async_playwright

async def test_apply():
    async with async_playwright() as p:
        browser = await p.chromium.launch(headless=True)
        context = await browser.new_context()
        page = await context.new_page()

        await page.goto("http://127.0.0.1:8000/login")
        await page.fill("#loginIdentifier", "lucas.santos@cidadao.es.gov.br")
        await page.fill("#loginPassword", "secret123")
        await page.click("#loginSubmitBtn")
        await page.wait_for_url("**/dashboard")

        await page.goto("http://127.0.0.1:8000/oportunidades")
        await page.wait_for_timeout(2000)

        page.on("response", lambda res: print("RESPONSE:", res.status, res.url) if "api" in res.url else None)

        btn = page.locator("button:has-text('Candidatar-se')").first
        await btn.click()
        await page.wait_for_timeout(1000)

        confirm = page.locator("button:has-text('Confirmar Encaminhamento')")
        async with page.expect_response(lambda r: "candidatar" in r.url or "inscrever" in r.url) as response_info:
            await confirm.click()
            response = await response_info.value
            print("Status:", response.status)
            print("Response JSON:", await response.text())

        await browser.close()

if __name__ == "__main__":
    asyncio.run(test_apply())
