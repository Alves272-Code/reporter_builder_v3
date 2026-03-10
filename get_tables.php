<?php
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
try {
    rb_db();
    $traducao = require __DIR__ . '/traducao.php';

    $tabelas_internas = array(
        'pais','ponderacoes','utilizadores','logs_sistema','registo_ativos',
        'registolimpeza','tipologia','valores','saved_reports'
    );

    $tabelas = array();
    foreach ($traducao as $id => $info) {
        if (!in_array($id, $tabelas_internas, true)) {
            $tabelas[] = array(
                'id' => $id,
                'text' => isset($info['tabela']) ? $info['tabela'] : $id,
                'descricao' => isset($info['descricao']) ? $info['descricao'] : 'Sem descrição'
            );
        }
    }

    usort($tabelas, function($a, $b) {
        return strcmp($a['text'], $b['text']);
    });

    echo json_encode($tabelas, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(array('success' => false, 'message' => $e->getMessage()), JSON_UNESCAPED_UNICODE);
}
?>
