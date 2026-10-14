/* Renders the WordPress.org directory icon and banner. Run in the Playwright container: node brand-assets.cjs */
/* Needs brand/apple-touch-icon.png, downloaded from https://apipj.com.br/apple-touch-icon.png (git-ignored). */
const { chromium } = require( '/opt/pw/node_modules/playwright' );
const fs = require( 'fs' );

const logo = 'data:image/png;base64,' + fs.readFileSync( __dirname + '/brand/apple-touch-icon.png' ).toString( 'base64' );

( async () => {
	const browser = await chromium.launch();
	const page = await browser.newPage();

	// Sample the logo colors so the artwork matches the brand.
	await page.setContent( `<img id="l" src="${ logo }">` );
	const colors = await page.evaluate( async () => {
		const img = document.getElementById( 'l' );
		await img.decode();
		const c = document.createElement( 'canvas' );
		c.width = img.naturalWidth;
		c.height = img.naturalHeight;
		const ctx = c.getContext( '2d' );
		ctx.drawImage( img, 0, 0 );
		const d = ctx.getImageData( 0, 0, c.width, c.height ).data;
		const px = ( x, y ) => {
			const i = ( y * c.width + x ) * 4;
			return `rgb(${ d[ i ] },${ d[ i + 1 ] },${ d[ i + 2 ] })`;
		};
		// Background corner and the most saturated pixel (the yellow "pj").
		let best = [ 0, 0, 0 ];
		let score = -1;
		for ( let i = 0; i < d.length; i += 4 ) {
			const s = Math.max( d[ i ], d[ i + 1 ], d[ i + 2 ] ) - Math.min( d[ i ], d[ i + 1 ], d[ i + 2 ] );
			if ( s > score && d[ i + 3 ] > 250 ) {
				score = s;
				best = [ d[ i ], d[ i + 1 ], d[ i + 2 ] ];
			}
		}
		return { bg: px( 2, 2 ), accent: `rgb(${ best.join( ',' ) })` };
	} );
	console.log( 'colors', colors );

	const mark = ( size ) => `<div style="font:700 ${ size }px/1 'Liberation Mono',monospace;letter-spacing:-0.04em"><span style="color:#f2f2f0">a</span><span style="color:${ colors.accent }">pj</span></div>`;

	for ( const s of [ 256, 128 ] ) {
		await page.setViewportSize( { width: s, height: s } );
		await page.setContent( `<body style="margin:0;width:${ s }px;height:${ s }px;background:${ colors.bg };display:flex;align-items:center;justify-content:center">${ mark( Math.round( s * 0.42 ) ) }</body>` );
		await page.screenshot( { path: `/assets/icon-${ s }x${ s }.png` } );
	}

	const banner = ( w, h ) => {
		const k = w / 772;
		return `<body style="margin:0;width:${ w }px;height:${ h }px;background:${ colors.bg };font-family:'Liberation Sans',sans-serif;display:flex;align-items:center;gap:${ 34 * k }px;padding:0 ${ 44 * k }px;box-sizing:border-box;color:#e8eaed;overflow:hidden">
			<div style="flex:0 0 auto">${ mark( Math.round( 76 * k ) ) }</div>
			<div style="flex:1 1 auto">
				<div style="font-weight:700;font-size:${ 34 * k }px;line-height:1.1">Consulta CNPJ</div>
				<div style="font-size:${ 16 * k }px;color:#a3aab3;margin-top:${ 8 * k }px;line-height:1.35">Razão social e endereço preenchidos<br>a partir do CNPJ, no checkout e em formulários</div>
				<div style="margin-top:${ 18 * k }px;border:${ 1.5 * k }px solid #3a4048;border-radius:${ 8 * k }px;padding:${ 8 * k }px ${ 12 * k }px;width:${ 300 * k }px;background:#181b1f">
					<div style="font-size:${ 11 * k }px;color:#7c848e">CNPJ</div>
					<div style="font:${ 17 * k }px 'Liberation Mono',monospace">12.ABC.345/01DE-35</div>
				</div>
				<div style="font-size:${ 13 * k }px;color:#34d399;margin-top:${ 7 * k }px">&#10003; Empresa encontrada</div>
			</div>
		</body>`;
	};
	for ( const [ w, h ] of [ [ 772, 250 ], [ 1544, 500 ] ] ) {
		await page.setViewportSize( { width: w, height: h } );
		await page.setContent( banner( w, h ) );
		await page.screenshot( { path: `/assets/banner-${ w }x${ h }.png` } );
	}
	await browser.close();
} )();
