<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

$rb_db_error = '';
$rb_ligacao = false;
$rb_liga_paths = array(
    __DIR__ . '/../ligaBD.php',
    dirname(__DIR__) . '/ligaBD.php',
    __DIR__ . '/ligaBD.php'
);

foreach ($rb_liga_paths as $rb_liga_path) {
    if (is_file($rb_liga_path)) {
        require_once $rb_liga_path;
        $rb_ligacao = true;
        break;
    }
}

if (!$rb_ligacao) {
    $rb_db_error = 'Ficheiro ligaBD.php não encontrado. Verifique a instalação do módulo.';
}

function rb_db() {
    global $ligacao, $rb_db_error;

    if (!isset($ligacao) || !$ligacao) {
        if (!empty($rb_db_error)) {
            throw new Exception($rb_db_error);
        }
        throw new Exception("Ligação à base de dados não disponível.");
    }

    return $ligacao;
}

function rb_current_user() {
    if (isset($_SESSION['UtilizadorEmail']) && trim((string)$_SESSION['UtilizadorEmail']) !== '') {
        return trim((string)$_SESSION['UtilizadorEmail']);
    }
    return '';
}

function rb_base_url() {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $scheme = $https ? 'https' : 'http';
    $host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
    $scriptDir = isset($_SERVER['SCRIPT_NAME']) ? dirname($_SERVER['SCRIPT_NAME']) : '';
    $scriptDir = rtrim(str_replace('\\', '/', $scriptDir), '/');
    return $scheme . '://' . $host . ($scriptDir ? $scriptDir : '');
}

function rb_json($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function rb_write_log($msg) {
    error_log("[REPORT_BUILDER] " . $msg);
}

function validateIdentifier($name) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $name);
}

function rb_prepare_and_exec($db, $sql, $params = array()) {
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($db));
    }

    if ($params) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception($err);
    }

    mysqli_stmt_close($stmt);
}

function rb_prepare_and_fetch_all($db, $sql, $params = array()) {
    $stmt = mysqli_prepare($db, $sql);
    if (!$stmt) {
        throw new Exception(mysqli_error($db));
    }

    if ($params) {
        $types = str_repeat('s', count($params));
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }

    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new Exception($err);
    }

    $meta = mysqli_stmt_result_metadata($stmt);
    if (!$meta) {
        mysqli_stmt_close($stmt);
        return [];
    }

    $fields = [];
    $row = [];
    $data = [];

    while ($field = mysqli_fetch_field($meta)) {
        $fields[] = &$row[$field->name];
    }

    call_user_func_array(array($stmt, 'bind_result'), $fields);

    while (mysqli_stmt_fetch($stmt)) {
        $c = [];
        foreach ($row as $key => $val) {
            $c[$key] = $val;
        }
        $data[] = $c;
    }

    mysqli_stmt_close($stmt);
    return $data;
}

function rb_prepare_and_fetch_one($db, $sql, $params = array()) {
    $rows = rb_prepare_and_fetch_all($db, $sql, $params);
    return count($rows) > 0 ? $rows[0] : null;
}

function rb_ensure_saved_reports_table() {
    $db = rb_db();

    $sql = "
    CREATE TABLE IF NOT EXISTS saved_reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        config LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        status TINYINT(1) NOT NULL DEFAULT 1
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ";

    mysqli_query($db, $sql);

    $columns = [
        "description" => "ALTER TABLE saved_reports ADD COLUMN description TEXT NULL",
        "status" => "ALTER TABLE saved_reports ADD COLUMN status TINYINT(1) NOT NULL DEFAULT 1",
        "created_by" => "ALTER TABLE saved_reports ADD COLUMN created_by VARCHAR(255) NOT NULL DEFAULT ''",
        "deleted_at" => "ALTER TABLE saved_reports ADD COLUMN deleted_at DATETIME NULL",
        "share_token" => "ALTER TABLE saved_reports ADD COLUMN share_token VARCHAR(64) NULL"
    ];

    foreach ($columns as $column => $alter) {
        $check = mysqli_query($db, "SHOW COLUMNS FROM saved_reports LIKE '" . mysqli_real_escape_string($db, $column) . "'");
        if ($check && mysqli_num_rows($check) === 0) {
            mysqli_query($db, $alter);
        }
    }
}

function rb_table_has_column($db, $table, $column) {
    $safeTable = mysqli_real_escape_string($db, $table);
    $safeColumn = mysqli_real_escape_string($db, $column);
    $res = mysqli_query($db, "SHOW COLUMNS FROM `$safeTable` LIKE '$safeColumn'");
    return $res && mysqli_num_rows($res) > 0;
}

function rb_translate_filter_operator($op, $value) {
    $op = strtolower(trim((string)$op));

    switch ($op) {
        case 'eq': return ['sql' => '= ?', 'params' => [$value]];
        case 'neq': return ['sql' => '!= ?', 'params' => [$value]];
        case 'contains': return ['sql' => 'LIKE ?', 'params' => ['%' . $value . '%']];
        case 'starts': return ['sql' => 'LIKE ?', 'params' => [$value . '%']];
        case 'ends': return ['sql' => 'LIKE ?', 'params' => ['%' . $value]];
        case 'gt': return ['sql' => '> ?', 'params' => [$value]];
        case 'lt': return ['sql' => '< ?', 'params' => [$value]];
        case 'gte': return ['sql' => '>= ?', 'params' => [$value]];
        case 'lte': return ['sql' => '<= ?', 'params' => [$value]];
        case 'isnull': return ['sql' => 'IS NULL', 'params' => []];
        case 'notnull': return ['sql' => 'IS NOT NULL', 'params' => []];
        case 'between':
            $parts = is_array($value) ? $value : explode(',', (string)$value, 2);
            $v1 = isset($parts[0]) ? trim((string)$parts[0]) : '';
            $v2 = isset($parts[1]) ? trim((string)$parts[1]) : '';
            if ($v1 === '' || $v2 === '') {
                return ['sql' => '= ?', 'params' => [$value]];
            }
            return ['sql' => 'BETWEEN ? AND ?', 'params' => [$v1, $v2]];
        case '=':
        case '!=':
        case '>':
        case '<':
        case '>=':
        case '<=':
            return ['sql' => $op . ' ?', 'params' => [$value]];
        case 'like':
            return ['sql' => 'LIKE ?', 'params' => [$value]];
        case 'not like':
            return ['sql' => 'NOT LIKE ?', 'params' => [$value]];
        case 'in':
        case 'not in':
            $items = is_array($value) ? $value : array_map('trim', explode(',', (string)$value));
            $items = array_values(array_filter($items, function($v) { return $v !== ''; }));
            if (!$items) {
                return ['sql' => '= ?', 'params' => [$value]];
            }
            $placeholders = implode(', ', array_fill(0, count($items), '?'));
            return ['sql' => strtoupper($op) . ' (' . $placeholders . ')', 'params' => $items];
        default:
            return ['sql' => '= ?', 'params' => [$value]];
    }
}

function rb_table_has_numprocesso($db, $table) {
    $safe = mysqli_real_escape_string($db, $table);
    $res = mysqli_query($db, "SHOW COLUMNS FROM `$safe` LIKE 'NumProcesso'");
    return $res && mysqli_num_rows($res) > 0;
}

function rb_build_query_parts($tabelas, $campos, $filtros, $order) {
    if (!$tabelas || !$campos) {
        throw new Exception("Sem tabelas ou campos selecionados.");
    }

    $db = rb_db();
    $base = $tabelas[0];

    if (!validateIdentifier($base)) {
        throw new Exception("Tabela inválida.");
    }

    if (!rb_table_has_numprocesso($db, $base)) {
        throw new Exception("A tabela base tem de ter o campo NumProcesso.");
    }

    $selects = [];
    $joins = '';
    $whereParts = [];
    $params = [];
    $joined = array($base => true);

    foreach ($tabelas as $tab) {
        if (!validateIdentifier($tab) || $tab === $base || isset($joined[$tab])) continue;
        if (!rb_table_has_numprocesso($db, $tab)) continue;
        $joins .= " LEFT JOIN `$tab` ON `$base`.`NumProcesso` = `$tab`.`NumProcesso` ";
        $joined[$tab] = true;
    }

    foreach ($campos as $c) {
        if (!is_string($c) || strpos($c, '.') === false) continue;
        list($tab, $col) = explode('.', $c, 2);
        if (!validateIdentifier($tab) || !validateIdentifier($col)) continue;
        $selects[] = "`$tab`.`$col` AS `" . $tab . "_" . $col . "`";
    }

    if (!$selects) {
        throw new Exception("Sem campos válidos para apresentar.");
    }

    foreach ($filtros as $f) {
        if (!isset($f['campo']) || !isset($f['op'])) continue;
        if (strpos($f['campo'], '.') === false) continue;

        list($tab, $col) = explode('.', $f['campo'], 2);
        if (!validateIdentifier($tab) || !validateIdentifier($col)) continue;

        $value = isset($f['val']) ? $f['val'] : '';
        $translated = rb_translate_filter_operator($f['op'], $value);
        $whereParts[] = "`$tab`.`$col` " . $translated['sql'];

        foreach ($translated['params'] as $p) {
            $params[] = $p;
        }
    }

    $where = $whereParts ? (' WHERE ' . implode(' AND ', $whereParts)) : '';

    $orderSql = '';
    if (!empty($order['field']) && strpos($order['field'], '.') !== false) {
        list($tab, $col) = explode('.', $order['field'], 2);
        if (validateIdentifier($tab) && validateIdentifier($col)) {
            $dir = strtoupper((string)$order['dir']) === 'DESC' ? 'DESC' : 'ASC';
            $orderSql = " ORDER BY `$tab`.`$col` $dir";
        }
    }

    return [
        'base' => $base,
        'joins' => $joins,
        'where' => $where,
        'selects' => $selects,
        'params' => $params,
        'order' => $orderSql
    ];
}
