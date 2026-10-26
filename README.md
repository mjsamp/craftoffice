# CraftOffice Suite (`local_craftoffice`)

[English](#english) | [Português](#português)

---

<a name="english"></a>
## 🇬🇧 English

**CraftOffice Suite** is a modern, enterprise-grade, pure-Rust WebAssembly (WASM) productivity office plugin for **Moodle 4.x/5.x**. It integrates WordCraft (wordprocessor), GridCraft (spreadsheet), PdfCraft (PDF editor), and DeckCraft (presentation slides) directly into Moodle using native Moodle File Storage API (`mdl_files`).

### ✨ Key Features

- **4 Pure-Rust WASM Apps**:
  - 📝 **WordCraft**: Wordprocessing editor (`.docx`)
  - 📊 **GridCraft**: Spreadsheet editor (`.xlsx`)
  - 📕 **PdfCraft**: PDF viewer & editor (`.pdf`)
  - 🖥️ **DeckCraft**: Slide presentation editor (`.pptx`)
- **Native Moodle Integration**: Uses Moodle File Storage API (`get_file_storage()`, `mdl_files`) for direct binary file persistence.
- **Advanced Dashboard & Central Manager**:
  - **Sidebar Folders & Categories**: Filter documents by application type (WordCraft, GridCraft, PdfCraft, DeckCraft) or access level (Public, Shared, Private) with dynamic counters.
  - **Real-Time Instant Search**: Filter documents instantly by title or author without reloading.
  - **Dual View Modes**: Toggle between **Grid View (Cards)** and **Detailed List View (Table)**, with user preference remembered in `localStorage`.
  - **Direct Download Button**: Quick one-click local copy download button on every card and table row.
- **Real-Time Collaboration & Presence**:
  - **Live Presence Heartbeat**: Shows active editors in real-time (`🟢 Editing (you)` or `👥 Editing (you) and N users` with tooltips listing active names).
  - **New Version Alerts**: Notifies active users when a colleague saves a newer version on the server (`🔔 [Author] saved a new version. [🔄 Reload to View]`).
- **Granular Access Control & Specific User Sharing**:
  - `Public (0)`: Viewable by anyone (including non-logged-in visitors in read-only mode).
  - `Authenticated (1)`: Editable by logged-in Moodle users.
  - `Private (2)`: Author and Admin only.
  - `Shared (3)`: Specific Moodle users selected via autocomplete sharing selector.
- **Bilingual & Multi-language Support**:
  - Full English (`en`) and Portuguese Brazil (`pt_br`) localization via Moodle `$string`.
  - Automatic browser locale detection in GridCraft and PdfCraft.
  - Native Portuguese UI translation layer for DeckCraft (ribbon tabs, menus, placeholders).
- **Portuguese Spreadsheet Formulas**:
  - GridCraft natively supports typing formulas in Portuguese (`=SOMA()`, `=MÉDIA()`, `=SE()`, `=PROCV()`, `=SOMASE()`, `=SEERRO()`, etc.) alongside English.
- **Moodle Standards Compliance**:
  - GPL v3 `LICENSE` file included.
  - Official Moodle copyright & GPL headers across all PHP files.
  - Mustache templates in `templates/` rendered via `$OUTPUT->render_from_template()`.
  - Clean technical English comments in PHP source code.

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

#### Automatic Build Script (PowerShell):
```powershell
cd C:\projects\craftoffice
.\build_wasms.ps1
```

---
### 📦 Instalação do Plugin no Moodle

1. Copy the directory `craftoffice` to Moodle local plugin's directory `seu_moodle_root/local/craftoffice`.
2. Login as Administrator.
3. Access **Site Administration ▸ Plugins** and follow the steps to install/update.
---

<a name="português"></a>
## 🇧🇷 Português

O **CraftOffice Suite** é uma suíte de produtividade moderna desenvolvida em Rust e WebAssembly (WASM) para o **Moodle 4.x/5.x**. Integre o WordCraft (editor de texto), GridCraft (planilhas), PdfCraft (editor de PDF) e DeckCraft (apresentações) diretamente ao Moodle via API de Arquivos do Moodle (`mdl_files`).

### ✨ Principais Funcionalidades

- **4 Aplicativos Rust WASM Nativos**:
  - 📝 **WordCraft**: Editor de documentos de texto (`.docx`)
  - 📊 **GridCraft**: Editor de planilhas de cálculo (`.xlsx`)
  - 📕 **PdfCraft**: Leitor e editor de PDF (`.pdf`)
  - 🖥️ **DeckCraft**: Editor de apresentações de slides (`.pptx`)
- **Integração Nativa com a Moodle File API**: Utiliza a Moodle File Storage API (`get_file_storage()`, `mdl_files`) para gravação direta dos arquivos binários.
- **Dashboard Central Avançado**:
  - **Pastas e Categorias no Menu Lateral**: Filtre documentos por tipo de aplicativo (WordCraft, GridCraft, PdfCraft, DeckCraft) ou nível de acesso (Público, Compartilhado, Privado) com contadores dinâmicos.
  - **Busca Instantânea em Tempo Real**: Filtre documentos pelo título ou autor instantaneamente sem recarregar a página.
  - **Modos de Exibição Alternáveis**: Alterne entre **Grade (Cards)** e **Lista Detalhada (Tabela)**, com lembrança automática da preferência no `localStorage`.
  - **Botão de Download Direto**: Botão de download rápido de cópia local em cada card e linha de tabela.
- **Colaboração em Tempo Real e Presença de Usuários**:
  - **Heartbeat de Presença Ativa**: Mostra quem está editando no momento (`🟢 Editando (Você)` ou `👥 Editando por você e mais X pessoas` com tooltip listando os nomes).
  - **Alerta de Nova Versão**: Avisa quando um colega salva uma nova versão no servidor (`🔔 [Autor] salvou uma nova versão... [🔄 Recarregar para Ver]`).
- **Controle de Acesso e Compartilhamento com Usuários Específicos**:
  - `Público (0)`: Acessível por qualquer visitante (modo leitura para não logados).
  - `Autenticado (1)`: Editável por usuários logados no Moodle.
  - `Privado (2)`: Exclusivo do Autor e Administrador.
  - `Compartilhado (3)`: Acesso restrito a usuários específicos selecionados via autocomplete.
- **Suporte a Múltiplos Idiomas (`en` e `pt_br`)**:
  - Localização completa via `$string` do Moodle.
  - Detecção automática do idioma do navegador no GridCraft e PdfCraft.
  - Camada de tradução nativa em Português para a interface do DeckCraft (abas, menus e placeholders).
- **Fórmulas em Português no GridCraft**:
  - Suporte nativo para digitação de fórmulas em Português (`=SOMA()`, `=MÉDIA()`, `=SE()`, `=PROCV()`, `=SOMASE()`, `=SEERRO()`, etc.) mantendo 100% de compatibilidade com o padrão OpenXML.
- **Conformidade com os Padrões do Moodle**:
  - Arquivo `LICENSE` (GPLv3) na raiz.
  - Cabeçalhos oficiais de copyright e GPL do Moodle em todos os arquivos `.php`.
  - Templates Mustache em `templates/` renderizados via `$OUTPUT->render_from_template()`.
  - Comentários em inglês técnico no código fonte PHP.

---

### 🚀 Como Compilar os Motores WASM a partir do Fontes

#### Script Automático no PowerShell (Windows):
```powershell
cd C:\projects\craftoffice
.\build_wasms.ps1
```

---

### 📦 Instalação do Plugin no Moodle

1. Copie a pasta `craftoffice` para o seu diretório do Moodle em `seu_moodle_root/local/craftoffice`.
2. Faça login como Administrador no Moodle.
3. Acesse **Administração do Site ▸ Plugins** e conclua a instalação/atualização.

---

### 📜 Licença

GPL v3 or later — http://www.gnu.org/copyleft/gpl.html
