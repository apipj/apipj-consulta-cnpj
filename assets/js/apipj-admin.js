/**
 * apipj – Consulta CNPJ: settings screen ("Test connection" button).
 */
( function ( window, document ) {
	'use strict';

	var config = window.apipjAdmin || {};

	function init() {
		var button = document.getElementById( 'apipj-test-connection' );
		var output = document.getElementById( 'apipj-test-result' );
		if ( ! button || ! output ) {
			return;
		}
		button.addEventListener( 'click', function ( event ) {
			event.preventDefault();
			var keyInput = document.getElementById( 'apipj_api_key' );
			var body = new window.FormData();
			body.append( 'action', 'apipj_test_connection' );
			body.append( '_ajax_nonce', config.nonce || '' );
			if ( keyInput && keyInput.value ) {
				body.append( 'key', keyInput.value );
			}
			button.disabled = true;
			output.className = 'apipj-test-result';
			output.textContent = config.i18n && config.i18n.testing ? config.i18n.testing : '…';
			window
				.fetch( config.ajaxUrl, { method: 'POST', credentials: 'same-origin', body: body } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( r ) {
					var lines = ( r && r.data && r.data.lines ) || [];
					output.textContent = '';
					lines.forEach( function ( line ) {
						var p = document.createElement( 'span' );
						p.style.display = 'block';
						p.textContent = line;
						output.appendChild( p );
					} );
					output.className = 'apipj-test-result ' + ( r && r.success ? 'apipj-ok' : 'apipj-fail' );
				} )
				.catch( function () {
					output.textContent = config.i18n && config.i18n.failed ? config.i18n.failed : 'Error';
					output.className = 'apipj-test-result apipj-fail';
				} )
				.then( function () {
					button.disabled = false;
				} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )( window, document );
