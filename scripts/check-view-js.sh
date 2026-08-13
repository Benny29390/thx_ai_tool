#!/bin/bash
# Prüft den JS-Block in einer PHP-View auf Syntaxfehler.
# Aufruf: scripts/check-view-js.sh views/lam/linkoptionen.php
F="${1:-}"
if [ -z "$F" ]; then echo "Usage: $0 <view.php>"; exit 1; fi
TMP=$(mktemp --suffix=.js)
sed -n '/^<script>$/,/^<\/script>$/{/^<script>$/d;/^<\/script>$/d;p}' "$F" > "$TMP"
if node --check "$TMP" 2>&1; then
    echo "✓ $F — JS Syntax OK"
    rm "$TMP"
    exit 0
else
    echo "✗ $F — JS Syntax-Fehler (siehe oben)"
    rm "$TMP"
    exit 1
fi
