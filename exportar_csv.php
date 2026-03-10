<?php
require_once __DIR__ . '/config.php';

try {
    rb_require_auth('json');
    $db = rb_db();
    $traducao = file_exists(__DIR__ . '/traducao.php') ? require __DIR__ . '/traducao.php' : array();
    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        throw new Exception('Pedido inválido.');
    }

    $parts = rb_build_query_parts(
        isset($data['tabelas']) ? $data['tabelas'] : array(),
        isset($data['campos']) ? $data['campos'] : array(),
        isset($data['filtros']) ? $data['filtros'] : array(),
        isset($data['order']) ? $data['order'] : array()
    );

    $headers = array();
    $campos = isset($data['campos']) ? $data['campos'] : array();

    foreach ($campos as $c) {
        if (!is_string($c) || strpos($c, '.') === false) continue;
        list($tab, $col) = explode('.', $c, 2);
        if (!validateIdentifier($tab) || !validateIdentifier($col)) continue;

        $lbl = isset($traducao[$tab]['campos'][$col]) ? $traducao[$tab]['campos'][$col] : $col;
        $tabLbl = isset($traducao[$tab]['tabela']) ? $traducao[$tab]['tabela'] : $tab;

        $headers[] = $lbl . ' (' . $tabLbl . ')';
    }

    $sql = "SELECT " . implode(', ', $parts['selects']) .
           " FROM `" . $parts['base'] . "` " .
           $parts['joins'] . " " .
           $parts['where'] . " " .
           $parts['order'];

    $rows = rb_prepare_and_fetch_all($db, $sql, $parts['params']);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "\xEF\xBB\xBF";

    $out = fopen('php://output', 'w');

    if (!empty($headers)) {
        fputcsv($out, $headers, ';');
    }

    foreach ($rows as $row) {
        $line = array();
        foreach (array_values($row) as $v) {
            $line[] = is_null($v) ? '' : (string)$v;
        }
        fputcsv($out, $line, ';');
    }

    fclose($out);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Erro ao exportar CSV: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}