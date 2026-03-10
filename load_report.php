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

    $row = rb_prepare_and_fetch_one(
        $db,
        "SELECT config, status FROM saved_reports WHERE id = ? AND created_by = ?",
        array($id, $user)
    );

    if (!$row || !array_key_exists('config', $row)) {
        rb_json(array('success' => false, 'message' => 'Relatório não encontrado'), 404);
    }

    $rawConfig = $row['config'];
    $config = json_decode($rawConfig, true);
    if ($config === null && is_string($rawConfig)) {
        $config = $rawConfig;
    }

    rb_json(array('success' => true, 'config' => $config, 'rawConfig' => $rawConfig, 'status' => isset($row['status']) ? (int)$row['status'] : 1), 200);
} catch (Throwable $e) {
    rb_json(array('success' => false, 'message' => $e->getMessage()), 500);
}