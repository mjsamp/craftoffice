# Guia de Compilação dos Motores WebAssembly (WASM) para o CraftOffice

Todos os quatro aplicativos da suíte CraftOffice (**WordCraft**, **GridCraft**, **PdfCraft** e **DeckCraft**) estão integrados ao plugin local **CraftOffice** (`local_craftoffice`), localizados em `craftoffice/editor/wasm/`.

---

## 📁 Estrutura dos Arquivos WASM no Plugin

Os arquivos finais compilados no diretório `local/craftoffice/editor/wasm/` são:

- 📝 **WordCraft (Text/Docs):** `wordcraft-web.js` & `wordcraft-web_bg.wasm`
- 📊 **GridCraft (Spreadsheet/Calc):** `gridcraft-web.js` & `gridcraft-web_bg.wasm`
- 📕 **PdfCraft (PDF Editor):** `pdfcraft-web.js` & `pdfcraft-web_bg.wasm`
- 🖥️ **DeckCraft (Presentations/Slides):** `deckcraft-web.js` & `deckcraft-web_bg.wasm`

---

## 🛠️ Pré-requisitos

1. **Rust & Target WebAssembly**:
   ```bash
   rustup target add wasm32-unknown-unknown
   ```
2. **Trunk WASM Bundler**:
   ```bash
   cargo install trunk
   ```

---

## 🚀 Script Automático no PowerShell (Windows)

Você pode executar o script automático `build_wasms.ps1` localizado na raiz do plugin:

```powershell
cd C:\Users\marcos_1728\PhpstormProjects\craftoffice
.\build_wasms.ps1
```

---

## 💻 Comandos Individuais para Recompilar (PowerShell)

### 1. Recompilar o WordCraft (Docs)
```powershell
Set-Location C:\Users\marcos_1728\PhpstormProjects\wordcraft\apps\wordcraft-web
trunk build --release
Copy-Item "..\..\dist\web\wordcraft-web-*.js" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\wordcraft-web.js" -Force
Copy-Item "..\..\dist\web\wordcraft-web-*_bg.wasm" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\wordcraft-web_bg.wasm" -Force
```

### 2. Recompilar o GridCraft (Calc)
```powershell
Set-Location C:\Users\marcos_1728\PhpstormProjects\gridcraft\apps\gridcraft-web
trunk build --release
Copy-Item "..\..\dist\web\gridcraft-web-*.js" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\gridcraft-web.js" -Force
Copy-Item "..\..\dist\web\gridcraft-web-*_bg.wasm" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\gridcraft-web_bg.wasm" -Force
```

### 3. Recompilar o PdfCraft (PDF)
```powershell
Set-Location C:\Users\marcos_1728\PhpstormProjects\pdfcraft\apps\pdfcraft-web
trunk build --release
Copy-Item "..\..\dist\web\pdfcraft-web-*.js" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\pdfcraft-web.js" -Force
Copy-Item "..\..\dist\web\pdfcraft-web-*_bg.wasm" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\pdfcraft-web_bg.wasm" -Force
```

### 4. Recompilar o DeckCraft (Slides)
```powershell
Set-Location C:\Users\marcos_1728\PhpstormProjects\deckcraft\apps\deckcraft-web
trunk build --release
Copy-Item "..\..\dist\web\deckcraft-web-*.js" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\deckcraft-web.js" -Force
Copy-Item "..\..\dist\web\deckcraft-web-*_bg.wasm" "C:\Users\marcos_1728\PhpstormProjects\craftoffice\editor\wasm\deckcraft-web_bg.wasm" -Force
```

---

## 🐧 Script Bash para Linux / macOS (`build_wasms.sh`)

```bash
#!/usr/bin/env bash
set -e

BASE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
TARGET_WASM_DIR="$BASE_DIR/craftoffice/editor/wasm"

mkdir -p "$TARGET_WASM_DIR"

echo "=== Compilando WordCraft ==="
cd "$BASE_DIR/wordcraft/apps/wordcraft-web"
trunk build --release
cp ../../dist/web/wordcraft-web-*.js "$TARGET_WASM_DIR/wordcraft-web.js"
cp ../../dist/web/wordcraft-web-*_bg.wasm "$TARGET_WASM_DIR/wordcraft-web_bg.wasm"

echo "=== Compilando GridCraft ==="
cd "$BASE_DIR/gridcraft/apps/gridcraft-web"
trunk build --release
cp ../../dist/web/gridcraft-web-*.js "$TARGET_WASM_DIR/gridcraft-web.js"
cp ../../dist/web/gridcraft-web-*_bg.wasm "$TARGET_WASM_DIR/gridcraft-web_bg.wasm"

echo "=== Compilando PdfCraft ==="
cd "$BASE_DIR/pdfcraft/apps/pdfcraft-web"
trunk build --release
cp ../../dist/web/pdfcraft-web-*.js "$TARGET_WASM_DIR/pdfcraft-web.js"
cp ../../dist/web/pdfcraft-web-*_bg.wasm "$TARGET_WASM_DIR/pdfcraft-web_bg.wasm"

echo "=== Compilando DeckCraft ==="
cd "$BASE_DIR/deckcraft/apps/deckcraft-web"
trunk build --release
cp ../../dist/web/deckcraft-web-*.js "$TARGET_WASM_DIR/deckcraft-web.js"
cp ../../dist/web/deckcraft-web-*_bg.wasm "$TARGET_WASM_DIR/deckcraft-web_bg.wasm"

echo "=== Todos os motores WASM foram compilados com sucesso! ==="
```
