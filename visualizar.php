<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

try {

    $db = rb_db();

    $raw = file_get_contents('php://input');

    if (!$raw) {
        echo json_encode([
            "success" => false,
            "message" => "Pedido vazio"
        ]);
        exit;
    }

    $data = json_decode($raw, true);

    if (!is_array($data)) {
        echo json_encode([
            "success" => false,
            "message" => "JSON inválido"
        ]);
        exit;
    }

    $tabelas = $data['tabelas'] ?? [];
    $campos = $data['campos'] ?? [];

    if (!$tabelas || !$campos) {
        echo json_encode([
            "success" => false,
            "message" => "Sem tabelas ou campos"
        ]);
        exit;
    }

    $parts = rb_build_query_parts(
        $tabelas,
        $campos,
        $data['filtros'] ?? [],
        $data['order'] ?? []
    );

    $page = max(1, intval($data['page'] ?? 1));
    $limit = max(1, intval($data['limit'] ?? 50));
    $offset = ($page - 1) * $limit;

    $sql = "SELECT " . implode(', ', $parts['selects']) .
        " FROM `" . $parts['base'] . "` " .
        $parts['joins'] . " " .
        $parts['where'] . " " .
        $parts['order'] .
        " LIMIT $limit OFFSET $offset";

    $rows = rb_prepare_and_fetch_all($db, $sql, $parts['params']);

    echo json_encode([
        "success" => true,
        "data" => $rows,
        "meta" => [
            "page" => $page,
            "limit" => $limit,
            "totalPages" => 1
        ]
    ]);

} catch (Throwable $e) {

    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}