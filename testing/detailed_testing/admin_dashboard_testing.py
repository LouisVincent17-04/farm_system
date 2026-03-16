"""
GATZ SmartFarm — Sign In Flow Automation Tests
Framework : Selenium + Python
Run       : python test_signin.py
Requires  : pip install selenium webdriver-manager
"""

import time
import unittest
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

# ── CONFIG ────────────────────────────────────────────────────────────────────
BASE_URL      = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/login.php"  # ← update this
VALID_EMAIL   = "vinxvadezxz@gmail.com"
VALID_PASS    = "v1i1n1x1"
INVALID_EMAIL = "notanemail"
INVALID_PASS  = ""
TIMEOUT       = 10  # seconds


# ── HELPERS ───────────────────────────────────────────────────────────────────
def get_driver():
    options = webdriver.ChromeOptions()
    options.add_argument("--start-maximized")
    # options.add_argument("--headless")   # uncomment to run headless
    options.add_argument("--disable-notifications")
    driver = webdriver.Chrome(
        service=Service(ChromeDriverManager().install()),
        options=options
    )
    return driver


def bypass_ngrok_interstitial(driver, wait):
    """
    Ngrok shows a browser warning page with a 'Visit Site' button.
    This injects the bypass header via a CDP request so Selenium
    never sees the interstitial — no clicking required.
    """
    try:
        # Method 1: Add ngrok-skip-browser-warning header via CDP
        driver.execute_cdp_cmd("Network.enable", {})
        driver.execute_cdp_cmd("Network.setExtraHTTPHeaders", {
            "headers": {
                "ngrok-skip-browser-warning": "true",
                "User-Agent": "SeleniumTestBot/1.0"
            }
        })
    except Exception:
        pass

    try:
        # Method 2: Fallback — click the "Visit Site" button if it appears
        visit_btn = wait.until(
            EC.element_to_be_clickable(
                (By.XPATH, "//button[contains(text(),'Visit Site')] | //a[contains(text(),'Visit Site')]")
            )
        )
        visit_btn.click()
        time.sleep(1)
    except Exception:
        pass  # No interstitial shown, continue normally


# ── TEST SUITE ────────────────────────────────────────────────────────────────
class TestSignIn(unittest.TestCase):

    def setUp(self):
        self.driver = get_driver()
        self.wait   = WebDriverWait(self.driver, TIMEOUT)
        bypass_ngrok_interstitial(self.driver, WebDriverWait(self.driver, 5))
        self.driver.get(BASE_URL)
        time.sleep(1)  # allow page to settle after bypass

    def tearDown(self):
        self.driver.quit()

    # ── HELPERS ───────────────────────────────────────────────────────────────
    def _email_field(self):
        return self.wait.until(EC.presence_of_element_located((By.ID, "signinEmail")))

    def _pass_field(self):
        return self.driver.find_element(By.ID, "signinPassword")

    def _submit(self):
        self.driver.find_element(By.ID, "signinBtn").click()

    def _toast_visible(self, form="signin"):
        toast = self.wait.until(
            EC.presence_of_element_located((By.ID, f"{form}Toast"))
        )
        self.wait.until(lambda d: "is-visible" in toast.get_attribute("class"))
        return toast

    def _field_invalid(self, field_id):
        el = self.driver.find_element(By.ID, field_id)
        return "is-invalid" in el.get_attribute("class")

    # ── TC-01: Page load ──────────────────────────────────────────────────────
    def test_01_page_loads(self):
        """Page loads and Sign In tab is active by default."""
        panel = self.wait.until(
            EC.presence_of_element_located((By.ID, "signinForm"))
        )
        self.assertIn("is-active", panel.get_attribute("class"),
                      "Sign In panel should be active on load")
        print("✅ TC-01 PASS — Page loaded, Sign In tab active")

    # ── TC-02: Empty form submission ──────────────────────────────────────────
    def test_02_empty_form_shows_errors(self):
        """Submitting an empty form shows inline validation errors."""
        self._submit()
        time.sleep(0.4)
        self.assertTrue(self._field_invalid("signinEmail"),
                        "Email field should be marked invalid")
        self.assertTrue(self._field_invalid("signinPassword"),
                        "Password field should be marked invalid")
        print("✅ TC-02 PASS — Empty form shows validation errors")

    # ── TC-03: Invalid email format ───────────────────────────────────────────
    def test_03_invalid_email_format(self):
        """A malformed email triggers an inline error."""
        self._email_field().send_keys(INVALID_EMAIL)
        self._pass_field().send_keys("somepassword")
        self._submit()
        time.sleep(0.4)
        self.assertTrue(self._field_invalid("signinEmail"),
                        "Email field should be invalid for bad format")
        print("✅ TC-03 PASS — Invalid email format caught")

    # ── TC-04: Password toggle ────────────────────────────────────────────────
    def test_04_password_toggle(self):
        """Eye icon toggles password visibility."""
        pw = self._pass_field()
        pw.send_keys("TestPassword")
        self.assertEqual(pw.get_attribute("type"), "password",
                         "Field should start as password type")
        eye = self.driver.find_element(
            By.CSS_SELECTOR, "#signinForm .lp-field__eye"
        )
        eye.click()
        time.sleep(0.2)
        self.assertEqual(pw.get_attribute("type"), "text",
                         "Field should switch to text after toggle")
        eye.click()
        time.sleep(0.2)
        self.assertEqual(pw.get_attribute("type"), "password",
                         "Field should revert to password after second toggle")
        print("✅ TC-04 PASS — Password toggle works correctly")

    # ── TC-05: Tab switch to Sign Up ──────────────────────────────────────────
    def test_05_switch_to_signup_tab(self):
        """Clicking Sign Up tab shows the registration panel."""
        signup_tab = self.wait.until(
            EC.element_to_be_clickable(
                (By.XPATH, "//button[normalize-space()='Sign Up']")
            )
        )
        signup_tab.click()
        time.sleep(0.4)
        signup_panel = self.driver.find_element(By.ID, "signupForm")
        self.assertIn("is-active", signup_panel.get_attribute("class"),
                      "Sign Up panel should become active")
        print("✅ TC-05 PASS — Tab switches to Sign Up")

    # ── TC-06: Invalid credentials ────────────────────────────────────────────
    def test_06_invalid_credentials_show_toast(self):
        """Wrong credentials display an error toast."""
        self._email_field().send_keys("wrong@example.com")
        self._pass_field().send_keys("wrongpassword")
        self._submit()
        toast = self._toast_visible()
        self.assertIn("lp-toast--error", toast.get_attribute("class"),
                      "Error toast should appear for bad credentials")
        print("✅ TC-06 PASS — Error toast shown for invalid credentials")

    # ── TC-07: Valid credentials ──────────────────────────────────────────────
    def test_07_valid_credentials_redirect(self):
        """Correct credentials log in and redirect to dashboard."""
        self._email_field().send_keys(VALID_EMAIL)
        self._pass_field().send_keys(VALID_PASS)
        self._submit()
        # Expect either a success toast or a URL change
        try:
            toast = self._toast_visible()
            self.assertIn("lp-toast--success", toast.get_attribute("class"),
                          "Success toast should appear")
            # Wait for redirect
            self.wait.until(EC.url_contains("admin_dashboard"))
        except Exception:
            # Might have redirected directly without toast
            self.wait.until(EC.url_contains("admin_dashboard"),
                            "Should redirect to dashboard after login")
        print("✅ TC-07 PASS — Valid credentials redirect to dashboard")

    # ── TC-08: Field error clears on input ────────────────────────────────────
    def test_08_error_clears_on_retype(self):
        """Inline error disappears once the user starts retyping."""
        self._submit()          # trigger errors
        time.sleep(0.3)
        em = self._email_field()
        self.assertTrue(self._field_invalid("signinEmail"))
        em.send_keys("a")       # start typing
        time.sleep(0.2)
        self.assertFalse(self._field_invalid("signinEmail"),
                         "Invalid class should clear after user types")
        print("✅ TC-08 PASS — Inline error clears on retype")

    # ── TC-09: Remember me checkbox ───────────────────────────────────────────
    def test_09_remember_me_checkbox(self):
        """Remember Me checkbox can be checked and unchecked."""
        cb = self.driver.find_element(By.ID, "remember")
        self.assertFalse(cb.is_selected(), "Checkbox should start unchecked")
        cb.click()
        self.assertTrue(cb.is_selected(), "Checkbox should be checked after click")
        cb.click()
        self.assertFalse(cb.is_selected(), "Checkbox should uncheck on second click")
        print("✅ TC-09 PASS — Remember Me checkbox works")

    # ── TC-10: Forgot password link ───────────────────────────────────────────
    def test_10_forgot_password_link(self):
        """Forgot password link navigates away from the login page."""
        link = self.wait.until(
            EC.element_to_be_clickable((By.CSS_SELECTOR, "a.lp-forgot"))
        )
        href = link.get_attribute("href")
        self.assertIn("forgot_password", href,
                      "Link should point to forgot_password page")
        print("✅ TC-10 PASS — Forgot password link is correct")


# ── ENTRY POINT ───────────────────────────────────────────────────────────────
if __name__ == "__main__":
    loader  = unittest.TestLoader()
    suite   = loader.loadTestsFromTestCase(TestSignIn)
    runner  = unittest.TextTestRunner(verbosity=2)
    result  = runner.run(suite)

    print("\n" + "=" * 60)
    print(f"Tests run   : {result.testsRun}")
    print(f"Failures    : {len(result.failures)}")
    print(f"Errors      : {len(result.errors)}")
    print(f"Skipped     : {len(result.skipped)}")
    status = "✅ ALL PASSED" if result.wasSuccessful() else "❌ SOME TESTS FAILED"
    print(f"Result      : {status}")
    print("=" * 60)