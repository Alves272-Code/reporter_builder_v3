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

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        rb_json(array('success' => false, 'message' => 'Método inválido'), 405);
    }

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        rb_json(array('success' => false, 'message' => 'ID inválido'), 400);
    }

    $row = rb_prepare_and_fetch_one(
        $db,
        "SELECT id, share_token FROM saved_reports WHERE id = ? AND created_by = ?",
        array($id, $user)
    );

    if (!$row) {
        rb_json(array('success' => false, 'message' => 'Apenas o proprietário pode partilhar este relatório'), 403);
    }

    $token = isset($row['share_token']) ? trim((string)$row['share_token']) : '';
    if ($token === '') {
        $token = bin2hex(random_bytes(16));
        rb_prepare_and_exec(
            $db,
            "UPDATE saved_reports SET share_token = ? WHERE id = ? AND created_by = ?",
            array($token, $id, $user)
        );
    }

    $url = rb_base_url() . '/index.php?report_id=' . $id . '&token=' . urlencode($token);
    rb_json(array('success' => true, 'share_url' => $url), 200);
} catch (Throwable $e) {
    rb_json(array('success' => false, 'message' => $e->getMessage()), 500);
}
