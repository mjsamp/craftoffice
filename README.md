# CraftOffice Suite (`local_craftoffice`)

[English](#english) | [Português](#português)

---

<a name="english"></a>
## 🇬🇧 English

**CraftOffice Suite** is a modern, pure-Rust WebAssembly (WASM) productivity office plugin for **Moodle 4.x/5.x**. It integrates WordCraft (wordprocessor), GridCraft (spreadsheet), PdfCraft (PDF editor), and DeckCraft (presentation slides) directly into Moodle using native Moodle File Storage API (`mdl_files`).

### ✨ Key Features

- **4 Pure-Rust WASM Apps**:
  - 📝 **WordCraft**: Wordprocessing editor (`.docx`)
  - 📊 **GridCraft**: Spreadsheet editor (`.xlsx`)
  - 📕 **PdfCraft**: PDF viewer & editor (`.pdf`)
  - 🖥️ **DeckCraft**: Slide presentation editor (`.pptx`)
- **Native Moodle Integration**: Uses Moodle File Storage API (`get_file_storage()`, `mdl_files`) for direct binary file persistence.
- **Embedded Page Layout**: Uses `$PAGE->set_pagelayout('embedded')` to eliminate Moodle theme UI glitches and Bootstrap popover conflicts.
- **Granular Access Control**:
  - `Public (0)`: Allows guests / non-logged-in visitors to view documents in read-only mode and export/download local copies.
  - `Authenticated (1)`: Allows logged-in Moodle users to view and edit.
  - `Private (2)`: Author & Administrator access only.
- **Direct Multi-part Binary Save**: Saves modified document binaries (`.docx`, `.xlsx`, `.pdf`, `.pptx`) directly to Moodle without Base64 corruption or string truncation.
- **Standalone Local Test Server**: Includes `test_local.html` for local testing without requiring a running Moodle instance.

---

### 🛠️ Architecture & Source Code Modifications

To enable direct integration with Moodle, the following modifications were made across the Rust WASM source codes (`wordcraft-web`, `gridcraft-web`, `pdfcraft-web`, `deckcraft-web`):

#### 1. Automatic Document Loading on Startup
When launching an editor, the Rust WASM shell checks for `window._craftOfficeFileUrl`. If present, it performs an HTTP `fetch()` request, converts the file bytes in memory, and feeds them into the active document session via `Inbox`, bypassing the welcome screen and opening the stored file directly.

#### 2. Native Multi-Part Binary Save (`save_to_craftoffice`)
When the user saves (`Ctrl+S` or menu Save):
- The WASM engine generates the raw document bytes in memory.
- It creates a native JavaScript binary `Blob` and appends it as `doc_file` inside a `FormData` object.
- It executes an HTTP `POST` request to `/local/craftoffice/editor.php?id={doc_id}`.
- Moodle receives `$_FILES['doc_file']` and calls `$fs->create_file_from_pathname(...)`, storing the exact binary file in `mdl_files`.

#### 3. WASM Panic Hook Guard
Standard Rust panic hooks attempt to query OS process IDs via `std::process::id()`, which panics on WASM targets with `"no pids on this platform"`. We wrapped `std::process::id()` calls with `#[cfg(not(target_arch = "wasm32"))]` in `recovery.rs` and `guard.rs` to guarantee crash-free WASM execution.

---

### 🚀 Compiling WASM Engines from Source

#### Prerequisites
- Rust compiler with `wasm32-unknown-unknown` target:
  ```bash
  rustup target add wasm32-unknown-unknown
  ```
- Trunk WASM bundler:
  ```bash
  cargo install trunk
  ```

#### Build PowerShell Commands:

```powershell
# 1. Build WordCraft
cd wordcraft/apps/wordcraft-web
trunk build --release
Copy-Item "../../dist/web/wordcraft-web-*.js" "../../../craftoffice/editor/wasm/wordcraft-web.js" -Force
Copy-Item "../../dist/web/wordcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/wordcraft-web_bg.wasm" -Force

# 2. Build GridCraft
cd gridcraft/apps/gridcraft-web
trunk build --release
Copy-Item "../../dist/web/gridcraft-web-*.js" "../../../craftoffice/editor/wasm/gridcraft-web.js" -Force
Copy-Item "../../dist/web/gridcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/gridcraft-web_bg.wasm" -Force

# 3. Build PdfCraft
cd pdfcraft/apps/pdfcraft-web
trunk build --release
Copy-Item "../../dist/web/pdfcraft-web-*.js" "../../../craftoffice/editor/wasm/pdfcraft-web.js" -Force
Copy-Item "../../dist/web/pdfcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/pdfcraft-web_bg.wasm" -Force

# 4. Build DeckCraft
cd deckcraft/apps/deckcraft-web
trunk build --release
Copy-Item "../../dist/web/deckcraft-web-*.js" "../../../craftoffice/editor/wasm/deckcraft-web.js" -Force
Copy-Item "../../dist/web/deckcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/deckcraft-web_bg.wasm" -Force
```

---

### 📦 Moodle Plugin Installation

1. Copy the `craftoffice` folder into your Moodle installation at `your_moodle_root/local/craftoffice`.
2. Log in as an Administrator in Moodle.
3. Go to **Site Administration ▸ Notifications** and complete the database upgrade.

---
---

<a name="português"></a>
## 🇧🇷 Português

O **CraftOffice Suite** é uma suíte de produtividade moderna desenvolvida em Rust e WebAssembly (WASM) para o **Moodle 4.x/5.x**. Integre o WordCraft (editor de texto), GridCraft (planilhas), PdfCraft (editor de PDF) e DeckCraft (apresentações) diretamente ao Moodle via API de Arquivos do Moodle (`mdl_files`).

### ✨ Principais Recursos

- **4 Aplicativos Rust WASM Nativos**:
  - 📝 **WordCraft**: Editor de documentos de texto (`.docx`)
  - 📊 **GridCraft**: Editor de planilhas de cálculo (`.xlsx`)
  - 📕 **PdfCraft**: Leitor e editor de PDF (`.pdf`)
  - 🖥️ **DeckCraft**: Editor de apresentações de slides (`.pptx`)
- **Integração Nativa com a Moodle File API**: Utiliza a Moodle File Storage API (`get_file_storage()`, `mdl_files`) para gravação direta dos arquivos binários.
- **Layout Embutido no Moodle**: Usa `$PAGE->set_pagelayout('embedded')`, eliminando conflitos de Bootstrap tooltip/popover com temas do Moodle.
- **Controle de Acesso Granular**:
  - `Público (0)`: Permite que visitantes não logados visualizem o documento em Modo Leitura e exportem cópias locais.
  - `Autenticado (1)`: Permite que usuários logados no Moodle visualizem e editem.
  - `Privado (2)`: Acesso exclusivo do Autor e Administradores.
- **Salvamento Binário Multi-part Direto**: Salva arquivos binários modificados (`.docx`, `.xlsx`, `.pdf`, `.pptx`) diretamente no Moodle sem corrupção Base64 ou truncamento.
- **Servidor de Teste Local Standalone**: Inclui o arquivo `test_local.html` para testar os 4 editores localmente sem precisar do Moodle rodando.

---

### 🛠️ Arquitetura e Modificações nos Fontes

Para integrar os 4 aplicativos ao Moodle, realizamos as seguintes modificações no código-fonte Rust de cada aplicativo (`wordcraft-web`, `gridcraft-web`, `pdfcraft-web`, `deckcraft-web`):

#### 1. Carregamento Automático de Documentos no Início
Ao iniciar o editor, o shell WASM em Rust lê a variável `window._craftOfficeFileUrl`. Se presente, realiza uma requisição `fetch()` HTTP, converte os bytes do arquivo em memória e injeta-os na sessão ativa via `Inbox`, pulando a tela inicial e abrindo o arquivo salvo diretamente.

#### 2. Salvamento Binário Multi-Part Nativo (`save_to_craftoffice`)
Quando o usuário salva (`Ctrl+S` ou menu Salvar):
- O motor Rust gera os bytes do documento em memória.
- Cria um `Blob` binário nativo no JavaScript e o anexa como `doc_file` dentro de um `FormData`.
- Envia uma requisição HTTP `POST` para `/local/craftoffice/editor.php?id={doc_id}`.
- O PHP do Moodle recebe `$_FILES['doc_file']` e chama `$fs->create_file_from_pathname(...)`, gravando o arquivo binário na tabela `mdl_files`.

#### 3. Proteção contra Pânicos de Process ID no WASM
O hook de pânico padrão do Rust tenta consultar o PID do processo via `std::process::id()`, o que gera pânico no WebAssembly (`"no pids on this platform"`). Isolamos as chamadas de PID com `#[cfg(not(target_arch = "wasm32"))]` nos arquivos `recovery.rs` e `guard.rs`.

---

### 🚀 Como Compilar os Motores WASM a partir do Código-Fonte

#### Pré-requisitos
- Compilador Rust com suporte ao target `wasm32-unknown-unknown`:
  ```bash
  rustup target add wasm32-unknown-unknown
  ```
- Gerenciador de builds WASM Trunk:
  ```bash
  cargo install trunk
  ```

#### Comandos de Compilação no PowerShell:

```powershell
# 1. Compilar WordCraft
cd wordcraft/apps/wordcraft-web
trunk build --release
Copy-Item "../../dist/web/wordcraft-web-*.js" "../../../craftoffice/editor/wasm/wordcraft-web.js" -Force
Copy-Item "../../dist/web/wordcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/wordcraft-web_bg.wasm" -Force

# 2. Compilar GridCraft
cd gridcraft/apps/gridcraft-web
trunk build --release
Copy-Item "../../dist/web/gridcraft-web-*.js" "../../../craftoffice/editor/wasm/gridcraft-web.js" -Force
Copy-Item "../../dist/web/gridcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/gridcraft-web_bg.wasm" -Force

# 3. Compilar PdfCraft
cd pdfcraft/apps/pdfcraft-web
trunk build --release
Copy-Item "../../dist/web/pdfcraft-web-*.js" "../../../craftoffice/editor/wasm/pdfcraft-web.js" -Force
Copy-Item "../../dist/web/pdfcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/pdfcraft-web_bg.wasm" -Force

# 4. Compilar DeckCraft
cd deckcraft/apps/deckcraft-web
trunk build --release
Copy-Item "../../dist/web/deckcraft-web-*.js" "../../../craftoffice/editor/wasm/deckcraft-web.js" -Force
Copy-Item "../../dist/web/deckcraft-web-*_bg.wasm" "../../../craftoffice/editor/wasm/deckcraft-web_bg.wasm" -Force
```

---

### 📦 Instalação do Plugin no Moodle

1. Copie a pasta `craftoffice` para o seu diretório do Moodle em `seu_moodle_root/local/craftoffice`.
2. Faça login como Administrador no Moodle.
3. Acesse **Administração do Site ▸ Notificações** e conclua a atualização do banco de dados.

---

### 📜 Licença

GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
