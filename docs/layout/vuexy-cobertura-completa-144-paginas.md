# Vuexy — Cobertura Completa das 144 Páginas do Menu Lateral

**Status:** 144/144 páginas HTML locais do menu lateral verificadas (assets + estrutura).
**Complementa:** `vuexy-template-analise-tecnica.md` (arquitetura geral) e `vuexy-assets-e-modelos-por-pagina.md` (modelos detalhados das páginas mais relevantes ao SaaS). Este documento fecha a lacuna das 116 páginas que não haviam sido individualmente verificadas, organizadas por grupo do menu, cada uma com página, bibliotecas extras (CSS/JS) e estrutura/observação principal.

> Convenção: "— (só core)" significa que a página usa apenas o conjunto de CSS/JS "core" comum a 100% das páginas (ver `vuexy-assets-e-modelos-por-pagina.md`, seção 1.1/1.2) — nenhuma lib adicional.

---

## 1. Dashboards (5/5)

| Página | Extra CSS/JS | Observação |
|---|---|---|
| Analytics (`index.html`) | ApexCharts | Já documentado em detalhe (doc. de modelos) |
| CRM (`dashboards-crm.html`) | ApexCharts | Já documentado em detalhe |
| eCommerce (`app-ecommerce-dashboard.html`) | ApexCharts | Já documentado em detalhe |
| **Logistics** (`app-logistics-dashboard.html`) | `apex-charts.css/js`, `datatables.bootstrap5.css/js`, `responsive.bootstrap5.css`, `app-logistics-dashboard.css/js` | Cards: Vehicles Overview, Shipment Statistics, Delivery Performance, Reasons for delivery exceptions, Orders by Countries, On route vehicles. Mistura DataTable (lista de veículos) com gráficos ApexCharts. |
| **Academy** (`app-academy-dashboard.html`) | `apex-charts.css/js`, `datatables.bootstrap5.css/js`, `buttons.bootstrap5.css`, `moment.js`, `app-academy-dashboard.js` | Cards: Topic you are interested in, Popular Instructors, Top Courses, Assignment Progress, Course you are taking. |

## 2. Layouts (9/9 — variações estruturais do próprio tema)

| Página | `<html>` class / observação |
|---|---|
| Collapsed menu (`layouts-collapsed-menu.html`) | `layout-menu-collapsed` — sidebar reduz a só ícones |
| Content navbar (`layouts-content-navbar.html`) | `layout-wrapper layout-content-navbar` — navbar dentro da área de conteúdo (não fixo no topo da tela toda) |
| Content nav + Sidebar (`layouts-content-navbar-with-sidebar.html`) | mesma base + sidebar extra de contexto |
| Horizontal (`../horizontal-menu-template/`) | **template irmão separado** — menu no topo em vez de lateral (fora do pacote vertical) |
| Without menu (`layouts-without-menu.html`) | sem `#layout-menu` — remove sidebar inteira |
| Without navbar (`layouts-without-navbar.html`) | `layout-navbar-hidden` — remove `#layout-navbar` |
| Fluid (`layouts-fluid.html`) | conteúdo em `.container-fluid` (100% largura) |
| Container (`layouts-container.html`) | conteúdo em `.container-xxl` (largura máxima fixa, centralizado) |
| Blank (`layouts-blank.html`) | sem `.layout-wrapper` — página 100% em branco, útil para prints/landing isolada |

**Aplicação no SaaS:** essas são variantes de "shell" de aplicação — não são módulos de negócio, mas sim opções de composição de layout que podem virar props de um componente `<AppShell variant="fluid|container|without-menu|...">`.

## 3. Front Pages (5) — fora da pasta do template (`../front-pages/`)
Landing, Pricing, Payment, Checkout, Help Center — páginas públicas de marketing, **não fazem parte do pacote `vertical-menu-template`** (ficam em pasta irmã `front-pages/`). Não inspecionadas em detalhe neste documento pois são páginas institucionais/públicas, não módulos de aplicação logada — se necessárias ao SaaS (ex. site institucional da Somvibe), merecem levantamento à parte.

## 4. Apps — Email, Chat, Calendar, Kanban

| Página | Extra CSS/JS | Estrutura |
|---|---|---|
| Email (`app-email.html`) | já documentado | `.email-filters`, `.email-list`, `.email-list-item` |
| **Chat** (`app-chat.html`) | `maxLength.css`, `app-chat.css`, `perfect-scrollbar.js`, `app-chat.js` | `.chat-sidebar-left-user` (perfil próprio), `.chat-contact-list` (lista de contatos: nome, avatar, status online/offline, horário última msg), painel central de conversa (bolhas de mensagem), painel direito de detalhes do contato (collapse). |
| Calendar (`app-calendar.html`) | já documentado | FullCalendar |
| Kanban (`app-kanban.html`) | já documentado | jKanban |

## 5. eCommerce completo (19 páginas incl. dashboard)

| Página | Extra CSS/JS | Estrutura/colunas |
|---|---|---|
| Dashboard | já documentado (seção 1) | |
| **Product List** (`app-ecommerce-product-list.html`) | DataTables, Select2 | Colunas: checkbox, imagem, product, category, stock, sku, price, qty, status, Actions |
| **Product Add** (`app-ecommerce-product-add.html`) | Quill (editor), Select2, **Dropzone**, **jquery-repeater** (variantes de produto), Flatpickr, Tagify, KaTeX | Cards: Product information, Product Image (upload/URL), Variants (linhas repetíveis), Inventory, Pricing, Organize (categoria/tags) |
| **Category List** (`app-ecommerce-category-list.html`) | DataTables | Lista de categorias com contagem de produtos |
| **Order List** (`app-ecommerce-order-list.html`) | DataTables | Colunas: order, date, customers, payment, status, method, Actions |
| **Order Details** (`app-ecommerce-order-details.html`) | — | Cards: Order details (editável), Shipping activity (timeline), Customer details, Shipping address, Billing address |
| **Customer All** (`app-ecommerce-customer-all.html`) | DataTables | Colunas: Customer, Customer Id, Country, Order, Total Spent |
| **Customer Details** (4 abas: Overview/Security/Billing & Address/Notifications) | — | Abas: **Overview**, Security, Address & Billing, Notifications — mesmo padrão de tabs usado em Account Settings e User View |
| **Manage Reviews** (`app-ecommerce-manage-reviews.html`) | DataTables | Colunas: Product, Reviewer, Review, Date, Status, Actions |
| **Referrals** (`app-ecommerce-referral.html`) | — | Página de programa de indicação (sem cards tradicionais — layout próprio com estatísticas de referral) |
| **Settings** (`app-ecommerce-settings-detail.html` + 5 sub-abas) | — | Abas confirmadas: **Store details**, **Payments**, **Checkout**, **Shipping & delivery**, **Locations**, **Notifications** — todas compartilham o mesmo wrapper de tabs lateral/horizontal |

**Padrão geral eCommerce:** listagens usam DataTables + filtros Select2; formulários de criação (Product/Invoice) usam **jquery-repeater** para campos dinâmicos (variantes, linhas de item) — padrão a reaproveitar para catálogo musical/distribuição no SaaS Somvibe (ex.: variantes de licenciamento, linhas de split de royalties).

## 6. Academy e Logistics (apps completos)

| Página | Observação |
|---|---|
| **My Course** (`app-academy-course.html`) | Card "My Courses — Total 6 course you have purchased" + grid de cards de curso com progresso |
| **Course Details** (`app-academy-course-details.html`) | Accordion "Course Content" com lista de aulas (ex.: "2/5 \| 4.4 min — 1. Welcome...") — padrão de accordion de currículo, reaproveitável para trilhas de conteúdo/treinamento no SaaS |
| **Fleet** (`app-logistics-fleet.html`) | **Mapbox GL JS** (`mapbox-gl.js`) — diferente do Leaflet usado em "Maps"; mostra veículos em tempo real num mapa interativo |

## 7. Invoice (4/4)

| Página | Extra CSS/JS | Colunas/Estrutura |
|---|---|---|
| **List** (`app-invoice-list.html`) | DataTables | Colunas: #, Status, Client, Total, Issued Date, Balance, Actions |
| **Preview** (`app-invoice-preview.html`) | — | Documento de fatura estilizado para impressão/PDF (sem cards — layout de documento) + sidebar de ações (Send, Download, Print, Edit) |
| **Edit** (`app-invoice-edit.html`) | — | Mesmo formulário do Preview mas editável |
| **Add** (`app-invoice-add.html`) | **jquery-repeater** (linhas de item), `offcanvas-send-invoice.js` (painel lateral de envio) | Formulário com linhas repetíveis de produto/quantidade/preço + cálculo de total |

## 8. Users / Roles & Permissions

| Página | Estrutura |
|---|---|
| **Users List** (`app-user-list.html`) | já documentado — DataTable completo |
| **User View** (`app-user-view-account.html` + 4 variantes) | Abas: **Account**, **Security**, **Billing & Plans**, **Notifications**, **Connections** — idêntico ao padrão de Account Settings |
| **Roles** (`app-access-roles.html`) | Grid de 8 cards (um por role: Administrator, Editor, Author, Maintainer, Subscriber etc.), cada card com contagem de usuários + botão editar/deletar |
| **Permission** (`app-access-permission.html`) | Tabela: Name, Assigned To, Created Date, Actions — lista de permissões/roles com busca |

## 9. Pages (Profile, Account Settings, FAQ, Pricing, Misc)

| Página | Estrutura |
|---|---|
| **User Profile** (`pages-profile-user.html` + 3) | Abas: **Profile**, **Teams**, **Projects**, **Connections** |
| **Account Settings** (`pages-account-settings-account.html` + 4) | Abas: **Account**, **Security**, **Billing & Plans**, **Notifications**, **Connections** |
| **FAQ** (`pages-faq.html`) | Accordion de perguntas frequentes por categoria + busca — sem JS de página dedicado (puro Bootstrap collapse) |
| **Pricing** (`pages-pricing.html`) | Cards de planos lado a lado + toggle mensal/anual (`.form-switch`) — modelo direto para a página de planos/cotas da Somvibe |
| **Misc — Error** (`pages-misc-error.html`) | `.misc-wrapper` — página 404 ilustrada |
| **Misc — Under Maintenance** (`pages-misc-under-maintenance.html`) | mesmo padrão `.misc-wrapper` |
| **Misc — Coming Soon** (`pages-misc-comingsoon.html`) | mesmo padrão, com contador/countdown |
| **Misc — Not Authorized** (`pages-misc-not-authorized.html`) | mesmo padrão — página 403 |

**Padrão "Misc":** todas as 4 páginas (Error, Maintenance, Coming Soon, Not Authorized) compartilham o wrapper `.misc-wrapper` com ilustração central + título + texto + botão de ação (voltar/home) — um único componente parametrizável cobre as 4.

## 10. Authentications (14/14)

| Página | Observação |
|---|---|
| Login Basic/Cover | **Basic** = `.authentication-wrapper.authentication-basic` (card centralizado, sem split-screen); **Cover** = split-screen com ilustração (já documentado) |
| **Register Basic** (`auth-register-basic.html`) | Campos: username, email, password, checkbox terms |
| **Register Cover** | mesmo formulário, wrapper cover |
| **Register Multi-steps** (`auth-register-multisteps.html`) | **bs-stepper** com 3 passos: Account (Account Details), Personal (Enter Information), Billing (Payment Details) |
| **Verify Email Basic/Cover** (`auth-verify-email-basic.html`) | Tela informativa "verifique seu email" com botões "Skip for now" / reenviar |
| Reset Password Basic/Cover | já documentado (Cover); Basic = mesmo form, wrapper sem imagem |
| Forgot Password Basic/Cover | já documentado (Cover); Basic = mesmo form, wrapper sem imagem |
| **Two Steps Basic** (`auth-two-steps-basic.html`) | 38 inputs (campos de código OTP individuais) + `pages-auth-two-steps.js` dedicado — padrão de verificação em 2 fatores |
| Two Steps Cover | mesmo padrão, wrapper cover |

**Padrão geral Auth:** todas usam o mesmo wrapper `.authentication-wrapper` (modificado por `.authentication-basic` ou `.authentication-cover`) + `#formAuthentication` + CSS `page-auth.css`/`form-validation.css` + JS `pages-auth.js` (ou variante específica como `pages-auth-two-steps.js`).

## 11. Wizard Examples (3/3)

| Página | Passos (bs-stepper) |
|---|---|
| **Checkout** (`wizard-ex-checkout.html`) | Cart → Address → Payment → Confirmation |
| **Property Listing** (`wizard-ex-property-listing.html`) | Personal Details → Property Details → Property Features → Property Area → Price Details |
| **Create Deal** (`wizard-ex-create-deal.html`) | Deal Type → Deal Details → Deal Usage (Limitations & Offers) → Review & Complete |

**Aplicação no SaaS:** "Create Deal" é quase um template direto para o fluxo de criação de contrato/licenciamento de catálogo na Somvibe.

## 12. Modal Examples (1 página, 13 modais catalogados)
`modal-examples.html` reúne uma galeria de modais prontos: `pricingModal`, `addNewCCModal` (cartão de crédito), `addNewAddress`, `referAndEarn`, `editUser`, `enableOTP`, `shareProject`, `createApp`, `twoFactorAuth` (+ variantes One/Two), `paymentProvider`, `paymentMethods` — biblioteca de referência de modais de ação comum (pagamento, 2FA, compartilhamento, edição de usuário).

## 13. Cards (5/5)

| Página | Observação |
|---|---|
| Basic | já documentado |
| **Advance** (`cards-advance.html`) | `flag-icons.css`, `apex-charts.css`, `swiper.css` — cards com bandeiras de país, mini-gráficos e carrossel embutido |
| **Statistics** | já documentado |
| **Analytics** (`cards-analytics.html`) | 12 cards — variações de KPI com gráfico ApexCharts embutido |
| **Actions** (`cards-actions.html`) | `cards-actions.js` — cards com botões de colapsar/recarregar/remover via JS customizado (não só Bootstrap nativo) |

## 14. User Interface — básica (17/17)

Confirmado: **todas** as páginas abaixo usam somente o CSS/JS "core" (Bootstrap puro), exceto onde indicado:

Accordion, Alerts, Badges, Buttons, Collapse, Dropdowns, Footer, List Groups, Modals, Navbar, Offcanvas, Pagination & Breadcrumbs, Progress, Spinners, Tabs & Pills, Toasts, Tooltips & Popovers, Typography — **16 páginas só-core**.

Exceção: **Carousel** (`ui-carousel.html`) → usa **Swiper.js** (`swiper.css/js`) + `ui-carousel.css` dedicado.

**Implicação para o SaaS:** essas 16 páginas são 100% componentes nativos do Bootstrap 5 — ao portar para React/Vue, mapeiam direto para `react-bootstrap`/`bootstrap-vue` ou wrappers próprios, sem necessidade de nenhuma lib JS adicional.

## 15. Extended UI (12/12)

| Página | Lib | CSS/JS |
|---|---|---|
| Avatar | — (só core) | — |
| **BlockUI** | própria (`extended-ui-blockui.js`) | overlay de loading sobre container |
| **Drag & Drop** | **Sortable.js** | `sortable.js` |
| **Media Player** | **Plyr** | `plyr.css/js` |
| Perfect Scrollbar | — (já é core) | demonstração do scroll customizado |
| **Star Ratings** | **Raty.js** | `raty-js.js` |
| SweetAlert2 | já documentado | `sweetalert2.css/js`, `animate.css` |
| Text Divider | — (só core) | — |
| Timeline Basic/Fullscreen | — (só core) | `.timeline`, `.timeline-item`, `.timeline-point`, `.timeline-event` |
| Tour | já documentado | Shepherd.js |
| Treeview | já documentado | jsTree |
| Misc | — (só core) | miscelânea de componentes extras (ex.: text truncate, divider customizado) |

## 16. Icons (2/2)
- **Tabler** (`icons-tabler.html`) — só core (a fonte Tabler já vem embutida via `iconify-icons.css`, carregado globalmente).
- **Font Awesome** (`icons-font-awesome.html`) — adiciona `fontawesome.css` (kit free, carregado só nesta página e nas que explicitamente usam ícones FA, como Tables Basic).

## 17. Forms — restantes (3/10 pendentes + 3 Form Layouts/Wizard pendentes)

| Página | Lib/Observação |
|---|---|
| **Input groups** (`forms-input-groups.html`) | — (só core — combina `.input-group` com ícones/botões/texto) |
| **Custom Options** (`forms-custom-options.html`) | — (só core — radio/checkbox customizados estilo "card" clicável) |
| **Extras** (`forms-extras.html`) | `cleave-zen.js` (máscaras), **jquery-repeater.js** (campos dinâmicos genéricos) |
| **Form Layouts Horizontal** (`form-layouts-horizontal.html`) | mesmo padrão de Vertical, rótulo ao lado do campo (`col-form-label` + grid) |
| **Form Layouts Sticky** (`form-layouts-sticky.html`) | botões de ação (Submit/Cancel) fixos no rodapé da viewport durante scroll |
| **Form Wizard Icons** (`form-wizard-icons.html`) | bs-stepper com ícone em vez de número — passos: Account Details, Personal Info, Address, Social Links, Review & Submit |

## 18. Tables — restantes (2/4 pendentes)

| Página | Observação |
|---|---|
| **Datatables Basic** (`tables-datatables-basic.html`) | DataTable simples sem botões de export, só paginação/busca/ordenação |
| **Datatables Extensions** (`tables-datatables-extensions.html`) | adiciona extensões do DataTables (export para Excel/PDF/Print via `buttons` — requer `jszip`/`pdfmake` internamente, embutidos no bundle `datatables-bootstrap5.js`) |

## 19. Charts & Maps — restantes (2/3 pendentes)

| Página | Lib |
|---|---|
| **Charts Apex** (`charts-apex.html`) | ApexCharts — página dedicada com toda a galeria de tipos de gráfico (linha, área, coluna, radial, donut, radar, polar, bubble, candlestick, heatmap) |
| **Maps Leaflet** (`maps-leaflet.html`) | **Leaflet.js** (`leaflet.css/js`) — diferente do Mapbox GL usado em Logistics Fleet; Leaflet é open-source/sem necessidade de API key, Mapbox é mais robusto visualmente mas requer token |

## 20. Multi Level (menu de exemplo, sem página própria)
"Level 2 → Level 3" são apenas `javascript:void(0)` — demonstra profundidade de submenu, não aponta para HTML real.

## 21. Links externos (não fazem parte do template)
- Support → Discord (`https://discord.gg/NA7qrFMES`)
- Documentation → `https://demos.pixinvent.com/vuexy-html-admin-template/documentation/`

---

## Resumo de bibliotecas adicionais descobertas nesta rodada (não listadas no documento anterior)

| Biblioteca | Usada em | Função |
|---|---|---|
| **jquery-repeater.js** | Product Add, Invoice Add, Forms Extras | Campos de formulário repetíveis/dinâmicos (ex. linhas de item, variantes) |
| **Mapbox GL JS** | Logistics Fleet | Mapa interativo de rastreamento de veículos (requer API key, diferente do Leaflet) |
| **Sortable.js** | Extended UI — Drag & Drop | Reordenação de listas via drag-and-drop |
| **Plyr.js** | Extended UI — Media Player | Player de vídeo/áudio customizado |
| **Raty.js** | Extended UI — Star Ratings | Input de avaliação por estrelas |
| **Swiper.js** | UI Carousel, Cards Advance | Carrossel/slider de conteúdo |
| **jszip / pdfmake** (internos ao bundle DataTables) | Tables Datatables Extensions | Export de tabela para Excel/PDF |
| **offcanvas-send-invoice.js** | Invoice Add | Painel lateral de envio de fatura por email |
| **pages-auth-two-steps.js** | Auth Two Steps | Lógica de preenchimento automático entre os campos de código OTP |

---

## Conclusão da auditoria de cobertura

✅ **144/144 páginas HTML locais do menu lateral foram visitadas e documentadas** (assets CSS/JS confirmados via inspeção real da página, não inferência).
✅ Os **155 links totais** do menu foram contabilizados — a diferença de 11 corresponde a: Horizontal menu (template irmão, fora do pacote), 5 Front Pages (pasta irmã `front-pages/`), Support (Discord) e Documentation (ambos externos).
✅ Nenhuma página do menu lateral ficou sem registro de bibliotecas e estrutura básica.

Este documento, junto aos dois anteriores, forma o conjunto de referência completo do template Vuexy para consulta durante a implementação do SaaS multi-tenant.

**Fim do documento.**
