#!/usr/bin/env bash
# Prepares a fresh WordPress + WooCommerce (+ this plugin, active) for the end-to-end suites:
# installs the API mock as a must-use plugin, creates the test products/pages, configures
# the store and writes tests/e2e/state.json. Needs WP_PATH, ImageMagick (convert), and
# optionally heif-enc (for the HEIC fixture already in fixtures/).
set -euo pipefail
: "${WP_PATH:?Set WP_PATH to the WordPress root}"
WP_CLI="${WP_CLI:-wp --allow-root}"
WP="$WP_CLI --path=$WP_PATH"
HERE="$(cd "$(dirname "$0")" && pwd)"
FIX="$HERE/fixtures"
mkdir -p "$WP_PATH/wp-content/mu-plugins" "$FIX/generated"
cp "$HERE/mock/ofr-mock.php" "$WP_PATH/wp-content/mu-plugins/ofr-mock.php"
cp "$FIX/mock-result.png" "$WP_PATH/wp-content/mock-result.png"

# A large photo with fake GPS EXIF, to prove metadata never reaches the service.
convert -size 3000x4000 plasma:fractal -quality 92 "$FIX/generated/big.jpg"
php -r '$d=file_get_contents($argv[1]); $p="Exif\0\0GPSSECRET-35.6892,51.3890 serial SECRET"; file_put_contents($argv[2], substr($d,0,2)."\xFF\xE1".pack("n",strlen($p)+2).$p.substr($d,2));' "$FIX/generated/big.jpg" "$FIX/generated/person-gps.jpg"

$WP option update woocommerce_coming_soon no
$WP option update woocommerce_currency IRT
$WP option update woocommerce_default_country "US:CA"
$WP option update woocommerce_ship_to_countries disabled
$WP option update woocommerce_cod_settings '{"enabled":"yes","title":"COD","description":"","instructions":"","enable_for_methods":[],"enable_for_virtual":"yes"}' --format=json
$WP post update 3 --post_status=publish >/dev/null 2>&1 || true
$WP option update wp_page_for_privacy_policy 3

SIMPLE=$($WP wc product create --name="پیراهن تست" --type=simple --regular_price=1200000 --sale_price=990000 --user=1 --porcelain)
$WP media import "$FIX/shirt.jpg" --post_id="$SIMPLE" --featured_image --porcelain >/dev/null
VARIABLE=$($WP eval-file "$HERE/make-variable.php" "$FIX")
CHECKOUT=$($WP post create --post_type=page --post_status=publish --post_title="Classic checkout" --post_content='[woocommerce_checkout]' --porcelain)
$WP option update woocommerce_checkout_page_id "$CHECKOUT"
$WP eval '$s = Online_Fitting_Room::settings(); $s["api_key"] = "vton_live_testkey123456789"; update_option("ofr_settings", $s);'

printf '{"simple": %s, "variable": %s, "checkout": %s}\n' "$SIMPLE" "$VARIABLE" "$CHECKOUT" > "$HERE/state.json"
echo "Ready: $(cat "$HERE/state.json")"
