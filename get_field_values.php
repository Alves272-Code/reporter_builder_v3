<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    rb_require_auth('json');
    $db = rb_db();

    $field = isset($_GET["field"]) ? trim((string)$_GET["field"]) : "";
    if (!$field || strpos($field, '.') === false) {
        rb_json(["success"=>false,"message"=>"Campo inválido"], 400);
    }

    list($table, $column) = explode(".", $field, 2);

    if (!validateIdentifier($table) || !validateIdentifier($column)) {
        rb_json(["success"=>false,"message"=>"Campo inválido"], 400);
    }

    $metaRes = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '$column'");
    $meta = $metaRes ? mysqli_fetch_assoc($metaRes) : null;
    $type = $meta && isset($meta['Type']) ? strtolower($meta['Type']) : '';

    $countRes = mysqli_query($db, "SELECT COUNT(*) AS total, COUNT(DISTINCT `$column`) AS distinct_count FROM `$table` WHERE `$column` IS NOT NULL");
    $countRow = $countRes ? mysqli_fetch_assoc($countRes) : ['total'=>0,'distinct_count'=>0];

    $total = (int)$countRow['total'];
    $distinct = (int)$countRow['distinct_count'];
    $ratio = $total > 0 ? ($distinct / $total) : 1;

    $mode = 'text';
    if (preg_match('/^(tinyint\\(1\\)|bit\\(1\\)|boolean)/', $type)) {
        $mode = 'select';
    } elseif ($distinct > 0 && $distinct <= 40 && $ratio <= 0.35) {
        $mode = 'select';
    } elseif ($distinct > 0 && $distinct <= 12) {
        $mode = 'select';
    }

    $values = [];
    if ($mode === 'select') {
        $sql = "SELECT DISTINCT `$column` FROM `$table` WHERE `$column` IS NOT NULL ORDER BY `$column` LIMIT 200";
        $res = mysqli_query($db, $sql);
        if ($res) {
            while ($r = mysqli_fetch_row($res)) {
                $values[] = $r[0];
            }
        }
    }

    rb_json([
        "success" => true,
        "mode" => $mode,
        "type" => $type,
        "distinct_count" => $distinct,
        "values" => $values
    ]);
} catch (Throwable $e) {
    rb_json(["success"=>false,"message"=>$e->getMessage()], 500);
}
