/**
 * apipj – Consulta CNPJ: block-based Checkout.
 *
 * The CNPJ field is registered with the Additional Checkout Fields API (id "apipj/cnpj",
 * contact location). Address values are set through the cart data store
 * (wp.data 'wc/store/cart' setBillingAddress / setShippingAddress), so the Checkout
 * block re-renders and syncs them with the Store API by itself.
 */
( function ( window, document ) {
	'use strict';

	var api = window.apipjCnpj;
	var wpData = window.wp && window.wp.data;
	if ( ! api || ! wpData ) {
		return;
	}

	var SELECTOR = 'input[id$="apipj-cnpj"], input[id$="apipj/cnpj"], input[name="apipj/cnpj"]';

	function patchFor( current, values ) {
		return api.plan( values, current || {} );
	}

	function fill( company ) {
		var cartSelect = wpData.select( 'wc/store/cart' );
		var cartDispatch = wpData.dispatch( 'wc/store/cart' );
		if ( ! cartSelect || ! cartDispatch || ! cartSelect.getCustomerData ) {
			return;
		}
		var checkoutSelect = wpData.select( 'wc/store/checkout' );
		var customer = cartSelect.getCustomerData() || {};
		var needsShipping = typeof cartSelect.getNeedsShipping === 'function' ? cartSelect.getNeedsShipping() : true;
		var sameAddress =
			checkoutSelect && typeof checkoutSelect.getUseShippingAsBilling === 'function'
				? checkoutSelect.getUseShippingAsBilling()
				: true;
		var values = api.addressValues( company, { hasNumber: false, hasNeighborhood: false } );

		var billing = patchFor( customer.billingAddress, values );
		if ( Object.keys( billing ).length ) {
			cartDispatch.setBillingAddress( billing );
		}
		if ( needsShipping && sameAddress ) {
			var shipping = patchFor( customer.shippingAddress, values );
			if ( Object.keys( shipping ).length ) {
				cartDispatch.setShippingAddress( shipping );
			}
		}
	}

	function anchor( input ) {
		return input.closest( '.wc-block-components-text-input' ) || input;
	}

	function scan() {
		var inputs = document.querySelectorAll( SELECTOR );
		Array.prototype.forEach.call( inputs, function ( input ) {
			api.watch( input, fill, { anchor: anchor } );
		} );
	}

	function start() {
		scan();
		if ( window.MutationObserver ) {
			new window.MutationObserver( api.debounce( scan, 200 ) ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )( window, document );
