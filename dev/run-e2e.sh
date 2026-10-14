#!/usr/bin/env bash
# Runs the end-to-end scenarios against the Docker stack in dev/docker-compose.yml.
# Prerequisites: stack up and site installed (dev/setup.sh),
# Playwright container "apipj-pw" on the apipj-wp_default network with playwright in /opt/pw.
set -u
W="docker exec -u 33 apipj-wp-cli-1 wp"
pw() { docker exec "$@"; }
reset_rl() { for t in $($W transient list --search='apipj_rl_*' --fields=name --format=csv | tail -n +2); do $W transient delete "$t" >/dev/null; done; }
fails=0
run() { pw "$@" || fails=$((fails+1)); }

$W option patch update apipj_settings inactive warn >/dev/null
$W option patch update apipj_settings rate_limit 100 >/dev/null
run apipj-pw node run.cjs classic
$W plugin deactivate woocommerce-extra-checkout-fields-for-brazil >/dev/null
run -e NO_BMW=1 apipj-pw node run.cjs classic
$W plugin activate woocommerce-extra-checkout-fields-for-brazil >/dev/null
run apipj-pw node run.cjs blocks
run apipj-pw node run.cjs account
run apipj-pw node run.cjs forms
$W option patch update apipj_settings inactive block >/dev/null
run apipj-pw node run.cjs inactive
$W option patch update apipj_settings inactive warn >/dev/null
run apipj-pw node run.cjs quota
$W option get apipj_last_error --format=json
run -e SETTINGS_SHOT=out/settings.png apipj-pw node run.cjs settings
$W option delete apipj_last_error >/dev/null
reset_rl
$W option patch update apipj_settings rate_limit 2 >/dev/null
run apipj-pw node run.cjs ratelimit
$W option patch update apipj_settings rate_limit 5 >/dev/null
$W eval-file wp-content/plugins/apipj-consulta-cnpj/tests/php/smoke.php || fails=$((fails+1))
echo "E2E failures: $fails"
exit $fails
