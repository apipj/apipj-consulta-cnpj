/* End-to-end scenarios. Run inside the Playwright container: node run.cjs <scenario> */
const { chromium } = require( '/opt/pw/node_modules/playwright' );
const assert = require( 'assert' );

const BASE = process.env.WP_BASE || 'http://wordpress';
const PRODUCT = process.env.PRODUCT_ID || '11';
const OUT = process.env.OUT || 'out';
const SHOTS = '/assets';

const FOUND = /Empresa encontrada|Company found/;
const NOT_FOUND = /não encontrado|not found/i;
const INVALID = /inválido|Invalid CNPJ/i;

function log( ...a ) {
	console.log( '[e2e]', ...a );
}

async function newPage( browser, opts = {} ) {
	const context = await browser.newContext( { viewport: { width: 1280, height: 1100 }, locale: 'pt-BR', ...opts } );
	const page = await context.newPage();
	page.lookups = [];
	page.on( 'request', ( r ) => {
		if ( r.url().includes( '/apipj/v1/cnpj/' ) ) {
			page.lookups.push( r.url().split( '/apipj/v1/cnpj/' )[ 1 ] );
		}
	} );
	page.on( 'pageerror', ( e ) => log( 'PAGE ERROR', e.message ) );
	page.on( 'console', ( m ) => {
		if ( m.type() === 'error' ) log( 'console error:', m.text() );
	} );
	return page;
}

async function status( page, input ) {
	return page.locator( input ).evaluate( ( el ) => ( el.apipjStatus ? el.apipjStatus.textContent : '' ) );
}

async function waitStatus( page, input, re ) {
	await page.waitForFunction(
		( [ sel, src ] ) => {
			const el = document.querySelector( sel );
			return el && el.apipjStatus && new RegExp( src, 'i' ).test( el.apipjStatus.textContent );
		},
		[ input, re.source ],
		{ timeout: 15000 }
	);
	return status( page, input );
}

async function values( page, ids ) {
	const out = {};
	for ( const id of ids ) {
		out[ id ] = await page.locator( '#' + id ).inputValue().catch( () => null );
	}
	return out;
}

async function typeCnpj( page, sel, value ) {
	await page.locator( sel ).click();
	await page.waitForTimeout( 400 );
	await page.locator( sel ).fill( '' );
	await page.locator( sel ).pressSequentially( value, { delay: 90 } );
}

const scenarios = {
	// (1) + (3) + (4) classic checkout with Brazilian Market on WooCommerce.
	async classic( browser ) {
		const page = await newPage( browser );
		await page.goto( `${ BASE }/?add-to-cart=${ PRODUCT }` );
		await page.goto( `${ BASE }/finalizar-classico/` );
		if ( await page.locator( '#billing_persontype' ).count() ) {
			await page.selectOption( '#billing_persontype', '2' );
		}
		await page.fill( '#billing_first_name', 'Maria' );
		await page.fill( '#billing_last_name', 'Silva' );

		// (3) invalid check digit: no request, message.
		await typeCnpj( page, '#billing_cnpj', '00000000000192' );
		const invalid = await waitStatus( page, '#billing_cnpj', INVALID );
		await page.waitForTimeout( 800 );
		assert.strictEqual( page.lookups.length, 0, 'invalid DV must not call the API' );
		log( 'classic invalid DV ->', JSON.stringify( invalid ), 'requests:', page.lookups.length );

		// (4) not found.
		await typeCnpj( page, '#billing_cnpj', '99999998999918' );
		const nf = await waitStatus( page, '#billing_cnpj', NOT_FOUND );
		log( 'classic not found ->', JSON.stringify( nf ) );

		if ( process.env.NO_BMW ) {
			// Alphanumeric CNPJ (valid check digits): goes to the API.
			await typeCnpj( page, '#billing_cnpj', '12.abc.345/01de-35' );
			const alnum = await waitStatus( page, '#billing_cnpj', /não encontrado|not found|encontrada|found/i );
			log( 'classic alphanumeric 12.ABC.345/01DE-35 ->', JSON.stringify( alnum ) );
		}

		// (1) Banco do Brasil.
		await typeCnpj( page, '#billing_cnpj', '00000000000191' );
		const st = await waitStatus( page, '#billing_cnpj', FOUND );
		await page.waitForTimeout( 1500 );
		const v = await values( page, [ 'billing_cnpj', 'billing_company', 'billing_address_1', 'billing_number', 'billing_address_2', 'billing_neighborhood', 'billing_city', 'billing_state', 'billing_postcode', 'billing_country' ] );
		log( 'classic BB ->', JSON.stringify( st ), JSON.stringify( v ) );
		assert.strictEqual( v.billing_company, 'BANCO DO BRASIL SA' );
		assert.strictEqual( v.billing_city, 'BRASILIA' );
		assert.strictEqual( v.billing_state, 'DF' );
		assert.strictEqual( v.billing_postcode, '70040-912' );
		if ( process.env.NO_BMW ) {
			assert.strictEqual( v.billing_address_1, 'QUADRA SAUN QUADRA 5 BLOCO B TORRE I, II, III, SN' );
			assert.ok( v.billing_address_2.endsWith( ' - ASA NORTE' ) );
		} else {
			assert.strictEqual( v.billing_number, 'SN' );
			assert.strictEqual( v.billing_neighborhood, 'ASA NORTE' );
		}
		log( 'classic lookups sent:', JSON.stringify( page.lookups ) );
		await page.locator( '#billing_cnpj' ).scrollIntoViewIfNeeded();
		await page.screenshot( { fullPage: true, path: process.env.NO_BMW ? `${ OUT }/classic-own-field.png` : `${ SHOTS }/screenshot-1.png`, clip: await clipOf( page, '.woocommerce-billing-fields', 1700 ) } );

		// Place the order (classic) to check the CNPJ is stored.
		await page.fill( '#billing_phone', '(61) 99999-0000' );
		await page.fill( '#billing_email', 'compras@example.com' );
		await page.locator( '#place_order' ).click();
		await page.waitForURL( /order-received/, { timeout: 30000 } );
		log( 'classic order placed:', page.url().replace( BASE, '' ) );
		await page.context().close();
	},

	// (2) block-based Checkout.
	async blocks( browser ) {
		const page = await newPage( browser );
		await page.goto( `${ BASE }/?add-to-cart=${ PRODUCT }` );
		await page.goto( `${ BASE }/checkout/` );
		await page.waitForSelector( '#contact-apipj-cnpj' );
		await page.fill( '#email', 'compras@example.com' );
		await page.fill( '#shipping-first_name', 'Maria' );
		await page.fill( '#shipping-last_name', 'Silva' );

		await typeCnpj( page, '#contact-apipj-cnpj', '00000000000192' );
		const invalid = await waitStatus( page, '#contact-apipj-cnpj', INVALID );
		await page.waitForTimeout( 800 );
		assert.strictEqual( page.lookups.length, 0, 'invalid DV must not call the API' );
		log( 'blocks invalid DV ->', JSON.stringify( invalid ), 'requests:', page.lookups.length );

		await typeCnpj( page, '#contact-apipj-cnpj', '00.000.000/0001-91' );
		const st = await waitStatus( page, '#contact-apipj-cnpj', FOUND );
		await page.waitForTimeout( 2500 );
		const v = await values( page, [ 'shipping-company', 'shipping-address_1', 'shipping-address_2', 'shipping-city', 'shipping-state', 'shipping-postcode', 'shipping-country' ] );
		const store = await page.evaluate( () => {
			const c = wp.data.select( 'wc/store/cart' ).getCustomerData();
			return { billing: c.billingAddress, shipping: c.shippingAddress };
		} );
		log( 'blocks BB ->', JSON.stringify( st ), JSON.stringify( v ) );
		log( 'blocks store billing ->', JSON.stringify( store.billing ) );
		assert.strictEqual( store.shipping.company, 'BANCO DO BRASIL SA' );
		assert.strictEqual( store.shipping.city, 'BRASILIA' );
		assert.strictEqual( store.shipping.state, 'DF' );
		assert.strictEqual( store.shipping.postcode, '70040-912' );
		assert.strictEqual( v[ 'shipping-city' ], 'BRASILIA' );
		await page.waitForSelector( 'input[id^="radio-control-0-flat_rate"]', { timeout: 15000 } ).catch( () => {} );
		await page.waitForTimeout( 1000 );
		await page.screenshot( { fullPage: true, path: `${ SHOTS }/screenshot-2.png`, clip: await clipOf( page, '.wc-block-checkout__main' , 1250 ) } );

		await page.locator( '.wc-block-components-checkout-place-order-button' ).click();
		await page.waitForURL( /order-received/, { timeout: 30000 } );
		log( 'blocks order placed:', page.url().replace( BASE, '' ) );
		await page.context().close();
	},

	// (5) inactive company + "block checkout" (mocked BAIXADA response), classic and blocks.
	async inactive( browser ) {
		let page = await newPage( browser );
		await page.goto( `${ BASE }/?add-to-cart=${ PRODUCT }` );
		await page.goto( `${ BASE }/finalizar-classico/` );
		if ( await page.locator( '#billing_persontype' ).count() ) {
			await page.selectOption( '#billing_persontype', '2' );
		}
		await page.fill( '#billing_first_name', 'Maria' );
		await page.fill( '#billing_last_name', 'Silva' );
		await typeCnpj( page, '#billing_cnpj', '11222333000181' );
		const st = await waitStatus( page, '#billing_cnpj', /BAIXADA/ );
		log( 'classic inactive status ->', JSON.stringify( st ) );
		await page.waitForTimeout( 1500 );
		await page.fill( '#billing_phone', '(11) 99999-0000' );
		await page.fill( '#billing_email', 'compras@example.com' );
		await page.locator( '#place_order' ).click();
		const err = page.locator( '.woocommerce-error, .woocommerce-NoticeGroup-checkout' );
		await err.first().waitFor( { timeout: 20000 } );
		const text = ( await err.first().innerText() ).replace( /\s+/g, ' ' );
		log( 'classic place order ->', JSON.stringify( text ) );
		assert.match( text, /BAIXADA/ );
		assert.ok( ! page.url().includes( 'order-received' ) );
		await page.context().close();

		page = await newPage( browser );
		await page.goto( `${ BASE }/?add-to-cart=${ PRODUCT }` );
		await page.goto( `${ BASE }/checkout/` );
		await page.waitForSelector( '#contact-apipj-cnpj' );
		await page.fill( '#email', 'compras@example.com' );
		await page.fill( '#shipping-first_name', 'Maria' );
		await page.fill( '#shipping-last_name', 'Silva' );
		await typeCnpj( page, '#contact-apipj-cnpj', '11.222.333/0001-81' );
		const bst = await waitStatus( page, '#contact-apipj-cnpj', /BAIXADA/ );
		log( 'blocks inactive status ->', JSON.stringify( bst ) );
		await page.waitForTimeout( 2500 );
		await page.locator( '.wc-block-components-checkout-place-order-button' ).click();
		await page.waitForTimeout( 5000 );
		const notices = ( await page.locator( '.wc-block-components-notice-banner, .wc-block-components-validation-error' ).allInnerTexts() ).join( ' | ' ).replace( /\s+/g, ' ' );
		log( 'blocks place order ->', JSON.stringify( notices ), 'url:', page.url().replace( BASE, '' ) );
		assert.match( notices, /BAIXADA/ );
		assert.ok( ! page.url().includes( 'order-received' ) );
		await page.screenshot( { path: `${ OUT }/blocks-inactive.png`, fullPage: true } );
		await page.context().close();
	},

	// (6) generic form with data attributes (no WooCommerce code involved).
	async forms( browser ) {
		const page = await newPage( browser );
		await page.goto( `${ BASE }/cadastro-empresa/` );
		await typeCnpj( page, '[data-apipj-cnpj]', '00000000000191' );
		const st = await waitStatus( page, '[data-apipj-cnpj]', FOUND );
		await page.waitForTimeout( 500 );
		const v = await page.$$eval( '[data-apipj-campo], .apipj-campo-email input', ( els ) => els.map( ( e ) => `${ e.getAttribute( 'data-apipj-campo' ) || e.name }=${ e.value }` ) );
		log( 'forms ->', JSON.stringify( st ), JSON.stringify( v ) );
		assert.ok( v.includes( 'nome=BANCO DO BRASIL SA' ) );
		assert.ok( v.includes( 'uf=DF' ) );
		assert.ok( v.includes( 'cep=70.040-912' ) );
		await page.screenshot( { fullPage: true, path: `${ SHOTS }/screenshot-4.png`, clip: await clipOf( page, 'form.apipj-demo' ) } );
		await page.context().close();
	},

	// (7) per-IP rate limit (limit set to 2/min by the runner, cache already warm).
	async ratelimit( browser ) {
		const page = await newPage( browser );
		await page.goto( `${ BASE }/cadastro-empresa/` );
		const codes = await page.evaluate( async () => {
			const out = [];
			for ( const c of [ '00000000000191', '99999998999918', '00000000000191' ] ) {
				const r = await fetch( apipjConfig.restUrl + c, { headers: { 'X-Apipj-Token': apipjConfig.token } } );
				out.push( r.status );
			}
			return out;
		} );
		log( 'rate limit statuses ->', JSON.stringify( codes ) );
		assert.deepStrictEqual( codes.slice( 0, 2 ), [ 200, 404 ] );
		assert.strictEqual( codes[ 2 ], 429 );
		await typeCnpj( page, '[data-apipj-cnpj]', '00000000000191' );
		const st = await waitStatus( page, '[data-apipj-cnpj]', /wait a minute|aguarde/i );
		log( 'rate limit message ->', JSON.stringify( st ) );
		await page.context().close();
	},

	// quota_exceeded (mocked): shopper sees nothing, owner gets an admin notice.
	async quota( browser ) {
		const page = await newPage( browser );
		await page.goto( `${ BASE }/cadastro-empresa/` );
		await typeCnpj( page, '[data-apipj-cnpj]', '11444777000161' );
		await page.waitForTimeout( 3000 );
		const st = await status( page, '[data-apipj-cnpj]' );
		log( 'quota shopper status ->', JSON.stringify( st ) );
		assert.strictEqual( st, '' );
		await page.context().close();
	},

	// My Account > Addresses > Billing (classic form, logged-in customer).
	async account( browser ) {
		const page = await newPage( browser );
		await login( page );
		await page.goto( `${ BASE }/my-account/edit-address/billing/` );
		if ( await page.locator( '#billing_persontype' ).count() ) {
			await page.selectOption( '#billing_persontype', '2' );
		}
		await typeCnpj( page, '#billing_cnpj', '00000000000191' );
		const st = await waitStatus( page, '#billing_cnpj', FOUND );
		await page.waitForTimeout( 800 );
		const v = await values( page, [ 'billing_company', 'billing_address_1', 'billing_number', 'billing_neighborhood', 'billing_city', 'billing_state', 'billing_postcode' ] );
		log( 'my account ->', JSON.stringify( st ), JSON.stringify( v ) );
		assert.strictEqual( v.billing_company, 'BANCO DO BRASIL SA' );
		assert.strictEqual( v.billing_state, 'DF' );
		await page.context().close();
	},

	// Settings > apipj without WooCommerce: save through the standalone form.
	async standalone( browser ) {
		const page = await newPage( browser );
		await login( page );
		await page.goto( `${ BASE }/wp-admin/options-general.php?page=apipj` );
		await page.fill( '#apipj_cache_days', '15' );
		await page.check( 'input[name="apipj[fill][]"][value="phone"]' );
		await page.click( '#submit' );
		await page.waitForURL( /updated=1/ );
		const notice = await page.locator( '.notice-success' ).first().innerText();
		const cache = await page.locator( '#apipj_cache_days' ).inputValue();
		const phone = await page.locator( 'input[name="apipj[fill][]"][value="phone"]' ).isChecked();
		const masked = await page.locator( '.apipj-key-current' ).innerText();
		log( 'standalone save ->', JSON.stringify( { notice, cache, phone, masked: masked.replace( /.{4}$/, '****' ) } ) );
		assert.strictEqual( cache, '15' );
		assert.ok( phone );
		await page.context().close();
	},

	// Settings screen (WooCommerce > Settings > apipj): masked key + connection test.
	async settings( browser ) {
		const page = await newPage( browser, { viewport: { width: 1280, height: 1500 } } );
		await login( page );
		await page.goto( `${ BASE }/wp-admin/${ process.env.SETTINGS_PATH || 'admin.php?page=wc-settings&tab=apipj' }` );
		const notice = ( await page.locator( '.notice-warning' ).allInnerTexts() ).join( ' | ' );
		log( 'admin notice ->', JSON.stringify( notice.replace( /\s+/g, ' ' ) ) );
		const keyInputValue = await page.locator( '#apipj_api_key' ).inputValue();
		const html = await page.content();
		assert.strictEqual( keyInputValue, '', 'key input must be empty' );
		assert.ok( ! /apipj_live_[A-Za-z0-9]{12,}/.test( html ), 'full key must not be in the HTML' );
		log( 'masked key shown ->', await page.locator( '.apipj-key-current' ).innerText() );
		if ( process.env.TEST_CONNECTION ) {
			await page.click( '#apipj-test-connection' );
			await page.waitForFunction( () => /apipj-(ok|fail)/.test( document.getElementById( 'apipj-test-result' ).className ), null, { timeout: 20000 } );
			log( 'test connection ->', JSON.stringify( await page.locator( '#apipj-test-result' ).innerText() ) );
		}
		await page.screenshot( { fullPage: true, path: process.env.SETTINGS_SHOT || `${ SHOTS }/screenshot-3.png`, clip: await clipOf( page, process.env.SETTINGS_SEL || '#mainform', 1400 ) } );
		await page.context().close();
	},
};

// Local test site credentials (throwaway Docker install created by dev/setup).
async function login( page ) {
	await page.goto( `${ BASE }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

async function clipOf( page, sel, maxH = 1100 ) {
	await page.evaluate( () => window.scrollTo( 0, 0 ) );
	const box = await page.locator( sel ).first().boundingBox();
	const pad = 16;
	const x = Math.max( 0, box.x - pad );
	const y = Math.max( 0, box.y - pad );
	return { x, y, width: Math.min( 1280 - x, box.width + pad * 2 ), height: Math.min( maxH, box.height + pad * 2 ) };
}

( async () => {
	const name = process.argv[ 2 ];
	const browser = await chromium.launch();
	try {
		await scenarios[ name ]( browser );
		log( `PASS ${ name }` );
	} catch ( e ) {
		log( `FAIL ${ name }:`, e.message );
		process.exitCode = 1;
	} finally {
		await browser.close();
	}
} )();
