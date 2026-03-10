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

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        rb_json(array('success' => false, 'message' => 'ID inválido'), 400);
    }

    rb_prepare_and_exec(
        $db,
        "UPDATE saved_reports SET status = 1, deleted_at = NULL WHERE id = ? AND created_by = ?",
        array($id, $user)
    );

    rb_json(array('success' => true, 'message' => 'Relatório restaurado com sucesso'), 200);
} catch (Throwable $e) {
    rb_json(array('success' => false, 'message' => $e->getMessage()), 500);
}
