<?php
require_once __DIR__ . '/config.php';
$user_name = rb_current_user_name();
$rb_has_db = empty($rb_db_error);
$rb_boot_report = null;
$rb_boot_report_error = null;

if ($rb_has_db && isset($_GET['report_id']) && (int)$_GET['report_id'] > 0) {
    try {
        rb_ensure_saved_reports_table();
        $db = rb_db();
        $user = rb_current_user();
        $report_id = (int)$_GET['report_id'];
        $share_token = isset($_GET['token']) ? trim((string)$_GET['token']) : '';

        $sql = "SELECT * FROM saved_reports WHERE id = " . (int)$report_id . " LIMIT 1";
        $res = mysqli_query($db, $sql);
        if ($res === false) {
            throw new Exception(mysqli_error($db));
        }
        $row = mysqli_fetch_assoc($res);

        if (!$row) {
            $rb_boot_report_error = 'Relatório não encontrado';
        } else {
            $hasCreatedBy = array_key_exists('created_by', $row);
            $hasShareToken = array_key_exists('share_token', $row);
            $isOwner = ($hasCreatedBy && $user !== '' && (string)$row['created_by'] === $user);
            $isSharedAccess = ($share_token !== '' && $hasShareToken && hash_equals((string)$row['share_token'], $share_token));
            $isSharedWithUser = false;
            if ($user !== '') {
                rb_ensure_report_shares_table();
                $shareRow = rb_prepare_and_fetch_one(
                    $db,
                    "SELECT id FROM report_shares WHERE report_id = ? AND shared_with = ? AND active = 1",
                    array($report_id, $user)
                );
                $isSharedWithUser = $shareRow ? true : false;
            }
            $canAccess = $isSharedAccess || $isOwner || $isSharedWithUser || (!$hasCreatedBy && $user !== '');

            if (!$canAccess) {
                $rb_boot_report_error = 'Relatório não encontrado';
            } else {
                $decoded = json_decode($row['config'], true);
                $rb_boot_report = array(
                    'reportId' => (int)$row['id'],
                    'name' => (string)$row['name'],
                    'description' => isset($row['description']) ? (string)$row['description'] : '',
                    'status' => isset($row['status']) ? (int)$row['status'] : 1,
                    'createdAt' => isset($row['created_at']) ? (string)$row['created_at'] : '',
                    'isOwner' => $isOwner,
                    'config' => $decoded === null ? $row['config'] : $decoded,
                    'rawConfig' => $row['config']
                );
            }
        }
    } catch (Throwable $e) {
        $rb_boot_report_error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Builder - Habitar S. João</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
<link rel="stylesheet" href="../assets/css/font-awesome.min.css">
<link rel="stylesheet" href="../css/menu-style.css">
<link rel="stylesheet" href="../css/gestao.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    
    
    <style>
        :root { --laranja-menu:#e95420; --laranja-escuro:#c34113; --laranja-suave:#ffefea; --cinza-escuro:#2C3E50; --cinza-claro:#F8F9FA; }
        body { background-color:#f8f9fa; display:flex; flex-direction:column; min-height:100vh; }
        .menu-container { flex:1; width:100%; max-width:1400px; margin:0 auto; padding:0 15px; }
        .process-header-main { background:linear-gradient(135deg,var(--laranja-menu) 0%,var(--laranja-escuro) 100%); color:#fff; padding:25px; border-radius:10px; margin:20px 0; box-shadow:0 4px 15px rgba(233,84,32,.15); }
        .process-card { background:#fff; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,.08); border:1px solid #e0e0e0; margin-bottom:20px; overflow:hidden; }
        .process-card-header { background:var(--cinza-claro); padding:15px 20px; border-bottom:1px solid #dee2e6; display:flex; justify-content:space-between; align-items:center; gap:10px; }
        .process-card-header h4 { margin:0; font-size:1.1rem; color:var(--cinza-escuro); font-weight:600; }
        .btn-action { padding:8px 15px; border-radius:6px; border:none; font-weight:600; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:all .2s ease; text-decoration:none; font-size:.9rem; white-space:nowrap; }
        .btn-action-laranja { background:var(--laranja-menu); color:#fff; }
        .btn-action-laranja:hover { background:var(--laranja-escuro); color:#fff; transform:translateY(-2px); }
        .btn-action-branco { background:rgba(255,255,255,.9); color:var(--laranja-menu); }
        .btn-action-branco:hover { background:#fff; color:var(--laranja-escuro); transform:translateY(-2px); }
        .card-body-section { padding:20px; }
        .builder-grid { display:grid; grid-template-columns:360px 1fr; gap:20px; }
        .field-item { padding:.45rem .6rem; border-radius:6px; transition:all .2s; cursor:pointer; border:1px solid transparent; }
        .field-item:hover { background:#f8f9fa; border-color:#e9ecef; }
        .field-item.selected-field { background:#fff0ea; border-color:#f0b49b; color:#8a2e0a; }
        .fields-list { max-height:260px; overflow:auto; }
        #active-fields-summary { min-height:72px; padding:.75rem; background:#f8f9fa; border:1px solid #e9ecef; border-radius:8px; }
        .condition-row { display:flex; gap:8px; margin-bottom:8px; flex-wrap:wrap; }
        .preview-placeholder { text-align:center; color:#6c757d; padding:60px 20px; }
        .table-preview th { background-color:var(--laranja-suave); color:var(--cinza-escuro); border-bottom:2px solid var(--laranja-menu); }
        .menu-footer { margin-top:auto; border-top:1px solid #eee; padding:20px 0; text-align:center; width:100%; }
        .badge-resumo { background:#e9f2ff; color:#0c5460; border-radius:14px; padding:6px 10px; margin:0 6px 6px 0; display:inline-block; }
        .select2-container { width:100% !important; }
        .select2-container--default .select2-selection--single { height:38px; border:1px solid #ced4da; border-radius:.25rem; }
        .select2-container--default .select2-selection--single .select2-selection__rendered { line-height:36px; }
        .select2-container--default .select2-selection--single .select2-selection__arrow { height:36px; }
        .status-help { border-left:4px solid #ffc107; background:#fff8e1; color:#856404; padding:14px 16px; border-radius:8px; margin-bottom:20px; }
        #preview, .builder-grid > div, .process-card, .process-card .card-body-section { min-width:0; }
        #preview { width:100%; max-width:100%; overflow:hidden; }
        #preview .table-responsive { display:block; width:100%; max-width:100%; overflow-x:auto; overflow-y:hidden; -webkit-overflow-scrolling:touch; }
        #preview .table-preview { width:max-content; min-width:100%; table-layout:auto; }
        #preview .table-preview th, #preview .table-preview td { white-space:nowrap; }
        @media (max-width:991px) { .builder-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<header class="menu-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../menu.php"><h3>Habitar S. João</h3></a>
            <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="../menu.php"><i class="fa fa-home"></i> Menu Principal</a></li>
                    <li class="nav-item"><a class="nav-link" href="../appointments.php"><i class="fa fa-calendar-check-o"></i> Atendimentos</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../sair.php"><i class="fa fa-sign-out"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<div class="menu-container">
    <div class="process-header-main">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div class="mb-2 mb-md-0">
                <h2 style="font-weight:700; margin:0;"><i class="fa fa-bar-chart"></i> Report Builder</h2>
                <p class="mb-0 mt-1" style="opacity:.9;">Construtor de relatórios integrado no site, com o mesmo layout dos atendimentos.</p>
            </div>
            <div class="d-flex flex-wrap" style="gap:5px;">
                <a href="saved_reports_list.php" class="btn-action btn-action-branco"><i class="fa fa-folder-open"></i> Relatórios guardados</a>
                <a href="saved_reports_deleted.php" class="btn-action btn-action-branco"><i class="fa fa-trash"></i> Relatórios apagados</a>
                <a href="../menu.php" class="btn-action btn-action-branco"><i class="fa fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>

    <?php if (!$rb_has_db) { ?>
        <div class="status-help"><strong>Atenção:</strong> <?php echo htmlspecialchars($rb_db_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <?php if ($rb_boot_report_error) { ?>
        <div class="status-help"><strong>Atenção:</strong> <?php echo htmlspecialchars($rb_boot_report_error, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } elseif ($rb_boot_report) { ?>
        <div class="status-help"><strong>A carregar relatório:</strong> <?php echo htmlspecialchars($rb_boot_report['name'], ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } ?>

    <div class="builder-grid">
        <div>
            <div class="process-card">
                <div class="process-card-header"><h4><i class="fa fa-database"></i> Tabelas</h4></div>
                <div class="card-body-section">
                    <label class="font-weight-bold mb-2">Adicionar tabela</label>
                    <div class="d-flex" style="gap:8px;">
                        <select id="table-select" class="form-control"></select>
                        <button type="button" onclick="addTable()" class="btn btn-action-laranja"><i class="fa fa-plus"></i></button>
                    </div>
                    <div class="mt-4">
                        <label class="font-weight-bold mb-2">Colunas selecionadas</label>
                        <div id="active-fields-summary"><span class="text-muted font-italic">Nenhuma coluna selecionada</span></div>
                    </div>
                </div>
            </div>

            <div class="process-card">
                <div class="process-card-header">
                    <h4><i class="fa fa-table"></i> Campos por tabela</h4>
                    <small class="text-muted"><?php echo htmlspecialchars($user_name, ENT_QUOTES, 'UTF-8'); ?></small>
                </div>
                <div class="card-body-section">
                    <div class="accordion" id="tablesAccordion"></div>
                </div>
            </div>
        </div>

        <div>
            <div class="process-card">
                <div class="process-card-header">
                    <h4><i class="fa fa-filter"></i> Filtros e ordenação</h4>
                    <div class="d-flex flex-wrap" style="gap:6px;">
                        <button type="button" onclick="addCondition()" class="btn btn-sm btn-outline-secondary"><i class="fa fa-plus"></i> Filtro</button>
                        <button type="button" onclick="clearAll()" class="btn btn-sm btn-outline-danger"><i class="fa fa-trash"></i> Limpar</button>
                    </div>
                </div>
                <div class="card-body-section">
                    <div id="conditions-container" class="mb-3"></div>
                    <div class="row">
                        <div class="col-md-8 form-group">
                            <label>Ordenar por</label>
                            <select id="order_field" class="form-control form-control-sm"><option value="">Selecione...</option></select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>Sentido</label>
                            <select id="order_dir" class="form-control form-control-sm"><option value="ASC">Crescente</option><option value="DESC">Decrescente</option></select>
                        </div>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                        <button type="button" onclick="refreshPreview()" class="btn btn-action-laranja"><i class="fa fa-eye"></i> Visualizar</button>
                        <button type="button" onclick="exportarExcel()" class="btn btn-secondary"><i class="fa fa-file-excel-o"></i> Excel</button>
                        <button type="button" onclick="exportarCSV()" class="btn btn-secondary"><i class="fa fa-file-text-o"></i> CSV</button>
                        <button type="button" id="save-report-btn" onclick="saveReport()" class="btn btn-info text-white"><i class="fa fa-save"></i> Guardar</button>
                    </div>
                </div>
            </div>

            <div class="process-card">
                <div class="process-card-header">
                    <h4><i class="fa fa-list"></i> Pré-visualização</h4>
                    <div id="preview-controls" class="d-flex flex-wrap align-items-center" style="gap:8px;"></div>
                </div>
                <div class="card-body-section">
                    <div id="preview" class="preview-placeholder">
                        <i class="fa fa-table fa-3x mb-3"></i>
                        <p class="mb-0">Escolha tabelas, selecione campos e carregue em visualizar.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<footer class="menu-footer">
    <div class="footer-content">
        <p>Contactos: 256 818 074 | geral@habitarsjoao.pt</p>
        <p>R. do Poder Local 347, 3700-225 S. João da Madeira</p>
        <p>&copy; <?php echo date('Y'); ?> Habitar S. João - Todos os direitos reservados</p>
    </div>
</footer>

<script src="../assets/js/vendor/jquery-1.12.4.min.js"></script>
<script src="../assets/js/popper.min.js"></script>
<script src="../assets/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="builder.js?v=11"></script>
<script>
window.RB_HAS_DB = <?php echo $rb_has_db ? 'true' : 'false'; ?>;
window.RB_BOOT_REPORT = <?php echo $rb_boot_report ? json_encode($rb_boot_report, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : 'null'; ?>;
</script>
</body>
</html>