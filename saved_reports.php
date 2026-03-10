<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    rb_ensure_saved_reports_table();
    $db = rb_db();
    $user = rb_current_user();

    if ($user === '') {
        rb_json(array('success' => false, 'message' => 'Utilizador não identificado'), 401);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $reports = rb_prepare_and_fetch_all(
            $db,
            "SELECT id, name, description, created_at FROM saved_reports WHERE created_by = ? AND status = 1 ORDER BY created_at DESC",
            array($user)
        );
        rb_json(array('success' => true, 'reports' => $reports), 200);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rb_json(array('success' => false, 'message' => 'Método inválido'), 405);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    if (!is_array($data)) {
        rb_json(array('success' => false, 'message' => 'JSON inválido'), 400);
    }

    $name = isset($data['name']) ? trim((string)$data['name']) : '';
    $description = isset($data['description']) ? trim((string)$data['description']) : '';
    $config = isset($data['config']) ? $data['config'] : null;

    if ($name === '') {
        rb_json(array('success' => false, 'message' => 'Indique o nome do relatório'), 400);
    }

    if (!is_array($config)) {
        rb_json(array('success' => false, 'message' => 'Configuração inválida'), 400);
    }

    $jsonConfig = json_encode($config, JSON_UNESCAPED_UNICODE);

    rb_prepare_and_exec(
        $db,
        "INSERT INTO saved_reports (name, description, config, created_by, created_at, status, deleted_at) VALUES (?, ?, ?, ?, NOW(), 1, NULL)",
        array($name, $description, $jsonConfig, $user)
    );

    rb_json(array('success' => true, 'message' => 'Relatório guardado com sucesso'), 200);

} catch (Throwable $e) {
    rb_write_log('saved_reports.php: ' . $e->getMessage());
    rb_json(array('success' => false, 'message' => $e->getMessage()), 500);
}
