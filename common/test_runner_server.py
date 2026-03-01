"""
test_runner_server.py — Local Flask server that triggers Selenium tests
Run this on YOUR LOCAL MACHINE:
    pip install flask flask-cors selenium webdriver-manager requests
    python test_runner_server.py

NOTE: Your device and the web server must be on the same WiFi network.
"""

import subprocess
import threading
import socket
import requests
import sys

if sys.platform == "win32":
    sys.stdout.reconfigure(encoding="utf-8")

from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

# ── CONFIG ────────────────────────────────────────────────────────────────────
FARM_SERVER  = "https://fallalishly-unposed-iliana.ngrok-free.dev"
REGISTER_URL = f"{FARM_SERVER}/FarmSystem/common/register_runner.php"
PORT         = 5001
# ─────────────────────────────────────────────────────────────────────────────

ALLOWED_TESTS = {
    'positive_test_transactions': '../testing/positive_testing/transactions_testing.py',
    # 'positive_test_employees': '../testing/positive_testing/employees_testing.py',
}

running_tests = {}
DEVICE_NAME = socket.gethostname()


def get_local_ip():
    """Get this machine's local network IP (e.g. 192.168.x.x)."""
    try:
        s = socket.socket(socket.AF_INET, socket.SOCK_DGRAM)
        s.connect(("8.8.8.8", 80))
        ip = s.getsockname()[0]
        s.close()
        return ip
    except Exception:
        return "127.0.0.1"


def register_with_server(local_ip):
    """Tell the PHP server: device name + local IP:port"""
    runner_url = f"http://{local_ip}:{PORT}"
    try:
        res = requests.post(
            REGISTER_URL,
            json={'device': DEVICE_NAME, 'url': runner_url},
            timeout=5,
            headers={"ngrok-skip-browser-warning": "true"}
        )
        data = res.json()
        if data.get('success'):
            print(f"✅ Registered as '{DEVICE_NAME}' → {runner_url}")
        else:
            print(f"⚠️  Registration failed: {data.get('message')}")
    except Exception as e:
        print(f"❌ Could not register with server: {e}")


def run_test(test_key, script_path):
    running_tests[test_key] = 'running'
    try:
        result = subprocess.run(
            ['python', script_path],
            capture_output=True, text=True, timeout=120,
            encoding='utf-8', errors='replace'
        )
        running_tests[test_key] = 'done'
        print(f"\n✅ Test finished: {test_key}")
        print(result.stdout)
        if result.stderr:
            print("STDERR:", result.stderr)
    except subprocess.TimeoutExpired:
        running_tests[test_key] = 'timeout'
        print(f"⏱️ Test timed out: {test_key}")
    except Exception as e:
        running_tests[test_key] = 'error'
        print(f"❌ Test error: {e}")


@app.route('/run-test', methods=['POST'])
def run_test_endpoint():
    body     = request.get_json(silent=True) or {}
    test_key = body.get('test', '').strip()

    if test_key not in ALLOWED_TESTS:
        return jsonify({'success': False, 'message': f'Unknown test: {test_key}'}), 400

    if running_tests.get(test_key) == 'running':
        return jsonify({'success': False, 'message': '⚠️ This test is already running!'}), 409

    script_path = ALLOWED_TESTS[test_key]
    thread = threading.Thread(target=run_test, args=(test_key, script_path), daemon=True)
    thread.start()

    return jsonify({
        'success': True,
        'message': f'✅ Test launched on <strong>{DEVICE_NAME}</strong>! Watch the browser window.',
        'script':  script_path
    })


@app.route('/test-status', methods=['GET'])
def test_status():
    return jsonify(running_tests)


@app.route('/', methods=['GET'])
def health():
    return jsonify({'status': 'ok', 'device': DEVICE_NAME})


if __name__ == '__main__':
    local_ip = get_local_ip()
    print(f"🖥️  Device   : {DEVICE_NAME}")
    print(f"🌐 Local IP : {local_ip}")
    print(f"🔗 Runner   : http://{local_ip}:{PORT}")

    register_with_server(local_ip)

    print(f"\n🚀 Test runner ready — keep this running while using the chatbot.\n")
    app.run(host='0.0.0.0', port=PORT, debug=False)