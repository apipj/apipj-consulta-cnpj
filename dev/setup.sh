#!/usr/bin/env bash
# Throwaway local test site (Docker). Not shipped in the plugin zip.
# Usage (Git Bash: prefix with MSYS_NO_PATHCONV=1):
#   docker compose -f dev/docker-compose.yml up -d
#   bash dev/setup.sh /path/to/file-with-apipj-key
#   docker run -d --name apipj-pw --network apipj-wp_default -v "$PWD/tests/e2e:/work" -v "$PWD/.wordpress-org:/assets" \
#     -w /work -e PLAYWRIGHT_SKIP_BROWSER_DOWNLOAD=1 mcr.microsoft.com/playwright:v1.64.0-noble sleep infinity
#   docker exec apipj-pw sh -c "mkdir -p /opt/pw && cd /opt/pw && npm init -y && npm i playwright@1.64.0"
#   bash dev/run-e2e.sh
set -eu
KEY_FILE="$1"
W="docker exec -u 33 apipj-wp-cli-1 wp"
$W core install --url=http://wordpress --title="Loja Teste" --admin_user=admin --admin_password=admin --admin_email=admin@example.com --skip-email
$W language core install pt_BR --activate
$W plugin install woocommerce woocommerce-extra-checkout-fields-for-brazil --activate
$W language plugin install woocommerce woocommerce-extra-checkout-fields-for-brazil pt_BR
$W plugin activate apipj-consulta-cnpj
$W rewrite structure '/%postname%/'
$W option update woocommerce_default_country "BR:SP"
$W option update woocommerce_currency BRL
$W option update woocommerce_coming_soon no
$W option update woocommerce_checkout_company_field optional
$W option update woocommerce_cod_settings '{"enabled":"yes","title":"Pagamento na entrega"}' --format=json
$W option update wcbcf_settings '{"person_type":1,"cell_phone":-1,"fields_style":0,"mailcheck":1,"maskedinput":1,"validate_cpf":1,"validate_cnpj":1}' --format=json
$W wc product create --name="Produto Teste" --regular_price=49.90 --user=admin --porcelain
$W post create --post_type=page --post_status=publish --post_title="Finalizar classico" --post_name=finalizar-classico --post_content='<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->'
docker exec -i -u 33 apipj-wp-cli-1 wp post create - --post_type=page --post_status=publish --post_title="Cadastro de empresa" --post_name=cadastro-empresa < "$(dirname "$0")/form-page.html"
Z=$($W wc shipping_zone create --name=Brasil --user=admin --porcelain)
$W eval "\$z=new WC_Shipping_Zone($Z);\$z->add_location('BR','country');\$z->save();"
$W wc shipping_zone_method create "$Z" --method_id=flat_rate --enabled=true --settings='{"cost":"10"}' --user=admin
# The key is read from a file and passed through stdin: never echoed, never stored in the repo.
tr -d '\r\n ' < "$KEY_FILE" | docker exec -i -u 33 apipj-wp-cli-1 wp option update apipj_api_key --autoload=no >/dev/null
