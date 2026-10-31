# Vuexy — Mapa de Assets (CSS/JS/JSON/i18n) e Modelos de Página

**Complementa:** `vuexy-template-analise-tecnica.md` (visão geral de arquitetura e menu).
**Objetivo deste documento:** listar, por categoria de página, exatamente quais arquivos CSS/JS são carregados, onde ficam os arquivos de tradução/JSON, e documentar a estrutura ("modelo") de cada tipo de página — formulários, tabelas, cards, calendário, login/recuperação de senha, dashboards, extended UI e charts.

Todos os caminhos são relativos à raiz do template: `html/vertical-menu-template/../../assets/` → ou seja, a pasta real é `assets/` na raiz do pacote (fora de `html/`).

---

## 1. Inventário geral de arquivos (fora do HTML por página)

### 1.1 CSS "core" (carregado em 100% das páginas)
```
assets/vendor/fonts/iconify-icons.css        → ícones (Iconify, inclui Tabler Icons)
assets/vendor/libs/node-waves/node-waves.css → efeito ripple
assets/vendor/libs/pickr/pickr-themes.css    → color picker (usado no Theme Customizer)
assets/vendor/css/core.css                    → Bootstrap 5 customizado + utilitários Vuexy (arquivo principal do design system)
assets/css/demo.css                           → estilos exclusivos das páginas de demo (remover em produção)
assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css → scrollbar customizada
https://fonts.googleapis.com/css2             → Google Fonts (Public Sans)
```

### 1.2 JS "core" (carregado em 100% das páginas)
```
https://www.googletagmanager.com/gtm.js       → analytics de demo (remover em produção)
assets/vendor/libs/@algolia/autocomplete-js.js → motor da busca global (Ctrl+K)
assets/vendor/js/helpers.js                    → funções utilitárias do tema (Helpers.*)
assets/vendor/js/template-customizer.js        → painel "Theme Customizer" (remover em produção)
assets/js/config.js                            → variáveis globais de config (templateName etc.)
assets/vendor/libs/jquery/jquery.js            → jQuery (base)
assets/vendor/libs/popper/popper.js            → Popper.js (posicionamento)
assets/vendor/js/bootstrap.js                  → Bootstrap 5 JS (modal, dropdown, collapse, offcanvas, tab)
assets/vendor/libs/node-waves/node-waves.js    → efeito ripple
assets/vendor/libs/pickr/pickr.js              → color picker
assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js → scrollbar customizada
assets/vendor/libs/hammer/hammer.js            → gestos touch (swipe no menu mobile)
assets/vendor/libs/i18n/i18n.js                → motor i18next (traduções)
assets/vendor/js/menu.js                       → motor do menu vertical/horizontal (expand/collapse)
assets/js/main.js                              → bootstrap geral da página (dark mode, RTL, inicializa i18next, menu, dropdowns de navbar)
```

### 1.3 Arquivos de tradução (i18n) — **JSON**
Carregados dinamicamente pelo `i18next-http-backend` (configurado em `assets/js/main.js`):
```
loadPath: assetsPath + "json/locales/{{lng}}.json"
```
Arquivos confirmados existentes:
```
assets/json/locales/en.json   → inglês (idioma padrão/fallback)
assets/json/locales/fr.json   → francês
assets/json/locales/de.json   → alemão
assets/json/locales/ar.json   → árabe (usado também para testar layout RTL)
```
Cada arquivo é um dicionário plano `{ "Chave em inglês": "Tradução" }` com ~175 chaves, cobrindo **apenas os textos do menu lateral** (ex.: `"Dashboards"`, `"eCommerce"`, `"Add Product"`, `"Store Details"`...). **Não traduz o conteúdo das páginas**, só a navegação.

> **Para o SaaS:** este é o padrão a seguir para i18n — um JSON plano por idioma, carregado sob demanda, trocando `{{lng}}` dinamicamente. Recomenda-se expandir esse dicionário para cobrir todo o texto de UI (labels de formulário, mensagens de validação, etc.), não só o menu.

### 1.4 Outros JSON/dados
Não foram encontrados arquivos JSON de dados estáticos adicionais (ex. lista de países, geodata) expostos em `assets/json/` além da pasta `locales/` — os dados de gráficos (países em "Sales by Countries", séries do ApexCharts) são **gerados inline via JS** dentro dos arquivos `assets/js/<pagina>.js` de cada dashboard, não em JSON externo.

### 1.5 Fontes
```
assets/vendor/fonts/iconify-icons.css   → fonte de ícones Iconify
assets/vendor/fonts/flag-icons.css      → bandeiras (usado no seletor de idioma e em tabelas com país)
Google Fonts via <link> (fonts.googleapis.com/css2) → tipografia textual (Public Sans)
```

---

## 2. Bibliotecas de terceiros por categoria (CSS + JS), com página de referência

| Categoria | CSS adicional | JS adicional | Página de referência |
|---|---|---|---|
| **Login / Auth** | `form-validation.css`, `page-auth.css` | `popular.js` (jQuery Validation), `bootstrap5.js`, `auto-focus.js`, `pages-auth.js` | `auth-login-cover.html` |
| **Recuperação de senha (Forgot)** | idem Auth | idem Auth | `auth-forgot-password-cover.html` |
| **Redefinir senha (Reset)** | idem Auth | idem Auth | `auth-reset-password-cover.html` |
| **Two-Steps verification** | idem Auth | idem Auth | `auth-two-steps-cover.html` |
| **Forms — Basic Inputs** | — | `form-basic-inputs.js` | `forms-basic-inputs.html` |
| **Forms — Select & Tags** | `select2.css`, `tagify.css`, `bootstrap-select.css`, `typeahead.css` | `select2.js`, `tagify.js`, `bootstrap-select.js`, `typeahead.js`, `bloodhound.js`, `forms-selects.js`, `forms-tagify.js`, `forms-typeahead.js` | `forms-selects.html` |
| **Forms — Pickers (data/hora/cor)** | `flatpickr.css`, `bootstrap-daterangepicker.css`, `jquery-timepicker.css` | `moment.js`, `flatpickr.js`, `bootstrap-daterangepicker.js`, `jquery-timepicker.js`, `pickr.js`, `forms-pickers.js` | `forms-pickers.html` |
| **Forms — Editors (rich text)** | `typography.css`, `highlight.css`, `katex.css`, `editor.css` | `katex.js`, `highlight.js`, `quill.js`, `forms-editors.js` | `forms-editors.html` |
| **Forms — File Upload** | `dropzone.css` | `dropzone.js`, `forms-file-upload.js` | `forms-file-upload.html` |
| **Forms — Sliders** | `nouislider.css` | `nouislider.js`, `forms-sliders.js` | `forms-sliders.html` |
| **Forms — Switches** | — (só core) | — (só core) | `forms-switches.html` |
| **Form Layouts** (Vertical/Horizontal/Sticky) | `flatpickr.css`, `select2.css` | `cleave-zen.js` (máscaras), `moment.js`, `flatpickr.js`, `select2.js`, `form-layouts.js` | `form-layouts-vertical.html` |
| **Form Wizard** (Numbered/Icons) | `bs-stepper.css`, `bootstrap-select.css`, `select2.css`, `form-validation.css` | `bs-stepper.js`, `bootstrap-select.js`, `select2.js`, `form-wizard-numbered.js`, `form-wizard-validation.js` | `form-wizard-numbered.html` |
| **Form Validation** | `bootstrap-select.css`, `select2.css`, `flatpickr.css`, `typeahead.css`, `tagify.css`, `form-validation.css` | `select2.js`, `bootstrap-select.js`, `moment.js`, `flatpickr.js`, `typeahead.js`, `tagify.js`, `popular.js` (jQuery Validation), `form-validation.js` | `form-validation.html` |
| **Tables (básica)** | `fontawesome.css` | — (só core) | `tables-basic.html` |
| **Datatables** | `datatables.bootstrap5.css`, `responsive.bootstrap5.css`, `flatpickr.css` | `datatables-bootstrap5.js`, `moment.js`, `flatpickr.js`, `tables-datatables-advanced.js` | `tables-datatables-advanced.html` |
| **Calendar** | `fullcalendar.css`, `flatpickr.css`, `select2.css`, `editor.css`, `form-validation.css`, `app-calendar.css` | `fullcalendar.js`, `select2.js`, `moment.js`, `flatpickr.js`, `app-calendar-events.js`, `app-calendar.js` | `app-calendar.html` |
| **Charts — ApexCharts** | `apex-charts.css` | `apexcharts.js` + script específico por página (ex.: `cards-statistics.js`, `dashboards-analytics.js`) | `charts-apex.html`, `cards-statistics.html` |
| **Charts — ChartJS** | `chartjs.css` | `chartjs.js`, `charts-chartjs-legend.js`, `charts-chartjs.js` | `charts-chartjs.html` |
| **Leaflet Maps** | (leaflet.css, não inspecionado diretamente mas padrão do plugin) | `leaflet.js` (presumido) + `maps-leaflet.js` | `maps-leaflet.html` |
| **Extended UI — Treeview** | `jstree.css` | `jstree.js`, `extended-ui-treeview.js` | `extended-ui-treeview.html` |
| **Extended UI — Tour** | `shepherd.css` | `shepherd.js`, `extended-ui-tour.js` | `extended-ui-tour.html` |
| **Extended UI — SweetAlert2** | `animate.css`, `sweetalert2.css` | `sweetalert2.js`, `extended-ui-sweetalert2.js` | `extended-ui-sweetalert2.html` |
| **Kanban** | — (só core) | `jkanban.js`, `app-kanban.js` | `app-kanban.html` |
| **Users List (DataTable + filtros)** | `datatables.bootstrap5.css` (presumido) | `datatables-bootstrap5.js`, `select2.js`, `cleave-zen.js`, `app-user-list.js` | `app-user-list.html` |

> Padrão geral: cada módulo carrega **apenas** o CSS/JS específico que precisa, além do "core". O nome do arquivo JS de página normalmente segue `assets/js/[nome-do-arquivo-html-sem-extensao].js`.

---

## 3. Modelos de página (estrutura HTML/CSS detalhada)

### 3.1 Login (`auth-login-cover.html` / `auth-login-basic.html`)

```html
<div class="authentication-wrapper authentication-cover">
  <div class="authentication-inner row m-0">
    <!-- Lado ilustração (só na variante "cover") -->
    <div class="d-none d-lg-flex col-lg-7 p-0">
      <div class="auth-cover-bg-color w-100 ...">
        <img class="auth-cover-illustration w-100" src="...illustration-login.png">
      </div>
    </div>
    <!-- Formulário -->
    <div class="d-flex col-12 col-lg-5 align-items-center authentication-bg p-sm-12 p-6">
      <div class="w-px-400 mx-auto">
        <div class="app-brand mb-6">...logo + nome...</div>
        <h4>Welcome to Vuexy! 👋</h4>
        <p>Please sign-in to your account...</p>
        <form id="formAuthentication" class="mb-6" action="index.html" method="GET">
          <div class="mb-6"><label for="email">Email or Username</label><input type="text" class="form-control" id="email"></div>
          <div class="mb-6">
            <label for="password">Password</label>
            <div class="input-group input-group-merge">
              <input type="password" id="password" class="form-control">
              <span class="input-group-text cursor-pointer"><i class="icon-base ri ri-eye-off-line"></i></span>
            </div>
          </div>
          <div class="my-8"><div class="form-check d-flex justify-content-between">
            <input class="form-check-input" type="checkbox" id="remember-me">
            <label for="remember-me">Remember Me</label>
            <a href="auth-forgot-password-cover.html">Forgot Password?</a>
          </div></div>
          <button class="btn btn-primary d-grid w-100">Login</button>
        </form>
        <p class="text-center">New on our platform? <a href="auth-register-cover.html">Create an account</a></p>
        <div class="divider my-6"><div class="divider-text">or</div></div>
        <div class="d-flex justify-content-center"><!-- botões social login (Facebook/Twitter/GitHub/Google) --></div>
      </div>
    </div>
  </div>
</div>
```
**CSS/JS específicos:** `page-auth.css`, `form-validation.css` + `pages-auth.js` (toggle de visibilidade de senha, submit handler).
**Variantes:** `-basic` (sem ilustração, card centralizado) vs `-cover` (split screen com imagem).

### 3.2 Esqueci a senha (`auth-forgot-password-cover.html`)
Mesmo wrapper `authentication-wrapper authentication-cover`. Formulário reduzido:
```html
<form>
  <div class="mb-6"><label for="email">Email</label><input type="text" id="email" class="form-control"></div>
  <button class="btn btn-primary d-grid w-100 mb-6" type="submit">Send Reset Link</button>
  <input type="hidden">
</form>
<a href="auth-login-cover.html"><i class="icon-base ri ri-arrow-left-s-line"></i> Back to login</a>
```

### 3.3 Redefinir senha (`auth-reset-password-cover.html`)
```html
<form>
  <div class="mb-6"><label for="password">New Password</label>
    <div class="input-group input-group-merge"><input type="password" id="password" class="form-control"><span class="input-group-text">👁</span></div>
  </div>
  <div class="mb-6"><label for="confirm-password">Confirm Password</label>
    <div class="input-group input-group-merge"><input type="password" id="confirm-password" class="form-control"><span class="input-group-text">👁</span></div>
  </div>
  <button class="btn btn-primary d-grid w-100 mb-6">Set new password</button>
  <input type="hidden">
</form>
```
**Padrão comum a toda a família Auth:** `.authentication-wrapper` → `.authentication-inner` → `.app-brand` (logo) → `<h4>` título + `<p>` subtítulo → `<form id="formAuthentication">` → link de navegação cruzada (login ↔ registro ↔ esqueci senha) → footer com imagens decorativas (`auth-cover-illustration`, `auth-cover-mask-*`, só na variante cover).

### 3.4 Formulários (Form Elements / Layouts / Wizard / Validation)

**Form Layouts (`form-layouts-vertical.html`):**
```html
<div class="card">
  <h5 class="card-header">Personal Info</h5>
  <div class="card-body">
    <form>
      <div class="row">
        <div class="col-md-6 mb-6"><label class="form-label">First Name</label><input class="form-control"></div>
        <div class="col-md-6 mb-6"><label class="form-label">Last Name</label><input class="form-control"></div>
      </div>
      <!-- select2, flatpickr (date), cleave-zen (telefone/cartão) misturados nos campos -->
      <div class="mt-6"><button class="btn btn-primary me-4">Submit</button><button class="btn btn-label-secondary" type="reset">Cancel</button></div>
    </form>
  </div>
</div>
```
Padrão de grid: `row` + `col-md-*` + `mb-6` por campo; rótulo sempre em `<label class="form-label">` acima do input (layout vertical) ou ao lado via `col-form-label` (layout horizontal).

**Form Wizard Numbered (`form-wizard-numbered.html`):** usa **bs-stepper**:
```html
<div class="bs-stepper wizard-numbered">
  <div class="bs-stepper-header">
    <div class="step" data-target="#step-account-details">
      <button class="step-trigger"><span class="bs-stepper-circle">1</span><span class="bs-stepper-label">Account Details</span></button>
    </div>
    <div class="line"></div>
    <!-- repete por etapa -->
  </div>
  <div class="bs-stepper-content">
    <form id="numbered-steps-form" onsubmit="return false">
      <div id="step-account-details" class="content">...campos + botões Next/Previous...</div>
    </form>
  </div>
</div>
```

**Form Validation (`form-validation.html`):** mesma estrutura de form básico, mas cada input recebe `data-*` do plugin de validação (bundle `popular.js` = jQuery Validation Plugin) e exibe `<div class="invalid-feedback">` condicional por campo.

### 3.5 Tabelas

**Tabela básica (`tables-basic.html`):** HTML puro Bootstrap — `table.table` com modificadores (`table-bordered`, `table-striped`, `table-hover`, `table-dark`, `table-sm`) dentro de `.card` → `.table-responsive`.

**DataTables (`tables-datatables-advanced.html`, `app-user-list.html`):**
```html
<div class="card">
  <div class="card-header"><!-- filtros select2 por coluna (Role/Plan/Status) --></div>
  <div class="card-datatable table-responsive">
    <table class="datatables-users table">
      <thead><tr><th></th><th></th><th>User</th><th>Role</th><th>Plan</th><th>Billing</th><th>Status</th><th>Actions</th></tr></thead>
    </table>
  </div>
</div>
```
Inicialização via JS (`app-user-list.js`): `$('.datatables-users').DataTable({ ajax: ..., columns: [...], dom: ..., buttons: [...] })` — paginação, export (print/csv/excel/pdf via extensão `buttons`), busca global e por coluna.

### 3.6 Cards

- **Basic** (`cards-basic.html`): `.card` + `.card-header` + `.card-body`, variações de alinhamento de título/subtítulo/imagem.
- **Advance** (`cards-advance.html`): cards com overlay de imagem, badges flutuantes, grupos de avatar.
- **Statistics** (`cards-statistics.html`): `.card` com ícone em `.avatar.avatar-initial.rounded` + número grande + label, usado nos dashboards como KPI tile.
- **Analytics** (`cards-analytics.html`): `.card` com **ApexCharts** embutido (radial bar, área) + estatística ao lado.
- **Actions** (`cards-actions.html`): cards com botões de ação no header (collapse, reload, remove) via `data-bs-toggle` + JS customizado.

### 3.7 Calendário (`app-calendar.html`)

```html
<div class="calendar-wrapper">
  <div class="sidebar"><!-- filtros de categoria de evento (checkbox coloridos) + botão "Add Event" --></div>
  <div class="card"><div id="calendar"></div></div> <!-- FullCalendar renderiza aqui -->
</div>
<!-- Modal de evento (Add/Edit) -->
<div class="offcanvas offcanvas-end" id="addEventSidebar">
  <form id="addEventForm">
    <input id="eventTitle">
    <select id="eventCategory"><!-- select2: Business/Personal/Family/Holiday/ETC --></select>
    <input id="startDate"><!-- flatpickr --><input id="endDate">
    <textarea id="eventDescription"><!-- Quill/editor opcional --></textarea>
    <button type="submit">Add</button>
  </form>
</div>
```
Bibliotecas: **FullCalendar** (grid/mês/semana/dia, drag-and-drop de evento), **Select2** (categoria), **Flatpickr** (datas), inicializado em `app-calendar.js` + `app-calendar-events.js` (dados mock de eventos).

### 3.8 Dashboards (ver documento anterior para detalhamento completo por dashboard)

Estrutura recorrente: `.container-xxl` → `.row` com `.col-*` → N× `.card.h-100`, cada card contendo:
```html
<div class="card h-100">
  <div class="card-header d-flex justify-content-between">
    <h5 class="card-title mb-0">Título</h5>
    <div class="dropdown"><button data-bs-toggle="dropdown">⋮</button>
      <ul class="dropdown-menu"><li><a>Refresh</a></li><li><a>Download</a></li><li><a>Delete</a></li></ul>
    </div>
  </div>
  <div class="card-body">
    <div id="chartId"></div> <!-- ApexCharts renderiza aqui via JS -->
  </div>
</div>
```
Script de dados/série fica em `assets/js/<nome-do-dashboard>.js` (ex.: `dashboards-analytics.js`), que inicializa cada `#chartId` com `new ApexCharts(el, options).render()`.

### 3.9 Extended UI

- **Avatar** (`extended-ui-avatar.html`): classes `.avatar`, `.avatar-sm/md/lg/xl`, `.avatar-initial`, `.avatar-group` (grupo sobreposto), badge de status (`.avatar-status-online/busy/away/offline`).
- **Timeline** (`extended-ui-timeline-basic.html` / `-fullscreen.html`): lista `<ul class="timeline">` → `<li class="timeline-item timeline-item-transparent">` com `.timeline-point` (ícone/cor) + `.timeline-event` (card de conteúdo) — sem lib JS externa, só CSS.
- **Treeview** (`extended-ui-treeview.html`): **jsTree** — `<div id="treeview">` inicializado via `$('#treeview').jstree()`.
- **Tour** (`extended-ui-tour.html`): **Shepherd.js** — define passos (`Shepherd.Tour().addStep(...)`) com overlay e setas apontando para elementos da página; usado para onboarding guiado.
- **SweetAlert2** (`extended-ui-sweetalert2.html`): substitui `alert()/confirm()` nativos por modais estilizados (`Swal.fire({...})`), com variações (success/error/warning/input/custom HTML).
- **BlockUI**: overlay de loading sobre um container específico durante requisições assíncronas.
- **Star Ratings**: inputs de avaliação por estrelas (plugin próprio, sem dependência pesada).

### 3.10 Charts

- **ApexCharts** (`charts-apex.html`): tipos usados no template — linha, área, barra, coluna, radial bar, donut, radar, polar, bubble, candlestick, heatmap. Cada gráfico é um `<div id="...">` + objeto JS de configuração (`series`, `chart.type`, `colors`, `xaxis`) renderizado via `new ApexCharts(...).render()`.
- **ChartJS** (`charts-chartjs.html`): tipos — linha, barra, radar, pizza, donut, polar, bubble, scatter; usa `<canvas>` em vez de `<div>` (diferença chave na hora de portar componente). Plugin de legenda customizada via `charts-chartjs-legend.js`.
- **Leaflet Maps** (`maps-leaflet.html`): mapas interativos (marcadores, popups, camadas) via Leaflet.js — útil para localização de clientes/entregas no SaaS (ex.: módulo de logística).

---

## 4. Resumo rápido — "preciso de X, qual lib/arquivo usar?"

| Preciso de... | Biblioteca | Arquivo JS | Arquivo CSS |
|---|---|---|---|
| Select avançado/multi-select | Select2 | `select2.js` | `select2.css` |
| Tags de input | Tagify | `tagify.js` | `tagify.css` |
| Autocomplete de texto | Typeahead + Bloodhound | `typeahead.js`, `bloodhound.js` | `typeahead.css` |
| Date/time picker | Flatpickr | `flatpickr.js` | `flatpickr.css` |
| Intervalo de datas | Daterangepicker | `bootstrap-daterangepicker.js` | `bootstrap-daterangepicker.css` |
| Time picker | jQuery Timepicker | `jquery-timepicker.js` | `jquery-timepicker.css` |
| Rich text editor | Quill | `quill.js` | `editor.css` |
| Syntax highlighting | Highlight.js | `highlight.js` | `highlight.css` |
| Fórmulas matemáticas | KaTeX | `katex.js` | `katex.css` |
| Upload de arquivos (drag&drop) | Dropzone | `dropzone.js` | `dropzone.css` |
| Slider de range | noUiSlider | `nouislider.js` | `nouislider.css` |
| Máscara de input (telefone/cartão) | Cleave-zen | `cleave-zen.js` | — |
| Wizard/stepper | bs-stepper | `bs-stepper.js` | `bs-stepper.css` |
| Validação de formulário | jQuery Validation (bundle `popular.js`) | `popular.js` | `form-validation.css` |
| Tabela com paginação/busca | DataTables + Bootstrap5 adapter | `datatables-bootstrap5.js` | `datatables.bootstrap5.css`, `responsive.bootstrap5.css` |
| Calendário completo | FullCalendar | `fullcalendar.js` | `fullcalendar.css` |
| Gráficos (dashboard) | ApexCharts | `apexcharts.js` | `apex-charts.css` |
| Gráficos (canvas-based) | ChartJS | `chartjs.js` | `chartjs.css` |
| Mapas | Leaflet | `leaflet.js` | `leaflet.css` |
| Kanban board | jKanban | `jkanban.js` | — |
| Árvore de nós (treeview) | jsTree | `jstree.js` | `jstree.css` |
| Tour guiado/onboarding | Shepherd.js | `shepherd.js` | `shepherd.css` |
| Alertas/modais bonitos | SweetAlert2 | `sweetalert2.js` | `sweetalert2.css`, `animate.css` |
| Color picker | Pickr | `pickr.js` | `pickr-themes.css` |
| Select nativo estilizado | Bootstrap-select | `bootstrap-select.js` | `bootstrap-select.css` |
| i18n / tradução | i18next + i18next-http-backend | `i18n.js` | — |

---

## 5. Observações para o build do SaaS

1. **Remover definitivamente em produção:** `template-customizer.js`, `assets/css/demo.css`, Google Tag Manager (`gtm.js`), e qualquer referência a `fonts.googleapis.com` deve ser auto-hospedada se o tenant exigir compliance de privacidade (LGPD) sem terceiros externos.
2. **i18n:** a estrutura `assets/json/locales/{{lng}}.json` é o padrão correto a seguir; adicionar `pt-br.json` (ou `pt.json`) como idioma padrão para o contexto da Somvibe, cobrindo não só o menu, mas labels de formulário, mensagens de validação e textos de dashboard.
3. **Carregamento condicional de libs pesadas** (DataTables, FullCalendar, ApexCharts, Quill, jsTree, Leaflet) deve ser feito por rota/módulo no bundler do SaaS — nenhuma página do template carrega todas de uma vez, e o SaaS deve preservar esse comportamento para performance.
4. **jQuery como dependência transversal:** quase todos os plugins (Select2, Flatpickr, DataTables, Dropzone, jsTree) dependem de jQuery. Se o SaaS for construído em um framework moderno (React/Vue) sem jQuery, será necessário substituir cada plugin por um equivalente nativo do framework (ex.: `react-select` no lugar de Select2, `react-big-calendar`/`FullCalendar React` no lugar do FullCalendar jQuery, `react-dropzone` no lugar de Dropzone).
5. **Form Validation** usa um plugin jQuery (`popular.js`) — em uma stack moderna, recomenda-se substituir por `zod`/`yup` + `react-hook-form` (ou equivalente), mantendo apenas o *padrão visual* (`.invalid-feedback`, classes `is-invalid`) do Bootstrap.

---

## 6. Configuração do mapa de demonstração

- [Confirmado] `assets/js/app-logistics-fleet.js` não inclui token Mapbox fixo. O mapa usa `window.MAPBOX_PUBLIC_TOKEN`, que deve ser disponibilizado em tempo de execução antes desse script. Sem uma string com prefixo público `pk.`, o script encerra a inicialização do mapa sem acessar a API Mapbox.
- [Confirmado] O valor do token não deve ser versionado nos assets ou na documentação. Configurar o valor no ambiente autorizado e expor ao navegador apenas o token público destinado ao mapa; nunca expor um token secreto. O comportamento foi definido na remoção da credencial detectada pelo GitHub Push Protection.

---

**Fim do documento.**
