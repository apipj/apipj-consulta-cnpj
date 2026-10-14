<?php
/**
 * Test-only mocks for the apipj API (NOT part of the plugin).
 * 11.222.333/0001-81 -> a company with status BAIXADA; 11.444.777/0001-61 -> 429 quota_exceeded.
 */

defined( 'ABSPATH' ) || exit;
add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		if ( false !== strpos( $url, '/v1/cnpj/11222333000181' ) ) {
			return array(
				'headers'  => array(),
				'body'     => wp_json_encode( array( 'cnpj' => '11.222.333/0001-81', 'nome' => 'EMPRESA BAIXADA DE TESTE LTDA', 'fantasia' => '', 'situacao' => 'BAIXADA', 'logradouro' => 'RUA DO TESTE', 'numero' => '100', 'complemento' => '', 'bairro' => 'CENTRO', 'municipio' => 'SAO PAULO', 'uf' => 'SP', 'cep' => '01.001-000', 'telefone' => '', 'email' => '', 'qsa' => array( array( 'nome' => 'NAO DEVE VAZAR' ) ) ) ),
				'response' => array( 'code' => 200, 'message' => 'OK' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		if ( false !== strpos( $url, '/v1/cnpj/11444777000161' ) ) {
			return array(
				'headers'  => array(),
				'body'     => '{"status":"ERROR","message":"Cota mensal esgotada","code":"quota_exceeded","upgrade_url":"https://apipj.com.br/precos","reset":1793502000}',
				'response' => array( 'code' => 429, 'message' => 'Too Many Requests' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}
		return $pre;
	},
	10,
	3
);
