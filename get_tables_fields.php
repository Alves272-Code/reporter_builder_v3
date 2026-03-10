<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    rb_require_auth('json');
    $db = rb_db();

    $table = isset($_GET['table']) ? trim((string)$_GET['table']) : '';

    if ($table === '') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Tabela não indicada.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Nome de tabela inválido.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $traducao = [];
    $traducaoFile = __DIR__ . '/traducao.php';
    if (file_exists($traducaoFile)) {
        $tmp = include $traducaoFile;
        if (is_array($tmp)) {
            $traducao = $tmp;
        }
    }

    $sql = "SHOW COLUMNS FROM `$table`";
    $res = mysqli_query($db, $sql);

    if (!$res) {
        throw new Exception('Erro SQL ao obter campos: ' . mysqli_error($db));
    }

    $fields = [];

    while ($row = mysqli_fetch_assoc($res)) {
        $fieldName = isset($row['Field']) ? $row['Field'] : '';

        if ($fieldName === '') {
            continue;
        }

        $label = $fieldName;

        if (
            isset($traducao[$table]) &&
            isset($traducao[$table]['campos']) &&
            isset($traducao[$table]['campos'][$fieldName]) &&
            $traducao[$table]['campos'][$fieldName] !== ''
        ) {
            $label = $traducao[$table]['campos'][$fieldName];
        }

        $fields[] = [
            'name' => $fieldName,
            'label' => $label,
            'type' => isset($row['Type']) ? $row['Type'] : '',
            'nullable' => (isset($row['Null']) && $row['Null'] === 'YES')
        ];
    }

    echo json_encode([
        'success' => true,
        'table' => $table,
        'fields' => $fields
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}