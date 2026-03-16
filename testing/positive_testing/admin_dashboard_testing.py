"""
GATZ SmartFarm — Quick Positive Test: Login → Employees CRUD
Run: python test_signin.py
Requires: pip install selenium webdriver-manager
"""

import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

BASE_URL      = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/login.php"  # ← update this
VALID_EMAIL   = "vinxvadezxz@gmail.com"
VALID_PASS    = "v1i1n1x1"

# ── Test Employee Data ────────────────────────────────────────────────────────
EMP_CODE      = "9999"
EMP_NAME      = "Test Employee Automation"
EMP_CONTACT   = "09123456789"
EMP_HIRE_DATE = "02012026"
EMP_NAME_EDIT = "Test Employee Edited"

# ── Driver Setup ──────────────────────────────────────────────────────────────
options = webdriver.ChromeOptions()
options.add_argument("--start-maximized")
driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
wait   = WebDriverWait(driver, 10)

# ── Bypass ngrok interstitial ─────────────────────────────────────────────────
driver.execute_cdp_cmd("Network.enable", {})
driver.execute_cdp_cmd("Network.setExtraHTTPHeaders", {
    "headers": { "ngrok-skip-browser-warning": "true" }
})

# ═════════════════════════════════════════════════════════════════════════════
# STEP 1: Login
# ═════════════════════════════════════════════════════════════════════════════
print("\n🔐 Logging in...")
driver.get(BASE_URL)
time.sleep(1)

wait.until(EC.presence_of_element_located((By.ID, "signinEmail"))).send_keys(VALID_EMAIL)
driver.find_element(By.ID, "signinPassword").send_keys(VALID_PASS)
driver.find_element(By.ID, "signinBtn").click()

wait.until(EC.url_contains("admin_dashboard"))
print("✅ Login successful — on dashboard")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 2: Navigate to Employees
# ═════════════════════════════════════════════════════════════════════════════
print("\n📋 Navigating to Employees page...")
emp_url = BASE_URL.replace("login.php", "employees.php")
driver.get(emp_url)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, "btn-add")))
print("✅ Employees page loaded")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 3: Add Employee
# ═════════════════════════════════════════════════════════════════════════════
print("\n➕ Adding new employee...")
driver.find_element(By.CLASS_NAME, "btn-add").click()
wait.until(EC.visibility_of_element_located((By.ID, "empModal")))
time.sleep(0.5)

driver.find_element(By.ID, "employee_code").send_keys(EMP_CODE)
driver.find_element(By.ID, "full_name").send_keys(EMP_NAME)

# Pick first available role from dropdown
role_select = Select(driver.find_element(By.ID, "position"))
if len(role_select.options) > 1:
    role_select.select_by_index(1)

driver.find_element(By.ID, "contact_no").send_keys(EMP_CONTACT)
driver.find_element(By.ID, "hire_date").send_keys(EMP_HIRE_DATE)
driver.find_element(By.CSS_SELECTOR, ".btn-save").click()

# Handle alert
try:
    wait.until(EC.alert_is_present())
    alert_text = driver.switch_to.alert.text
    driver.switch_to.alert.accept()
    print(f"✅ Add successful — Alert: {alert_text}")
except:
    print("⚠️  No alert after add — check manually")

wait.until(EC.presence_of_element_located((By.CLASS_NAME, "btn-add")))
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 4: Edit the newly added employee
# ═════════════════════════════════════════════════════════════════════════════
print("\n✏️  Editing the newly added employee...")

# Find the row with our test employee
rows = driver.find_elements(By.CSS_SELECTOR, "tbody tr")
edit_btn = None
for row in rows:
    if EMP_NAME in row.text:
        edit_btn = row.find_element(By.CLASS_NAME, "btn-edit")
        break

if edit_btn:
    edit_btn.click()
    wait.until(EC.visibility_of_element_located((By.ID, "empModal")))
    time.sleep(0.5)

    # Clear name and type new one
    name_field = driver.find_element(By.ID, "full_name")
    name_field.clear()
    name_field.send_keys(EMP_NAME_EDIT)

    driver.find_element(By.CSS_SELECTOR, ".btn-save").click()

    try:
        wait.until(EC.alert_is_present())
        alert_text = driver.switch_to.alert.text
        driver.switch_to.alert.accept()
        print(f"✅ Edit successful — Alert: {alert_text}")
    except:
        print("⚠️  No alert after edit — check manually")

    wait.until(EC.presence_of_element_located((By.CLASS_NAME, "btn-add")))
    time.sleep(1)
else:
    print("❌ Could not find the added employee row to edit")

# ═════════════════════════════════════════════════════════════════════════════
# STEP 5: Delete the employee
# ═════════════════════════════════════════════════════════════════════════════
print("\n🗑️  Deleting the employee...")

rows = driver.find_elements(By.CSS_SELECTOR, "tbody tr")
delete_btn = None
for row in rows:
    if EMP_NAME_EDIT in row.text:
        delete_btn = row.find_element(By.CLASS_NAME, "btn-delete")
        break

if delete_btn:
    delete_btn.click()

    # Confirm deletion dialog
    try:
        wait.until(EC.alert_is_present())
        driver.switch_to.alert.accept()  # confirm
    except:
        pass

    # Accept success alert
    try:
        wait.until(EC.alert_is_present())
        alert_text = driver.switch_to.alert.text
        driver.switch_to.alert.accept()
        print(f"✅ Delete successful — Alert: {alert_text}")
    except:
        print("⚠️  No alert after delete — check manually")
else:
    print("❌ Could not find the edited employee row to delete")

time.sleep(2)
print("\n🎉 All steps completed!")
driver.quit()