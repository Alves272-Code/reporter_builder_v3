<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

try {
    rb_ensure_saved_reports_table();
    rb_ensure_report_shares_table();
    $db = rb_db();
    $user = rb_current_user();

    if ($user === '') {
        rb_json(array('success' => false, 'message' => 'Utilizador não identificado'), 401);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rb_json(array('success' => false, 'message' => 'Método inválido'), 405);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        rb_json(array('success' => false, 'message' => 'JSON inválido'), 400);
    }

    $id = isset($data['report_id']) ? (int)$data['report_id'] : 0;
    $sharedWith = isset($data['shared_with']) ? trim((string)$data['shared_with']) : '';

    if ($id <= 0 || $sharedWith === '') {
        rb_json(array('success' => false, 'message' => 'Parâmetros inválidos'), 400);
    }

    $owner = rb_prepare_and_fetch_one($db, "SELECT id FROM saved_reports WHERE id = ? AND created_by = ?", array($id, $user));
    if (!$owner) {
        rb_json(array('success' => false, 'message' => 'Só o proprietário pode cancelar partilhas'), 403);
    }

    rb_prepare_and_exec($db, "UPDATE report_shares SET active = 0 WHERE report_id = ? AND shared_with = ? AND shared_by = ?", array($id, $sharedWith, $user));
    rb_json(array('success' => true, 'message' => 'Partilha cancelada com sucesso'), 200);
} catch (Throwable $e) {
    rb_json(array('success' => false, 'message' => $e->getMessage()), 500);
}
