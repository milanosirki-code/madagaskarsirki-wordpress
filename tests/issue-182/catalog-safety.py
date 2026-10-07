from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
legacy = (ROOT / "wp-content/plugins/madagaskar-legacy-redirects/madagaskar-legacy-redirects.php").read_text(encoding="utf-8")
v4 = (ROOT / "wp-content/plugins/madagaskar-bilet-yonetimi-v4/madagaskar-bilet-yonetimi-v4.php").read_text(encoding="utf-8")

for needle in [
    "Version: 1.0.2",
    "'/shop' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-26-eylul-2026-1400-bileti' => '/bilet-al/'",
    "'/urun/madagaskar-sirki-ankara-26-eylul-2026-1600-bileti' => '/bilet-al/'",
]:
    assert needle in legacy, needle

for needle in [
    "Version: 4.0.15-transition",
    "woocommerce_product_is_visible",
    "filter_closed_or_expired_catalog_visibility",
    "product_session_started",
    "CATALOG_HIDDEN_META",
    "set_catalog_visibility( 'hidden' )",
    "hide_product_catalog_by_v4",
    "restore_product_catalog_by_v4",
]:
    assert needle in v4, needle

assert "update_post_meta($product_id,self::SALES_META,'yes');\n            $this->hide_product_catalog_by_v4( $product_id );" in v4
print("PASS: Issue #182 redirect and catalogue-safety contract.")
