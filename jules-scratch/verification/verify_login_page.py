from playwright.sync_api import sync_playwright, expect

def run(playwright):
    browser = playwright.chromium.launch(headless=True)
    context = browser.new_context()
    page = context.new_page()

    try:
        # Navigate to the admin login page
        page.goto("http://localhost:8000/")

        # Check that the main heading is visible
        expect(page.get_by_role("heading", name="Acceso Corporativo")).to_be_visible()

        # Take a screenshot
        page.screenshot(path="jules-scratch/verification/login_page.png")

        print("Verification script ran successfully.")

    except Exception as e:
        print(f"An error occurred: {e}")
        page.screenshot(path="jules-scratch/verification/error.png")

    finally:
        browser.close()

with sync_playwright() as playwright:
    run(playwright)