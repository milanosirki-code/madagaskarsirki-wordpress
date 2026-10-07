from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]

school_plugin = (ROOT / "wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php").read_text(encoding="utf-8")
mmc_main = (ROOT / "wp-content/plugins/madagaskar-management-center/madagaskar-management-center.php").read_text(encoding="utf-8")
activator = (ROOT / "wp-content/plugins/madagaskar-management-center/includes/class-mmc-activator.php").read_text(encoding="utf-8")
nav = (ROOT / "wp-content/plugins/madagaskar-management-center/includes/class-mmc-navigation-admin.php").read_text(encoding="utf-8")
source = (ROOT / "wp-content/plugins/madagaskar-management-center/includes/class-mmc-school-source-service.php").read_text(encoding="utf-8")
planning = (ROOT / "wp-content/plugins/madagaskar-management-center/includes/class-mmc-school-planning-service.php").read_text(encoding="utf-8")
admin = (ROOT / "wp-content/plugins/madagaskar-management-center/includes/class-mmc-school-planning-admin.php").read_text(encoding="utf-8")

checks = {
    "school plugin 1.8.0": "Version: 1.8.0" in school_plugin,
    "imam hatip excluded": "İMAM HATİP ORTAOKULU" in school_plugin and "return false" in school_plugin,
    "student page registered": "'mad-okul-students'" in school_plugin,
    "unknown student count metric": "Öğrenci Sayısı Bilinmeyen" in school_plugin,
    "student source fields": all(x in school_plugin for x in ["ogrenci_sayisi", "ogrenci_kaynak_url", "ogrenci_dogrulama_tarihi"]),
    "school source maps enriched fields": all(x in source for x in ["student_count_status", "student_source_url", "campus_key", "website"]),
    "source-first campus planning": "MMC_School_Source_Service::schools_for_area" in planning,
    "unknown school units summary": "unknown_school_units" in planning and "unknown_school_units" in admin,
    "print policy table": "mmc_school_print_policies" in activator,
    "print plan table": "mmc_school_print_plans" in activator,
    "school module registered": "class-mmc-school-planning-admin.php" in mmc_main,
    "ordered workflow menu": all(x in nav for x in [
        "1. MEBBİS Veri Aktarımı",
        "2. Okul & Kampüs Listesi",
        "3. Öğrenci Sayıları",
        "4. Davetiye / Bilet Baskı Planı",
        "5. Saha Planı & Ziyaret Sonuçları",
        "7. Rota Oluşturma",
    ]),
    "print tracking columns": all(x in planning for x in ["planned_qty", "printed_qty", "distributed_qty"]),
}

failed = [name for name, ok in checks.items() if not ok]
for name, ok in checks.items():
    print(f"[{'OK' if ok else 'FAIL'}] {name}")

if failed:
    raise SystemExit("School workflow contract failed: " + ", ".join(failed))

print("School workflow contract OK")
