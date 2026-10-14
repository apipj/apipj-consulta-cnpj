=== apipj – Consulta CNPJ for WooCommerce ===
Contributors: brunogalizzi
Tags: cnpj, woocommerce, checkout, receita federal, brazil
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

CNPJ autofill for WooCommerce checkout (classic and blocks) and any form: company name and address from Receita Federal data via the apipj API.

== Description ==

Brazilian B2B customers hate typing their company address. With this plugin they type the **CNPJ** and the checkout fills itself: company name (razão social), street, number, address line 2 (complemento), neighborhood (bairro), city, state and postcode (CEP), straight from the Receita Federal open data served by the [apipj CNPJ API](https://apipj.com.br/).

**Features**

* **Classic checkout** (shortcode) and the **block-based Checkout** (the default since WooCommerce 8.3), using the official Additional Checkout Fields API and the Checkout data store.
* **My Account > Addresses** (billing address form).
* Integrates with **Brazilian Market on WooCommerce** (Extra Checkout Fields for Brazil): uses its CNPJ, number and neighborhood fields when present. Without it, the plugin adds its own CNPJ field.
* **Any form, no WooCommerce needed**: Contact Form 7, Elementor, page builders or plain HTML. Mark the inputs with data attributes or CSS classes (see FAQ).
* **Alphanumeric CNPJ** ready (IN RFB 2.229/2024): validated locally, check digits included, before any API call.
* Inline, non-blocking status next to the field: "Searching…", "Company found", "CNPJ not found", "Status: BAIXADA".
* **Inactive companies**: do nothing, warn the customer, or block the checkout when the registration status (situação cadastral) is not ATIVA.
* Choose which fields to fill and whether to keep what the customer already typed (default) or always replace it.
* **Protects your quota**: server-side proxy (the API key never reaches the browser), per-IP rate limit, response cache (30 days by default) and local check digit validation.
* Compatible with HPOS (High-Performance Order Storage).
* Translated to Brazilian Portuguese out of the box.

**You need an apipj API key.** The free plan includes 10 lookups per minute and 50,000 per month, no credit card: [create your account](https://apipj.com.br/criar-conta). Paid plans with higher limits are listed at [apipj.com.br/precos](https://apipj.com.br/precos).

= Em português =

Seu cliente digita o **CNPJ** e o checkout se preenche sozinho: razão social, logradouro, número, complemento, bairro, cidade, estado e CEP, com os dados abertos da Receita Federal servidos pela [API de CNPJ do apipj](https://apipj.com.br/).

* Funciona no **checkout clássico** e no **checkout em blocos** do WooCommerce, e em **Minha conta > Endereços**.
* Integra com o **Brazilian Market on WooCommerce** (campos CNPJ, número e bairro). Sem ele, o plugin cria o próprio campo de CNPJ.
* Funciona em **qualquer formulário** (Contact Form 7, Elementor, HTML), mesmo sem WooCommerce.
* Aceita o **CNPJ alfanumérico** e valida os dígitos verificadores antes de consultar.
* Mostra a situação cadastral e, se você quiser, **bloqueia pedidos de CNPJ não ATIVO** (BAIXADA, INAPTA, SUSPENSA…).
* Protege a sua cota: a chave fica no servidor, há limite por IP e cache das consultas.
* Interface em português do Brasil.

Para usar, crie uma conta grátis em [apipj.com.br/criar-conta](https://apipj.com.br/criar-conta) (10 consultas por minuto e 50.000 por mês, sem cartão), cole a chave em **WooCommerce > Configurações > apipj** (ou **Configurações > apipj** sem WooCommerce) e clique em **Testar conexão**.

== External services ==

This plugin connects to the **apipj CNPJ API** (https://apipj.com.br), operated by apipj, to look up the public registration data of Brazilian companies (Receita Federal open data). It is required for the plugin to work.

* **What is sent:** only the CNPJ being looked up, plus your API key in the Authorization header. No customer name, e-mail, address, IP address or any other personal data is sent. The requests are made by your server, never by the visitor's browser.
* **When:** when a customer or visitor types a complete CNPJ with valid check digits in a field handled by the plugin and the result is not in the local cache yet; when an order is validated with the "block checkout for inactive companies" option enabled and the result is not cached; and when an administrator clicks "Test connection" on the settings screen (which also calls the open status endpoint, https://apipj.com.br/v1/status).
* **Endpoints:** `https://apipj.com.br/v1/cnpj/{cnpj}` and `https://apipj.com.br/v1/status`.
* Terms of use: https://apipj.com.br/termos
* Privacy policy: https://apipj.com.br/privacidade

The plugin does not track users, does not load external assets (scripts, styles or fonts) and does not phone home.

== Installation ==

1. Install and activate the plugin (Plugins > Add New, search for "apipj").
2. Create a free apipj account at https://apipj.com.br/criar-conta and copy your API key (it starts with `apipj_live_`).
3. Go to **WooCommerce > Settings > apipj** (or **Settings > apipj** when WooCommerce is not active), paste the key, save and click **Test connection**.
4. Choose the fields to fill and what to do with inactive companies.

With **Brazilian Market on WooCommerce**, select "Pessoa Jurídica" at checkout and type the CNPJ. Without it, a CNPJ field appears in the billing address (classic checkout) or in the contact information step (block-based Checkout).

== Frequently Asked Questions ==

= Is my API key safe? =

Yes. The key is stored on your server and is only used by server-side requests to apipj.com.br. It is never printed in the page, sent to the browser or shown again in full on the settings screen (only its last 4 characters). You can also keep it out of the database by adding `define( 'APIPJ_API_KEY', 'apipj_live_...' );` to wp-config.php. If a key leaks, revoke it in your apipj dashboard and create another.

= How many lookups does it use? What are the limits? =

One lookup per new CNPJ. Repeated lookups are served from the cache (30 days by default, configurable), invalid CNPJs are rejected locally without calling the API, and each visitor IP is limited (5 lookups per minute by default). The free apipj plan allows 10 lookups per minute and 50,000 per month; see https://apipj.com.br/precos for higher limits. If your monthly quota runs out, customers simply type their data as usual and you see a notice in the WordPress admin.

= Does it support the new alphanumeric CNPJ? =

Yes. Input is normalized (punctuation removed, letters uppercased) and the check digits are validated with the official algorithm, so `12.ABC.345/01DE-35` works. Note that the CNPJ field of Brazilian Market on WooCommerce applies a numeric mask; for alphanumeric CNPJs use the plugin's own field or the block-based Checkout.

= Does it work with the block-based Checkout? =

Yes. On stores whose checkout page uses the Checkout block, the plugin registers a "CNPJ" field in the contact information step (WooCommerce Additional Checkout Fields API, WooCommerce 8.6 or newer) and fills the shipping/billing address through the Checkout data store. The CNPJ is saved with the order and shown on the order screen.

= Which plugins and versions are supported? =

WordPress 6.3+, PHP 7.4+, WooCommerce 8.0+ (block-based Checkout field: 8.6+), Brazilian Market on WooCommerce 4.x, HPOS. WooCommerce is optional: without it, the generic form integration still works.

= How do I use it with Contact Form 7, Elementor or my own form? =

Tell the plugin which input holds the CNPJ and which inputs, in the same form, should be filled. Any of these markers works:

* attributes: `data-apipj-cnpj` on the CNPJ input and `data-apipj-campo="nome"` (etc.) on the others;
* CSS classes: `apipj-cnpj` and `apipj-campo-nome` (etc.), on the input or on a wrapper element;
* input name or ID: `apipj_cnpj` and `apipj_nome` (etc.).

Available names: `nome` (company name), `fantasia` (trade name), `cep`, `logradouro`, `numero`, `complemento`, `bairro`, `municipio`, `uf`, `telefone`, `email`, `situacao`, `atividade_principal`.

* Plain HTML: `<input name="cnpj" data-apipj-cnpj>` and `<input name="empresa" data-apipj-campo="nome">`.
* Contact Form 7: `[text cnpj class:apipj-cnpj]`, `[text razao class:apipj-campo-nome]`, `[text cep class:apipj-campo-cep]` (or simply name the fields `apipj_cnpj`, `apipj_nome`, `apipj_cep`).
* Elementor forms: set the field ID (Advanced tab) to `apipj_cnpj`, `apipj_nome`, `apipj_cep` and so on.

The script loads automatically on pages whose content contains these markers or the `[apipj_cnpj]` shortcode. For forms in widgets, popups or theme templates, enable "Load the form script on every page" in the settings (or add the shortcode to the page).

= What happens with inactive companies? =

You choose: do nothing, show the registration status (e.g. "Status: BAIXADA") next to the field, or block the checkout. When blocking, orders with a CNPJ whose status is not ATIVA are rejected with a clear message, on the classic and on the block-based Checkout. If the API is unavailable, orders are never blocked.

= Where does the data come from? Is it real time? =

From the Receita Federal open data files (CNPJ database), loaded by apipj once a month. It is not a real-time query to the Receita Federal website.

= I use a CDN or reverse proxy. Is the per-IP limit correct? =

The plugin uses `REMOTE_ADDR`, which cannot be spoofed. Behind a trusted proxy or CDN, use the `apipj_client_ip` filter to return the real visitor IP.

= Developer hooks =

* `apipj_add_cnpj_field` (bool): add the CNPJ field to the classic checkout and My Account when Brazilian Market is not active.
* `apipj_register_block_field` (bool): register the block-based Checkout field.
* `apipj_lookup_response` (array): filter the company data returned to the browser.
* `apipj_client_ip` (string): IP used by the rate limit.
* JavaScript event `apipj:result` dispatched on the CNPJ input after each lookup.

== Screenshots ==

1. Classic checkout with Brazilian Market on WooCommerce: company name, address, number and neighborhood filled from the CNPJ.
2. Block-based Checkout: the CNPJ field in the contact information step fills the address.
3. Settings screen (WooCommerce > Settings > apipj): the API key is never shown in full.
4. Any form: inputs marked with data attributes are filled from the CNPJ.

== Changelog ==

= 1.0.0 =
* First release: classic and block-based Checkout, My Account, Brazilian Market on WooCommerce integration, generic forms, alphanumeric CNPJ, inactive company handling, cache and per-IP rate limit, pt_BR translation.

== Upgrade Notice ==

= 1.0.0 =
First release.
