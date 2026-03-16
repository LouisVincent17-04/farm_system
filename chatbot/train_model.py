#!/usr/bin/env python3
# ============================================================
# train_model.py
# Trains a TF-IDF + SVM classifier on the training data
# Run this once to generate: model.pkl
# ============================================================

import pickle
import numpy as np
from sklearn.pipeline import Pipeline
from sklearn.svm import LinearSVC
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.preprocessing import LabelEncoder
from sklearn.model_selection import cross_val_score
from training_data import data

print("=" * 50)
print("  Chatbot Model Trainer")
print("=" * 50)

# ── Build X (texts) and y (labels) ──────────────────────────
X = []
y = []

intent_map = {}  # intent -> { url, label }

for item in data:
    intent_map[item["intent"]] = {
        "url":   item["url"],
        "label": item["label"]
    }
    for prompt in item["prompts"]:
        X.append(prompt.lower())
        y.append(item["intent"])

print(f"\n✅ Total training samples : {len(X)}")
print(f"✅ Total intents          : {len(intent_map)}")
for intent, info in intent_map.items():
    count = y.count(intent)
    print(f"   • {intent:<30} ({count} prompts)  →  {info['url']}")

# ── Encode labels ────────────────────────────────────────────
le = LabelEncoder()
y_encoded = le.fit_transform(y)

# ── Build Pipeline: TF-IDF + LinearSVC ──────────────────────
# TF-IDF: converts text to numerical feature vectors
#   - analyzer='char_wb': uses character n-grams (handles typos better)
#   - word n-grams also included via analyzer='word'
# LinearSVC: fast, accurate Support Vector Machine classifier

pipeline = Pipeline([
    ("tfidf", TfidfVectorizer(
        analyzer="word",
        ngram_range=(1, 3),       # unigrams, bigrams, trigrams
        sublinear_tf=True,        # log scaling of term frequency
        min_df=1,
        max_features=10000
    )),
    ("clf", LinearSVC(
        C=1.0,
        max_iter=2000,
        class_weight="balanced"
    ))
])

# ── Cross-validation ─────────────────────────────────────────
print("\n📊 Running 5-fold cross-validation...")
scores = cross_val_score(pipeline, X, y_encoded, cv=5, scoring="accuracy")
print(f"   Accuracy per fold : {[round(s, 4) for s in scores]}")
print(f"   Mean Accuracy     : {scores.mean():.4f} ({scores.mean()*100:.2f}%)")

# ── Train on full dataset ────────────────────────────────────
print("\n🔧 Training on full dataset...")
pipeline.fit(X, y_encoded)
print("   Done!")

# ── Save everything ──────────────────────────────────────────
model_data = {
    "pipeline":   pipeline,
    "encoder":    le,
    "intent_map": intent_map
}

with open("model.pkl", "wb") as f:
    pickle.dump(model_data, f)

print("\n✅ Model saved to: model.pkl")
print("=" * 50)
print("  Run 'python app.py' to start the Flask API")
print("=" * 50)