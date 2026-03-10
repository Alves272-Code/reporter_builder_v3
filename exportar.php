<?php
require_once __DIR__ . '/config.php';

try {
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

    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="relatorio.xls"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo '<html xmlns:o="urn:schemas-microsoft-com:office:office" ';
    echo 'xmlns:x="urn:schemas-microsoft-com:office:excel" ';
    echo 'xmlns="http://www.w3.org/TR/REC-html40">';
    echo '<head>';
    echo '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    echo '<style>';
    echo 'table{border-collapse:collapse;font-family:Arial,sans-serif;font-size:12px;}';
    echo 'th,td{border:1px solid #999;padding:6px;vertical-align:top;}';
    echo 'th{background:#f2f2f2;font-weight:bold;}';
    echo '</style>';
    echo '</head><body>';
    echo '<table>';

    if (!empty($headers)) {
        echo '<tr>';
        foreach ($headers as $h) {
            echo '<th>' . htmlspecialchars($h, ENT_QUOTES, 'UTF-8') . '</th>';
        }
        echo '</tr>';
    }

    foreach ($rows as $row) {
        echo '<tr>';
        foreach (array_values($row) as $val) {
            echo '<td>' . htmlspecialchars(is_null($val) ? '' : (string)$val, ENT_QUOTES, 'UTF-8') . '</td>';
        }
        echo '</tr>';
    }

    echo '</table>';
    echo '</body></html>';
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo 'Erro ao exportar: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
}