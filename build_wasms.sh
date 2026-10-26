#!/usr/bin/env bash
# CraftOffice Suite - Automatic WASM Rebuild Script (Bash)
# Compiles WordCraft, GridCraft, PdfCraft, and DeckCraft to WebAssembly and copies assets to craftoffice/editor/wasm/

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BASE_DIR="$(cd "$SCRIPT_DIR/.." && pwd)"
TARGET_WASM_DIR="$SCRIPT_DIR/editor/wasm"

mkdir -p "$TARGET_WASM_DIR"

echo "========================================"
echo " Building WordCraft WASM..."
echo "========================================"
cd "$BASE_DIR/wordcraft/apps/wordcraft-web"
trunk build --release
cp ../../dist/web/wordcraft-web-*.js "$TARGET_WASM_DIR/wordcraft-web.js"
cp ../../dist/web/wordcraft-web-*_bg.wasm "$TARGET_WASM_DIR/wordcraft-web_bg.wasm"

echo "========================================"
echo " Building GridCraft WASM..."
echo "========================================"
cd "$BASE_DIR/gridcraft/apps/gridcraft-web"
trunk build --release
cp ../../dist/web/gridcraft-web-*.js "$TARGET_WASM_DIR/gridcraft-web.js"
cp ../../dist/web/gridcraft-web-*_bg.wasm "$TARGET_WASM_DIR/gridcraft-web_bg.wasm"

echo "========================================"
echo " Building PdfCraft WASM..."
echo "========================================"
cd "$BASE_DIR/pdfcraft/apps/pdfcraft-web"
trunk build --release
cp ../../dist/web/pdfcraft-web-*.js "$TARGET_WASM_DIR/pdfcraft-web.js"
cp ../../dist/web/pdfcraft-web-*_bg.wasm "$TARGET_WASM_DIR/pdfcraft-web_bg.wasm"

echo "========================================"
echo " Building DeckCraft WASM..."
echo "========================================"
cd "$BASE_DIR/deckcraft/apps/deckcraft-web"
trunk build --release
cp ../../dist/web/deckcraft-web-*.js "$TARGET_WASM_DIR/deckcraft-web.js"
cp ../../dist/web/deckcraft-web-*_bg.wasm "$TARGET_WASM_DIR/deckcraft-web_bg.wasm"

echo "========================================"
echo " SUCCESS! All 4 WASM engines recompiled! "
echo "========================================"
cd "$SCRIPT_DIR"
