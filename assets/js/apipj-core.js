/**
 * apipj – Consulta CNPJ: shared front-end helpers.
 *
 * Exposes window.apipjCnpj with local CNPJ validation (numeric and alphanumeric),
 * a memoized lookup through the site's own REST proxy and an input watcher that
 * shows a small inline status. The API key never reaches the browser.
 */
( function ( window, document ) {
	'use strict';

	var config = window.apipjConfig || {};
	var i18n = config.i18n || {};
	var WEIGHTS = [ 6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2 ];
	var memo = {};

	function normalize( value ) {
		return String( value == null ? '' : value ).replace( /[^0-9A-Za-z]/g, '' ).toUpperCase();
	}

	function checkDigits( base ) {
		var s1 = 0;
		var s2 = 0;
		for ( var i = 0; i < 12; i++ ) {
			var v = base.charCodeAt( i ) - 48;
			s1 += v * WEIGHTS[ i + 1 ];
			s2 += v * WEIGHTS[ i ];
		}
		var dv1 = s1 % 11 < 2 ? 0 : 11 - ( s1 % 11 );
		s2 += dv1 * WEIGHTS[ 12 ];
		var dv2 = s2 % 11 < 2 ? 0 : 11 - ( s2 % 11 );
		return '' + dv1 + dv2;
	}

	function isValid( value ) {
		var c = normalize( value );
		if ( ! /^[0-9A-Z]{12}[0-9]{2}$/.test( c ) || c === '00000000000000' ) {
			return false;
		}
		return c.slice( 12 ) === checkDigits( c.slice( 0, 12 ) );
	}

	/**
	 * Looks up a CNPJ through the site's REST proxy. Resolves to
	 * { state: 'found'|'not_found'|'invalid'|'error', company, active, message }.
	 */
	function lookup( cnpj ) {
		var c = normalize( cnpj );
		if ( ! isValid( c ) ) {
			return Promise.resolve( { state: 'invalid', message: i18n.invalid || '' } );
		}
		if ( memo[ c ] ) {
			return memo[ c ];
		}
		var url = String( config.restUrl || '' ) + encodeURIComponent( c );
		memo[ c ] = window
			.fetch( url, {
				method: 'GET',
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'X-Apipj-Token': config.token || '' },
			} )
			.then( function ( response ) {
				return response.json().then(
					function ( body ) {
						return { status: response.status, body: body || {} };
					},
					function () {
						return { status: response.status, body: {} };
					}
				);
			} )
			.then( function ( r ) {
				if ( r.status === 200 && r.body.found ) {
					return { state: 'found', company: r.body.company || {}, active: !! r.body.active };
				}
				if ( r.status !== 404 ) {
					delete memo[ c ]; // Transient failure: allow a retry later.
				}
				if ( r.status === 404 ) {
					return { state: 'not_found', message: r.body.message || i18n.notFound || '' };
				}
				if ( r.status === 400 ) {
					return { state: 'invalid', message: r.body.message || i18n.invalid || '' };
				}
				return { state: 'error', message: typeof r.body.message === 'string' ? r.body.message : '' };
			} )
			.catch( function () {
				delete memo[ c ];
				return { state: 'error', message: i18n.failed || '' };
			} );
		return memo[ c ];
	}

	function debounce( fn, wait ) {
		var t;
		return function () {
			var args = arguments;
			var self = this;
			window.clearTimeout( t );
			t = window.setTimeout( function () {
				fn.apply( self, args );
			}, wait );
		};
	}

	/**
	 * Returns (creating it if needed) the inline status element for an input.
	 * `anchor` is the element after which the status is inserted (defaults to the input).
	 */
	function statusElement( input, anchor ) {
		var el = input.apipjStatus;
		if ( el && el.isConnected ) {
			return el;
		}
		el = document.createElement( 'span' );
		el.className = 'apipj-status';
		el.setAttribute( 'role', 'status' );
		el.setAttribute( 'aria-live', 'polite' );
		var after = anchor || input;
		if ( after.parentNode ) {
			after.parentNode.insertBefore( el, after.nextSibling );
		}
		input.apipjStatus = el;
		return el;
	}

	function setStatus( input, anchor, text, type ) {
		var el = statusElement( input, anchor );
		el.textContent = text || '';
		el.className = 'apipj-status' + ( text && type ? ' apipj-status--' + type : '' );
	}

	/**
	 * Status text and type for a lookup result.
	 */
	function describe( result ) {
		if ( result.state === 'found' ) {
			if ( result.active || config.inactive === 'none' ) {
				return { text: i18n.found || '', type: 'success' };
			}
			var text = String( i18n.status || '%s' ).replace( '%s', result.company.situacao || '' );
			if ( config.inactive === 'block' ) {
				return { text: text + ' — ' +( i18n.blocked || '' ), type: 'error' };
			}
			return { text: text, type: 'warning' };
		}
		if ( result.state === 'not_found' || result.state === 'invalid' ) {
			return { text: result.message, type: 'warning' };
		}
		return { text: result.message, type: 'error' };
	}

	/**
	 * Watches a CNPJ input. Calls onFound( company, result ) when a company is found.
	 * Typing is never blocked: the lookup runs asynchronously after a short pause.
	 *
	 * options.anchor: function returning the element after which the status is shown.
	 */
	function watch( input, onFound, options ) {
		if ( ! input || input.apipjWatched ) {
			return;
		}
		input.apipjWatched = true;
		options = options || {};
		var last = '';
		var anchor = function () {
			return options.anchor ? options.anchor( input ) : input;
		};

		var run = function () {
			var c = normalize( input.value );
			if ( c.length < 14 ) {
				last = '';
				if ( input.apipjStatus ) {
					setStatus( input, anchor(), '', '' );
				}
				return;
			}
			if ( c === last ) {
				return;
			}
			last = c;
			if ( ! isValid( c ) ) {
				setStatus( input, anchor(), i18n.invalid || '', 'warning' );
				return;
			}
			setStatus( input, anchor(), i18n.searching || '', 'info' );
			lookup( c ).then( function ( result ) {
				if ( normalize( input.value ) !== c ) {
					return; // The shopper kept typing.
				}
				var d = describe( result );
				setStatus( input, anchor(), d.text, d.type );
				if ( result.state === 'found' && typeof onFound === 'function' ) {
					onFound( result.company, result );
				}
				var evt;
				try {
					evt = new window.CustomEvent( 'apipj:result', { bubbles: true, detail: result } );
				} catch ( e ) {
					evt = null;
				}
				if ( evt ) {
					input.dispatchEvent( evt );
				}
			} );
		};

		var debounced = debounce( run, 450 );
		input.addEventListener( 'input', debounced );
		input.addEventListener( 'change', run );
		input.addEventListener( 'blur', run );
		if ( input.value ) {
			run();
		}
	}

	function wants( field ) {
		return ( config.fill || [] ).indexOf( field ) !== -1;
	}

	function shouldFill( current ) {
		return config.overwrite === 'always' || ! String( current == null ? '' : current ).trim();
	}

	/**
	 * Maps company data to address values.
	 * opts.hasNumber / opts.hasNeighborhood: whether the form has dedicated fields.
	 * Returns only the keys enabled in the settings.
	 */
	function addressValues( company, opts ) {
		opts = opts || {};
		var out = {};
		var street = company.logradouro || '';
		var number = company.numero || '';
		var complement = company.complemento || '';
		var district = company.bairro || '';

		if ( wants( 'company' ) && company.nome ) {
			out.company = company.nome;
		}
		if ( wants( 'address' ) && street ) {
			out.address_1 = street;
			if ( wants( 'number' ) && number && ! opts.hasNumber ) {
				out.address_1 = street + ', ' + number;
			}
		}
		if ( wants( 'number' ) && number && opts.hasNumber ) {
			out.number = number;
		}
		var line2 = [];
		if ( wants( 'complement' ) && complement ) {
			line2.push( complement );
		}
		if ( wants( 'neighborhood' ) && district ) {
			if ( opts.hasNeighborhood ) {
				out.neighborhood = district;
			} else {
				line2.push( district );
			}
		}
		if ( line2.length ) {
			out.address_2 = line2.join( ' - ' );
		}
		if ( wants( 'city' ) && company.municipio ) {
			out.city = company.municipio;
		}
		if ( wants( 'state' ) && /^[A-Z]{2}$/.test( company.uf || '' ) && company.uf !== 'EX' ) {
			out.state = company.uf;
		}
		if ( wants( 'postcode' ) && company.cep ) {
			var cep = String( company.cep ).replace( /\D/g, '' );
			out.postcode = cep.length === 8 ? cep.slice( 0, 5 ) + '-' + cep.slice( 5 ) : company.cep;
		}
		if ( wants( 'phone' ) && company.telefone ) {
			out.phone = String( company.telefone ).split( ' / ' )[ 0 ];
		}
		if ( company.uf && company.uf !== 'EX' && ( out.state || out.postcode || out.city ) ) {
			out.country = 'BR';
		}
		return out;
	}

	/**
	 * Decides which values to write, given the current values of the form (same keys).
	 * Honors the overwrite policy. Country and state are pre-selected by WooCommerce
	 * (store base), so they follow the address: when the city is being filled, the state
	 * and the country are set too.
	 */
	function plan( values, current ) {
		current = current || {};
		var out = {};
		Object.keys( values ).forEach( function ( key ) {
			if ( key === 'country' || key === 'state' ) {
				return;
			}
			if ( shouldFill( current[ key ] ) && current[ key ] !== values[ key ] ) {
				out[ key ] = values[ key ];
			}
		} );
		var addressFilled = out.city !== undefined || out.postcode !== undefined || out.address_1 !== undefined;
		if ( values.country && current.country !== values.country && ( addressFilled || ! current.country ) ) {
			out.country = values.country;
		}
		var country = out.country || current.country;
		if ( values.state && country === 'BR' && current.state !== values.state && ( addressFilled || shouldFill( current.state ) ) ) {
			out.state = values.state;
		}
		return out;
	}

	window.apipjCnpj = {
		plan: plan,
		config: config,
		normalize: normalize,
		isValid: isValid,
		lookup: lookup,
		watch: watch,
		setStatus: setStatus,
		addressValues: addressValues,
		shouldFill: shouldFill,
		debounce: debounce,
	};
} )( window, document );
