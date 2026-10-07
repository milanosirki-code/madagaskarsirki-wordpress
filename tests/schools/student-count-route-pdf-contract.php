<?php
$root = dirname(__DIR__, 2);

$files = array(
    'school' => $root . '/wp-content/plugins/madagaskar-okul-tanitim/madagaskar-okul-tanitim.php',
    'ops' => $root . '/wp-content/plugins/madagaskar-okul-tanitim/includes/class-mad-okul-operations.php',
    'source' => $root . '/wp-content/plugins/madagaskar-management-center/includes/class-mmc-school-source-service.php',
    'region' => $root . '/wp-content/plugins/madagaskar-management-center/includes/class-mmc-region-service.php',
    'admin' => $root . '/wp-content/plugins/madagaskar-management-center/includes/class-mmc-admin.php',
);

foreach ($files as $key => $path) {
    if (!is_file($path)) {
        fwrite(STDERR, "Missing file: {$key} {$path}\n");
        exit(1);
    }
    $files[$key] = file_get_contents($path);
}

$checks = array(
    'student_count schema' => strpos($files['school'], 'student_count int unsigned NULL') !== false,
    'student_count manual save' => strpos($files['school'], "'student_count' => mad_okul_student_count_value") !== false,
    'student_count import alias' => strpos($files['school'], "'OGRENCI_SAYISI'") !== false,
    'route resolves a program' => strpos($files['school'], '$route_program=null;') !== false,
    'route persists program binding' => strpos($files['school'], "'program_id'=>(int)\$route_program->id") !== false,
    'route exposes PDF action' => strpos($files['school'], 'Rota Planını / PDF Olarak Getir') !== false,
    'empty PDF is explained' => strpos($files['ops'], "Bu program için PDF'ye bağlı kurum yok.") !== false,
    'route PDF shows student count' => strpos($files['ops'], '<th>Öğrenci</th>') !== false,
    'school source reports global stats' => strpos($files['source'], 'public static function all_stats()') !== false,
    'missing student institutions computed' => strpos($files['source'], "'student_missing_rows'") !== false,
    'program summary exposes school stats' => strpos($files['region'], "'school_area_stats'") !== false,
    'warehouse shows missing institutions' => strpos($files['admin'], 'Öğrenci Sayısı Eksik Kurum') !== false,
);

$failed = array_keys(array_filter($checks, static function($ok){ return !$ok; }));
if ($failed) {
    fwrite(STDERR, "Contract checks failed:\n - " . implode("\n - ", $failed) . "\n");
    exit(1);
}

echo "School student-count / route-PDF contract checks passed.\n";
