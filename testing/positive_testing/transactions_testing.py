"""
GATZ SmartFarm — Login → Transactions → Animal Purchases (Add) → Transactions → Medicine Purchases (Add)
Run: python test_signin.py
Requires: pip install selenium webdriver-manager
"""

import time
import sys
import os

# Fix Windows emoji encoding
if sys.platform == "win32":
    sys.stdout.reconfigure(encoding="utf-8")
    os.environ["PYTHONIOENCODING"] = "utf-8"
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait, Select
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.service import Service
from webdriver_manager.chrome import ChromeDriverManager

BASE_URL        = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/login.php"
TRANSACTIONS    = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/transactions.php"
PURCH_ANIMALS   = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/purch_animals.php"
PURCH_MEDICINE  = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/purch_medicines.php"
PURCH_VITAMINS  = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/purch_vitamins_supplements.php"
PURCH_VACCINES  = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/purch_vaccines.php"
PURCH_FEEDS     = "https://fallalishly-unposed-iliana.ngrok-free.dev/FarmSystem/views/purch_feeds_feedingSupplies.php"

VALID_EMAIL    = "vinxvadezxz@gmail.com"
VALID_PASS     = "v1i1n1x1"

# ── Driver Setup ──────────────────────────────────────────────────────────────
options = webdriver.ChromeOptions()
options.add_argument("--start-maximized")
driver = webdriver.Chrome(service=Service(ChromeDriverManager().install()), options=options)
wait   = WebDriverWait(driver, 10)

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
print("✅ Login successful")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 2: Transactions → Animal Purchases → Add
# ═════════════════════════════════════════════════════════════════════════════
print("\n🧭 Going to Transactions...")
driver.get(TRANSACTIONS)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, "management-grid")))
print("✅ Transactions page loaded")
time.sleep(1)

print("\n🐖 Going to Animal Purchases...")
driver.get(PURCH_ANIMALS)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "button.add-btn")))
print("✅ Animal Purchases page loaded")
time.sleep(1)

print("\n➕ Adding animal purchase...")
add_btn = wait.until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button.add-btn")))
driver.execute_script("arguments[0].scrollIntoView(true);", add_btn)
time.sleep(0.5)
driver.execute_script("arguments[0].click();", add_btn)
wait.until(EC.visibility_of_element_located((By.ID, "modal")))
time.sleep(0.5)

driver.find_element(By.ID, "item-name").send_keys("Hog")
time.sleep(1)
driver.find_element(By.ID, "unit-cost").click()
driver.find_element(By.ID, "unit-cost").send_keys("5000")
driver.find_element(By.ID, "item-weight").send_keys("80")
driver.execute_script("document.getElementById('purchase-date').value = '2025-06-01';")

driver.find_element(By.ID, "btn-save").click()

try:
    wait.until(EC.alert_is_present())
    print(f"✅ Animal Add — Alert: {driver.switch_to.alert.text}")
    driver.switch_to.alert.accept()
except:
    try:
        msg = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, ".alert.success")))
        print(f"✅ Animal Add — {msg.text}")
    except:
        print("⚠️  No alert after animal add")

time.sleep(2)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 3: Back to Transactions
# ═════════════════════════════════════════════════════════════════════════════
print("\n🔙 Going back to Transactions...")
driver.get(TRANSACTIONS)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, "management-grid")))
print("✅ Transactions page loaded")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 4: Medicine Purchases → Add
# ═════════════════════════════════════════════════════════════════════════════
print("\n💊 Going to Medicine Purchases...")
driver.get(PURCH_MEDICINE)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "button.add-btn")))
print("✅ Medicine Purchases page loaded")
time.sleep(1)

print("\n➕ Adding medicine purchase...")
add_btn = wait.until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button.add-btn")))
driver.execute_script("arguments[0].scrollIntoView(true);", add_btn)
time.sleep(0.5)
driver.execute_script("arguments[0].click();", add_btn)
wait.until(EC.visibility_of_element_located((By.ID, "modal")))
time.sleep(0.5)

# Item name
driver.find_element(By.ID, "item-name").send_keys("Paracetamol")
time.sleep(1)

# Click elsewhere to dismiss autocomplete
driver.find_element(By.ID, "net-weight").click()
driver.find_element(By.ID, "net-weight").send_keys("500")  # net weight in mg/g

# Unit of measurement — pick first available
unit_select = Select(driver.find_element(By.ID, "unit"))
if len(unit_select.options) > 1:
    unit_select.select_by_index(1)

# Quantity
driver.find_element(By.ID, "item-quantity").send_keys("10")

# Unit cost
driver.find_element(By.ID, "unit-cost").send_keys("150")

# Category — Consumable
cat_select = Select(driver.find_element(By.ID, "item-category"))
cat_select.select_by_value("1")

# Dates via JS
driver.execute_script("document.getElementById('purchase-date').value = '2025-06-01';")
driver.execute_script("document.getElementById('expiration-date').value = '2027-06-01';")

driver.find_element(By.ID, "btn-save").click()

try:
    wait.until(EC.alert_is_present())
    print(f"✅ Medicine Add — Alert: {driver.switch_to.alert.text}")
    driver.switch_to.alert.accept()
except:
    try:
        msg = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, ".alert.success")))
        print(f"✅ Medicine Add — {msg.text}")
    except:
        print("⚠️  No alert after medicine add")

time.sleep(2)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 5: Back to Transactions
# ═════════════════════════════════════════════════════════════════════════════
print("\n🔙 Going back to Transactions...")
driver.get(TRANSACTIONS)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, "management-grid")))
print("✅ Transactions page loaded")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 6: Vitamins & Supplements Purchase → Add
# ═════════════════════════════════════════════════════════════════════════════
print("\n🧴 Going to Vitamins & Supplements Purchase...")
driver.get(PURCH_VITAMINS)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "button.add-btn")))
print("✅ Vitamins & Supplements page loaded")
time.sleep(1)

print("\n➕ Adding vitamin purchase...")
add_btn = wait.until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button.add-btn")))
driver.execute_script("arguments[0].scrollIntoView(true);", add_btn)
time.sleep(0.5)
driver.execute_script("arguments[0].click();", add_btn)
wait.until(EC.visibility_of_element_located((By.ID, "modal")))
time.sleep(0.5)

# Item name
driver.find_element(By.ID, "item-name").send_keys("Multivitamins")
time.sleep(1)

# Click net-weight to dismiss autocomplete
driver.find_element(By.ID, "net-weight").click()
driver.find_element(By.ID, "net-weight").send_keys("250")

# Unit — pick first available
unit_select = Select(driver.find_element(By.ID, "unit"))
if len(unit_select.options) > 1:
    unit_select.select_by_index(1)

# Quantity
driver.find_element(By.ID, "item-quantity").send_keys("50")

# Unit cost
driver.find_element(By.ID, "unit-cost").send_keys("200")

# Category — Consumable
cat_select = Select(driver.find_element(By.ID, "item-category"))
cat_select.select_by_value("1")

# Dates via JS
driver.execute_script("document.getElementById('purchase-date').value = '2025-06-01';")
driver.execute_script("document.getElementById('expiration-date').value = '2027-06-01';")

driver.find_element(By.ID, "btn-save").click()

try:
    wait.until(EC.alert_is_present())
    print(f"✅ Vitamins Add — Alert: {driver.switch_to.alert.text}")
    driver.switch_to.alert.accept()
except:
    try:
        msg = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, ".alert.success")))
        print(f"✅ Vitamins Add — {msg.text}")
    except:
        print("⚠️  No alert after vitamins add")

time.sleep(2)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 7: Back to Transactions
# ═════════════════════════════════════════════════════════════════════════════
print("\n🔙 Going back to Transactions...")
driver.get(TRANSACTIONS)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, "management-grid")))
print("✅ Transactions page loaded")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 8: Vaccine Purchase → Add
# ═════════════════════════════════════════════════════════════════════════════
print("\n💉 Going to Vaccine Purchase...")
driver.get(PURCH_VACCINES)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "button.add-btn")))
print("✅ Vaccine Purchase page loaded")
time.sleep(1)

print("\n➕ Adding vaccine purchase...")
add_btn = wait.until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button.add-btn")))
driver.execute_script("arguments[0].scrollIntoView(true);", add_btn)
time.sleep(0.5)
driver.execute_script("arguments[0].click();", add_btn)
wait.until(EC.visibility_of_element_located((By.ID, "modal")))
time.sleep(0.5)

# Item name
driver.find_element(By.ID, "item-name").send_keys("FMD Vaccine")
time.sleep(1)

# Click net-weight to dismiss autocomplete
driver.find_element(By.ID, "net-weight").click()
driver.find_element(By.ID, "net-weight").send_keys("10")

# Unit — pick first available
unit_select = Select(driver.find_element(By.ID, "unit"))
if len(unit_select.options) > 1:
    unit_select.select_by_index(1)

# Quantity
driver.find_element(By.ID, "item-quantity").send_keys("100")

# Unit cost
driver.find_element(By.ID, "unit-cost").send_keys("350")

# Category — Consumable
cat_select = Select(driver.find_element(By.ID, "item-category"))
cat_select.select_by_value("1")

# Dates via JS
driver.execute_script("document.getElementById('purchase-date').value = '2025-06-01';")
driver.execute_script("document.getElementById('expiration-date').value = '2027-06-01';")

driver.find_element(By.ID, "btn-save").click()

try:
    wait.until(EC.alert_is_present())
    print(f"✅ Vaccine Add — Alert: {driver.switch_to.alert.text}")
    driver.switch_to.alert.accept()
except:
    try:
        msg = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, ".alert.success")))
        print(f"✅ Vaccine Add — {msg.text}")
    except:
        print("⚠️  No alert after vaccine add")

time.sleep(2)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 9: Back to Transactions
# ═════════════════════════════════════════════════════════════════════════════
print("\n🔙 Going back to Transactions...")
driver.get(TRANSACTIONS)
wait.until(EC.presence_of_element_located((By.CLASS_NAME, "management-grid")))
print("✅ Transactions page loaded")
time.sleep(1)

# ═════════════════════════════════════════════════════════════════════════════
# STEP 10: Feed Purchase → Add
# ═════════════════════════════════════════════════════════════════════════════
print("\n🌾 Going to Feed Purchase...")
driver.get(PURCH_FEEDS)
wait.until(EC.presence_of_element_located((By.CSS_SELECTOR, "button.add-btn")))
print("✅ Feed Purchase page loaded")
time.sleep(1)

print("\n➕ Adding feed purchase...")
add_btn = wait.until(EC.element_to_be_clickable((By.CSS_SELECTOR, "button.add-btn")))
driver.execute_script("arguments[0].scrollIntoView(true);", add_btn)
time.sleep(0.5)
driver.execute_script("arguments[0].click();", add_btn)
wait.until(EC.visibility_of_element_located((By.ID, "modal")))
time.sleep(0.5)

# Item name
driver.find_element(By.ID, "item-name").send_keys("Hog Starter Test")
time.sleep(1)

# Click net-weight to dismiss autocomplete
driver.find_element(By.ID, "net-weight").click()
driver.find_element(By.ID, "net-weight").send_keys("50")

# Unit — pick first available
unit_select = Select(driver.find_element(By.ID, "unit"))
if len(unit_select.options) > 1:
    unit_select.select_by_index(1)

# Quantity
driver.find_element(By.ID, "item-quantity").send_keys("20")

# Unit cost
driver.find_element(By.ID, "unit-cost").send_keys("1200")

# Category — Consumable
cat_select = Select(driver.find_element(By.ID, "item-category"))
cat_select.select_by_value("1")

# Dates via JS
driver.execute_script("document.getElementById('purchase-date').value = '2025-06-01';")
driver.execute_script("document.getElementById('expiration-date').value = '2027-06-01';")

driver.find_element(By.ID, "btn-save").click()

try:
    wait.until(EC.alert_is_present())
    print(f"✅ Feed Add — Alert: {driver.switch_to.alert.text}")
    driver.switch_to.alert.accept()
except:
    try:
        msg = wait.until(EC.visibility_of_element_located((By.CSS_SELECTOR, ".alert.success")))
        print(f"✅ Feed Add — {msg.text}")
    except:
        print("⚠️  No alert after feed add")

time.sleep(2)
print("\n🎉 All done!")
driver.quit()