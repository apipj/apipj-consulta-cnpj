/**
 * apipj – Consulta CNPJ: any form.
 *
 * Mark the CNPJ input with data-apipj-cnpj (or the CSS class "apipj-cnpj") and the inputs
 * to fill, inside the same form, with data-apipj-campo="nome|fantasia|cep|logradouro|numero|
 * complemento|bairro|municipio|uf|telefone|email|situacao|atividade_principal" (or the CSS
 * class "apipj-campo-<name>"). Classes may also be set on a wrapper element: the first
 * input, select or textarea inside it is used. Inputs whose name or id ends with
 * "apipj_cnpj" / "apipj_<name>" also work (e.g. Elementor field IDs, Contact Form 7 names).
 */
( function ( window, document ) {
	'use strict';

	var api = window.apipjCnpj;
	if ( ! api ) {
		return;
	}

	var CAMPOS = [ 'nome', 'fantasia', 'cep', 'logradouro', 'numero', 'complemento', 'bairro', 'municipio', 'uf', 'telefone', 'email', 'situacao', 'atividade_principal', 'cnpj' ];

	function control( el ) {
		if ( ! el ) {
			return null;
		}
		if ( /^(INPUT|SELECT|TEXTAREA)$/.test( el.tagName ) ) {
			return el;
		}
		return el.querySelector( 'input, select, textarea' );
	}

	function scope( input ) {
		return input.form || input.closest( 'form' ) || document;
	}

	function targets( root, campo ) {
		var found = [];
		var key = 'apipj_' + campo;
		var nodes = root.querySelectorAll(
			'[data-apipj-campo="' + campo + '"], .apipj-campo-' + campo + ', [name$="' + key + '"], [name$="' + key + ']"], [id$="' + key + '"]'
		);
		Array.prototype.forEach.call( nodes, function ( node ) {
			var c = control( node );
			if ( c && found.indexOf( c ) === -1 ) {
				found.push( c );
			}
		} );
		return found;
	}

	function setValue( el, value ) {
		if ( ! api.shouldFill( el.value ) ) {
			return;
		}
		if ( el.tagName === 'SELECT' ) {
			var match = Array.prototype.filter.call( el.options, function ( o ) {
				return o.value === value || o.text === value;
			} )[ 0 ];
			if ( ! match ) {
				return;
			}
			value = match.value;
		}
		el.value = value;
		el.dispatchEvent( new window.Event( 'input', { bubbles: true } ) );
		el.dispatchEvent( new window.Event( 'change', { bubbles: true } ) );
	}

	function fillFor( input ) {
		return function ( company ) {
			var root = scope( input );
			CAMPOS.forEach( function ( campo ) {
				var value = company[ campo ];
				if ( value === undefined || value === null || value === '' ) {
					return;
				}
				targets( root, campo ).forEach( function ( el ) {
					if ( el !== input ) {
						setValue( el, String( value ) );
					}
				} );
			} );
		};
	}

	function scan() {
		var nodes = document.querySelectorAll( '[data-apipj-cnpj], .apipj-cnpj, [name$="apipj_cnpj"], [name$="apipj_cnpj]"], [id$="apipj_cnpj"]' );
		Array.prototype.forEach.call( nodes, function ( node ) {
			var input = control( node );
			if ( input && ! input.apipjWatched ) {
				api.watch( input, fillFor( input ) );
			}
		} );
	}

	function start() {
		scan();
		if ( window.MutationObserver ) {
			// Popups and page builders may add forms after load.
			new window.MutationObserver( api.debounce( scan, 300 ) ).observe( document.body, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )( window, document );
