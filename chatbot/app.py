#!/usr/bin/env python3
# ============================================================
# app.py — Flask AI Chatbot API Server
# Goal: return ALL relevant pages for a given prompt
# Run: python app.py
# this is app.py
# ============================================================

import pickle
import numpy as np
from flask import Flask, request, jsonify

app = Flask(__name__)

print("Loading model...")
try:
    with open("model.pkl", "rb") as f:
        model_data = pickle.load(f)

    pipeline   = model_data["pipeline"]
    le         = model_data["encoder"]
    intent_map = model_data["intent_map"]
    print(f"✅ Model loaded — {len(intent_map)} intents ready")

except FileNotFoundError:
    print("❌ model.pkl not found! Run: python train_model.py")
    exit(1)

# ── Prediction — returns ALL pages above threshold ───────────
def get_top_predictions(text, threshold, top_n):
    text_clean = text.lower().strip()
    decision   = pipeline.decision_function([text_clean])[0]

    # Softmax normalization → confidence %
    exp_scores        = np.exp(decision - np.max(decision))
    confidence_scores = exp_scores / exp_scores.sum()

    all_intents = le.inverse_transform(range(len(confidence_scores)))
    ranked = sorted(
        zip(all_intents, confidence_scores),
        key=lambda x: x[1],
        reverse=True
    )

    return [
        {"intent": intent, "confidence": float(conf)}
        for intent, conf in ranked[:top_n]
        if float(conf) >= threshold
    ]

# ── Confidence label helper ───────────────────────────────────
def confidence_label(pct):
    if pct >= 80:   return "Very High"
    elif pct >= 60: return "High"
    elif pct >= 40: return "Moderate"
    elif pct >= 20: return "Low"
    else:           return "Very Low"

# ── Dynamic threshold logic ───────────────────────────────────
# You have 119 intents — confidence gets spread very thin on
# short queries. The old thresholds (0.05+) were too high,
# causing single words like "feed" to return nothing even
# though the intent exists and is trained.
def get_threshold_and_topn(word_count):
    if word_count <= 1:
        return 0.005, 10   # 1 word — very low bar, confidence spreads across 119 intents
    elif word_count == 2:
        return 0.015,  8
    elif word_count == 3:
        return 0.02,  6
    elif word_count <= 5:
        return 0.03,  5
    else:
        return 0.05,  5

# ── GET / — health check ─────────────────────────────────────
@app.route("/", methods=["GET"])
def health():
    return jsonify({
        "status":  "ok",
        "message": "Chatbot API is running",
        "intents": list(intent_map.keys())
    })

# ── POST /chat ───────────────────────────────────────────────
@app.route("/chat", methods=["POST"])
def chat():
    body         = request.get_json(silent=True) or {}
    user_message = body.get("message", "").strip()

    if not user_message:
        return jsonify({
            "status":  "error",
            "message": "No message provided.",
            "links":   []
        }), 400

    word_count       = len(user_message.split())
    threshold, top_n = get_threshold_and_topn(word_count)
    matches          = get_top_predictions(user_message, threshold=threshold, top_n=top_n)

    if not matches:
        return jsonify({
            "status":  "not_found",
            "message": "I'm sorry, I wasn't able to find a page related to your request. "
                       "I'm currently only able to assist with navigation within the system. "
                       "Please try rephrasing your request, or you may contact Louis "
                       "Vincent for further assistance. Type 'dev' for more info.",
            "links": []
        })

    links = []
    for match in matches:
        page       = intent_map[match["intent"]]
        confidence = round(match["confidence"] * 100, 2)
        links.append({
            "label":            page["label"],
            "url":              page["url"],
            "confidence":       confidence,
            "confidence_label": confidence_label(confidence)
        })

    top     = links[0]
    summary = (
        f"Found {len(links)} result(s) — "
        f"top match: {top['label']} at {top['confidence']}% confidence ({top['confidence_label']})"
    )

    return jsonify({
        "status":  "found",
        "message": summary,
        "links":   links
    })

# ── POST /batch ──────────────────────────────────────────────
@app.route("/batch", methods=["POST"])
def batch():
    body     = request.get_json(silent=True) or {}
    messages = body.get("messages", [])

    results = []
    for msg in messages:
        word_count       = len(msg.split())
        threshold, top_n = get_threshold_and_topn(word_count)
        matches          = get_top_predictions(msg, threshold=threshold, top_n=top_n)

        results.append({
            "input":   msg,
            "matches": [
                {
                    "intent":           m["intent"],
                    "confidence":       round(m["confidence"] * 100, 2),
                    "confidence_label": confidence_label(round(m["confidence"] * 100, 2)),
                    "url":              intent_map[m["intent"]]["url"]
                }
                for m in matches
            ]
        })

    return jsonify({"results": results})

# ── Start server ─────────────────────────────────────────────
# if __name__ == "__main__":
#     app.run(
#         host="0.0.0.0",
#         port=5000,
#         debug=False,
#         threaded=True
#     )

if __name__ == "__main__":
    from waitress import serve
    serve(app, host="10.1.1.33", port=5000, threads=4)