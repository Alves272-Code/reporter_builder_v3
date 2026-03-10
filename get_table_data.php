<?php
require_once __DIR__ . '/config.php';
try {
    rb_require_auth('json');
    $db = rb_db();
    $traducao = require __DIR__ . '/traducao.php';
    $table = isset($_GET['table']) ? $_GET['table'] : '';
    if (!$table || !validateIdentifier($table)) {
        http_response_code(400);
        die("<div class='alert alert-danger'>Tabela inválida.</div>");
    }

    $rows = rb_prepare_and_fetch_all($db, "SELECT * FROM `$table` LIMIT 10", array());
    if (!$rows) {
        die("<div class='alert alert-warning'>Sem dados nesta tabela.</div>");
    }

    echo "<table class='table table-bordered table-sm table-striped'><thead><tr>";
    foreach (array_keys($rows[0]) as $col) {
        $label = isset($traducao[$table]['campos'][$col]) ? $traducao[$table]['campos'][$col] : $col;
        echo "<th>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</th>";
    }
    echo "</tr></thead><tbody>";
    foreach ($rows as $row) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars(is_null($value) ? '' : (string)$value, ENT_QUOTES, 'UTF-8') . "</td>";
        }
        echo "</tr>";
    }
    echo "</tbody></table>";
} catch (Exception $e) {
    http_response_code(500);
    echo "<div class='alert alert-danger'>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
}
