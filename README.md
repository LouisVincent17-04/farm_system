# 🤖 AI Chatbot Setup Guide
## TF-IDF + SVM Model | Flask API | PHP Integration

---

## 📁 File Structure

```
your-project/
│
├── chatbot/                  ← Python AI files (keep separate from PHP)
│   ├── training_data.py      ← All prompts mapped to pages
│   ├── train_model.py        ← Run this to train the AI
│   ├── app.py                ← Flask API server
│   ├── requirements.txt      ← Python dependencies
│   └── model.pkl             ← Generated after training (auto-created)
│
└── your-php-app/
    └── chatbot.php           ← Drop this into any PHP page
```

---

## ⚙️ Step 1 — Install Python Dependencies

```bash
pip install -r requirements.txt
```

---

## 🧠 Step 2 — Train the AI Model

```bash
cd chatbot/
python train_model.py
```

You will see output like:
```
  Total training samples : 265
  Total intents          : 5
📊 Running 5-fold cross-validation...
   Mean Accuracy : 0.9800 (98.00%)
  Model saved to: model.pkl
```

This generates `model.pkl` — the trained AI brain.

---

## 🚀 Step 3 — Start the Flask API Server

```bash
python app.py
```

The server will run at: `http://localhost:5000`

Test it in your browser:
```
GET http://localhost:5000/
```

Test a chat message with curl:
```bash
curl -X POST http://localhost:5000/chat \
     -H "Content-Type: application/json" \
     -d '{"message": "I want to go to feeding page"}'
```

Expected response:
```json
{
  "status": "found",
  "intent": "group_feed_transaction",
  "confidence": 94.32,
  "message": "Here are some related pages:",
  "links": [
    { "label": "Group Feed Transaction", "url": "group_feed_transaction.php" }
  ]
}
```

---

## 🐘 Step 4 — Add Chatbot to Your PHP App

Copy `chatbot.php` to your PHP project. The chatbot widget will appear as a floating button on the bottom-right of the page.

Make sure your PHP server can reach `http://localhost:5000` (the Flask API).

---

## ➕ How to Add More Pages

Open `training_data.py` and add a new block:

```python
{
    "intent": "group_mortality",
    "url": "group_mortality.php",
    "label": "Group Mortality",
    "prompts": [
        "I want to go to mortality page",
        "show me mortality records",
        "dead animals",
        "animal death record",
        # ... add 50+ prompts for best accuracy
    ]
},
```

Then re-train:
```bash
python train_model.py
```

Then restart Flask:
```bash
python app.py
```

---

## 🔄 Keep Flask Running in Production

Use a process manager so Flask keeps running even if your terminal closes:

**Linux/Mac (using screen):**
```bash
screen -S chatbot
python app.py
# Press Ctrl+A then D to detach
```

**Or use PM2:**
```bash
npm install -g pm2
pm2 start app.py --interpreter python3 --name chatbot
pm2 save
```

---

## 📊 API Endpoints

| Method | Endpoint  | Description                          |
|--------|-----------|--------------------------------------|
| GET    | `/`       | Health check, lists loaded intents   |
| POST   | `/chat`   | Send a message, get page links back  |
| POST   | `/batch`  | Send multiple messages at once       |

### POST `/chat` — Request Body
```json
{ "message": "your user message here" }
```

### POST `/batch` — Request Body
```json
{ "messages": ["feeding page", "vaccination records", "medicine"] }
```

To Start The Application:
Run
[1] python test_runner_server.py
[2] python app.py
[3] ngrok http 80