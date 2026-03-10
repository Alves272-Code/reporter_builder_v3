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

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        rb_json(array('success' => false, 'message' => 'ID inválido'), 400);
    }

    $owner = rb_prepare_and_fetch_one($db, "SELECT id FROM saved_reports WHERE id = ? AND created_by = ?", array($id, $user));
    if (!$owner) {
        rb_json(array('success' => false, 'message' => 'Apenas o proprietário pode gerir partilhas'), 403);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $shares = rb_prepare_and_fetch_all(
            $db,
            "SELECT shared_with, created_at FROM report_shares WHERE report_id = ? AND shared_by = ? AND active = 1 ORDER BY created_at DESC",
            array($id, $user)
        );
        rb_json(array('success' => true, 'shares' => $shares), 200);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rb_json(array('success' => false, 'message' => 'Método inválido'), 405);
    }

    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        rb_json(array('success' => false, 'message' => 'JSON inválido'), 400);
    }

    $sharedWith = isset($data['shared_with']) ? trim((string)$data['shared_with']) : '';
    if ($sharedWith === '') {
        rb_json(array('success' => false, 'message' => 'Selecione um utilizador para partilhar'), 400);
    }

    if (strcasecmp($sharedWith, $user) === 0) {
        rb_json(array('success' => false, 'message' => 'Não pode partilhar consigo próprio'), 400);
    }

    $users = rb_get_active_users($db, '');
    $allowed = false;
    foreach ($users as $u) {
        if (strcasecmp((string)$u['email'], $sharedWith) === 0) {
            $allowed = true;
            break;
        }
    }

    if (!$allowed) {
        rb_json(array('success' => false, 'message' => 'Utilizador inválido ou inativo'), 400);
    }

    rb_prepare_and_exec(
        $db,
        "INSERT INTO report_shares (report_id, shared_by, shared_with, created_at, active)
         VALUES (?, ?, ?, NOW(), 1)
         ON DUPLICATE KEY UPDATE active = 1, shared_by = VALUES(shared_by), created_at = NOW()",
        array($id, $user, $sharedWith)
    );

    rb_json(array('success' => true, 'message' => 'Relatório partilhado com sucesso'), 200);
} catch (Throwable $e) {
    rb_json(array('success' => false, 'message' => $e->getMessage()), 500);
}
