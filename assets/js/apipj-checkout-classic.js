/**
 * apipj – Consulta CNPJ: classic (shortcode) checkout and My Account > Addresses.
 *
 * Works with the fields added by this plugin or by "Brazilian Market on WooCommerce"
 * (billing_cnpj, billing_number, billing_neighborhood).
 */
( function ( window, document, $ ) {
	'use strict';

	var api = window.apipjCnpj;
	if ( ! api ) {
		return;
	}

	function field( name ) {
		return document.getElementById( 'billing_' + name );
	}

	function fire( el ) {
		el.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		el.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
	}

	function setValue( name, value ) {
		var el = field( name );
		if ( ! el ) {
			return;
		}
		if ( el.tagName === 'SELECT' ) {
			var exists = Array.prototype.some.call( el.options, function ( o ) {
				return o.value === value;
			} );
			if ( ! exists ) {
				return;
			}
		}
		el.value = value;
		fire( el );
		if ( $ && el.tagName === 'SELECT' ) {
			$( el ).trigger( 'change.select2' );
		}
	}

	function fill( company ) {
		var values = api.addressValues( company, {
			hasNumber: !! field( 'number' ),
			hasNeighborhood: !! field( 'neighborhood' ),
		} );
		var current = {};
		Object.keys( values ).forEach( function ( key ) {
			var el = field( key );
			if ( el ) {
				current[ key ] = el.value;
			}
		} );
		var todo = api.plan( values, current );
		// Country first: WooCommerce rebuilds the state field when the country changes.
		if ( todo.country ) {
			setValue( 'country', todo.country );
		}
		[ 'company', 'postcode', 'address_1', 'number', 'address_2', 'neighborhood', 'city', 'state', 'phone' ].forEach( function ( key ) {
			if ( todo[ key ] !== undefined && field( key ) ) {
				setValue( key, todo[ key ] );
			}
		} );
	}

	function init() {
		var input = field( 'cnpj' );
		if ( input ) {
			api.watch( input, fill );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
	// The classic checkout re-renders parts of the form; make sure the field is watched.
	if ( $ ) {
		$( document.body ).on( 'updated_checkout', init );
	}
} )( window, document, window.jQuery );
