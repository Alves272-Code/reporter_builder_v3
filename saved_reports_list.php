<?php
require_once __DIR__ . '/config.php';
$error = null;
$reports = array();
$shared_reports = array();

try {
    rb_ensure_saved_reports_table();
    $db = rb_db();
    $user = rb_require_auth('page');

    rb_ensure_report_shares_table();

    $reports = rb_prepare_and_fetch_all(
        $db,
        "SELECT id, name, description, created_at FROM saved_reports WHERE created_by = ? AND status = 1 ORDER BY created_at DESC",
        array($user)
    );

    $shared_reports = rb_prepare_and_fetch_all(
        $db,
        "SELECT sr.id, sr.name, sr.description, sr.created_at, sr.created_by
         FROM saved_reports sr
         INNER JOIN report_shares rs ON rs.report_id = sr.id
         WHERE rs.shared_with = ? AND rs.active = 1 AND sr.status = 1
         ORDER BY sr.created_at DESC",
        array($user)
    );
} catch (Exception $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios Guardados - Habitar S. João</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/font-awesome.min.css">
    <link rel="stylesheet" href="../css/menu-style.css">
    <link rel="stylesheet" href="../css/gestao.css">
    <style>
        :root { --laranja-menu:#e95420; --laranja-escuro:#c34113; --laranja-suave:#ffefea; --cinza-escuro:#2C3E50; --cinza-claro:#F8F9FA; }
        body { background-color:#f8f9fa; display:flex; flex-direction:column; min-height:100vh; }
        .menu-container { flex:1; width:100%; max-width:1400px; margin:0 auto; padding:0 15px; }
        .process-header-main { background: linear-gradient(135deg, var(--laranja-menu) 0%, var(--laranja-escuro) 100%); color:white; padding:25px; border-radius:10px; margin:20px 0; box-shadow:0 4px 15px rgba(233,84,32,.15); }
        .process-card { background:white; border-radius:10px; box-shadow:0 3px 10px rgba(0,0,0,.08); border:1px solid #e0e0e0; margin-bottom:20px; overflow:hidden; }
        .process-card-header { background:var(--cinza-claro); padding:15px 20px; border-bottom:1px solid #dee2e6; display:flex; justify-content:space-between; align-items:center; }
        .process-card-header h4 { margin:0; font-size:1.1rem; color:var(--cinza-escuro); font-weight:600; }
        .btn-action { padding:8px 15px; border-radius:6px; border:none; font-weight:600; display:inline-flex; align-items:center; gap:8px; cursor:pointer; transition:all .2s ease; text-decoration:none; font-size:.9rem; white-space:nowrap; }
        .btn-action-branco { background:rgba(255,255,255,.9); color:var(--laranja-menu); }
        .btn-action-branco:hover { background:white; color:var(--laranja-escuro); transform:translateY(-2px); }
        .report-card { border:1px solid #e9ecef; border-radius:10px; padding:18px; height:100%; background:white; }
        .report-card:hover { box-shadow:0 5px 18px rgba(0,0,0,.08); border-color:#f0b49b; }
        .menu-footer { margin-top:auto; border-top:1px solid #eee; padding:20px 0; text-align:center; width:100%; }
        .share-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,.45); display:none; align-items:center; justify-content:center; z-index:9999; }
        .share-modal-box { width:min(560px, 92vw); background:#fff; border-radius:10px; padding:16px; box-shadow:0 10px 35px rgba(0,0,0,.2); }
        .share-item { display:flex; justify-content:space-between; gap:8px; align-items:center; border:1px solid #e9ecef; border-radius:8px; padding:8px 10px; margin-bottom:8px; }
    </style>
</head>
<body>
<header class="menu-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="navbar-brand" href="../menu.php"><h3>Habitar S. João</h3></a>
            <div class="collapse navbar-collapse show">
                <ul class="navbar-nav ml-auto">
                    <li class="nav-item"><a class="nav-link" href="../menu.php"><i class="fa fa-home"></i> Menu Principal</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php"><i class="fa fa-bar-chart"></i> Report Builder</a></li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../sair.php"><i class="fa fa-sign-out"></i> Sair</a></li>
                </ul>
            </div>
        </div>
    </nav>
</header>

<div class="menu-container">
    <div class="process-header-main">
        <div class="d-flex justify-content-between align-items-center flex-wrap">
            <div>
                <h2 style="font-weight:700; margin:0;"><i class="fa fa-folder-open"></i> Os meus relatórios</h2>
                <p class="mb-0 mt-1" style="opacity:.9;">Cada utilizador vê apenas os seus relatórios ativos.</p>
            </div>
            <div class="d-flex flex-wrap" style="gap:5px;">
                <a href="saved_reports_deleted.php" class="btn-action btn-action-branco"><i class="fa fa-trash"></i> Apagados</a>
                <a href="index.php" class="btn-action btn-action-branco"><i class="fa fa-plus"></i> Novo relatório</a>
                <a href="../menu.php" class="btn-action btn-action-branco"><i class="fa fa-arrow-left"></i> Voltar</a>
            </div>
        </div>
    </div>

    <div class="process-card">
        <div class="process-card-header"><h4><i class="fa fa-list"></i> Lista de relatórios</h4></div>
        <div class="p-4">
            <?php if ($error) { ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php } elseif (!$reports) { ?>
                <div class="text-center text-muted py-5">
                    <i class="fa fa-folder-open" style="font-size:48px; opacity:.35;"></i>
                    <p class="mt-3 mb-0">Ainda não existem relatórios guardados.</p>
                </div>
            <?php } else { ?>
                <div class="row">
                    <?php foreach ($reports as $report) { ?>
                        <div class="col-md-6 mb-3">
                            <div class="report-card">
                                <h5 class="mb-2"><i class="fa fa-file-text-o text-primary"></i> <?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($report['description'] ? $report['description'] : 'Sem descrição.', ENT_QUOTES, 'UTF-8'); ?></p>
                                <small class="text-muted"><i class="fa fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?></small>
                                <div class="mt-3 d-flex flex-wrap" style="gap:8px;">
                                    <button class="btn btn-sm btn-outline-primary" onclick="carregarRelatorio(<?php echo (int)$report['id']; ?>)"><i class="fa fa-folder-open"></i> Carregar</button>
                                    <button class="btn btn-sm btn-outline-secondary" onclick="partilharRelatorio(<?php echo (int)$report['id']; ?>)"><i class="fa fa-share-alt"></i> Partilhar</button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="eliminarRelatorio(<?php echo (int)$report['id']; ?>)"><i class="fa fa-trash"></i> Mover para apagados</button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>

    <div class="process-card">
        <div class="process-card-header"><h4><i class="fa fa-share-alt"></i> Partilhados comigo</h4></div>
        <div class="p-4">
            <?php if (!$shared_reports) { ?>
                <div class="text-center text-muted py-4">Sem relatórios partilhados consigo.</div>
            <?php } else { ?>
                <div class="row">
                    <?php foreach ($shared_reports as $report) { ?>
                        <div class="col-md-6 mb-3">
                            <div class="report-card">
                                <h5 class="mb-2"><i class="fa fa-file-text-o text-primary"></i> <?php echo htmlspecialchars($report['name'], ENT_QUOTES, 'UTF-8'); ?></h5>
                                <p class="text-muted mb-2"><?php echo htmlspecialchars($report['description'] ? $report['description'] : 'Sem descrição.', ENT_QUOTES, 'UTF-8'); ?></p>
                                <small class="text-muted"><i class="fa fa-user"></i> Dono: <?php echo htmlspecialchars($report['created_by'], ENT_QUOTES, 'UTF-8'); ?></small><br>
                                <small class="text-muted"><i class="fa fa-calendar"></i> <?php echo date('d/m/Y H:i', strtotime($report['created_at'])); ?></small>
                                <div class="mt-3">
                                    <button class="btn btn-sm btn-outline-primary" onclick="carregarRelatorio(<?php echo (int)$report['id']; ?>, true)"><i class="fa fa-folder-open"></i> Ver relatório</button>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<div id="share-modal" class="share-modal-backdrop">
    <div class="share-modal-box">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0"><i class="fa fa-share-alt"></i> Partilhar relatório</h5>
            <button class="btn btn-sm btn-outline-secondary" onclick="closeShareModal()">Fechar</button>
        </div>
        <div class="form-group">
            <label>Utilizador ativo</label>
            <select id="share-user-select" class="form-control"></select>
        </div>
        <button class="btn btn-sm btn-primary" onclick="confirmShare()"><i class="fa fa-check"></i> Confirmar partilha</button>
        <hr>
        <h6>Partilhas ativas</h6>
        <div id="share-current-list" class="mt-2"></div>
    </div>
</div>

<footer class="menu-footer">
    <div class="footer-content">
        <p>Contactos: 256 818 074 | geral@habitarsjoao.pt</p>
        <p>R. do Poder Local 347, 3700-225 S. João da Madeira</p>
        <p>&copy; <?php echo date('Y'); ?> Habitar S. João - Todos os direitos reservados</p>
    </div>
</footer>

<script>
let currentShareReportId = 0;

function carregarRelatorio(id, shared) {
    var url = 'index.php?report_id=' + encodeURIComponent(id);
    if (shared) url += '&shared=1';
    window.location.href = url;
}

function closeShareModal() {
    document.getElementById('share-modal').style.display = 'none';
    currentShareReportId = 0;
}

function partilharRelatorio(id) {
    currentShareReportId = id;
    document.getElementById('share-modal').style.display = 'flex';
    loadActiveUsers();
    loadCurrentShares();
}

function loadActiveUsers() {
    fetch('active_users.php')
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.success) throw new Error(data.message || 'Erro ao carregar utilizadores');
            const sel = document.getElementById('share-user-select');
            sel.innerHTML = '';
            (data.users || []).forEach(function(u){
                const opt = document.createElement('option');
                opt.value = u.email;
                opt.textContent = (u.name || u.email) + ' (' + u.email + ')';
                sel.appendChild(opt);
            });
            if (!sel.options.length) {
                const opt = document.createElement('option');
                opt.value = '';
                opt.textContent = 'Sem utilizadores ativos disponíveis';
                sel.appendChild(opt);
            }
        })
        .catch(function(err){ alert(err.message); });
}

function loadCurrentShares() {
    if (!currentShareReportId) return;
    fetch('share_report.php?id=' + encodeURIComponent(currentShareReportId))
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.success) throw new Error(data.message || 'Erro ao carregar partilhas');
            const box = document.getElementById('share-current-list');
            box.innerHTML = '';
            const shares = data.shares || [];
            if (!shares.length) {
                box.innerHTML = '<div class="text-muted">Sem partilhas ativas.</div>';
                return;
            }
            shares.forEach(function(s){
                const div = document.createElement('div');
                div.className = 'share-item';
                const left = document.createElement('div');
                left.textContent = s.shared_with;
                const btn = document.createElement('button');
                btn.className = 'btn btn-sm btn-outline-danger';
                btn.innerHTML = '<i class="fa fa-times"></i> Cancelar';
                btn.onclick = function(){ cancelarPartilha(s.shared_with); };
                div.appendChild(left);
                div.appendChild(btn);
                box.appendChild(div);
            });
        })
        .catch(function(err){ alert(err.message); });
}

function confirmShare() {
    if (!currentShareReportId) return;
    const sel = document.getElementById('share-user-select');
    const email = sel.value || '';
    if (!email) {
        alert('Selecione um utilizador ativo.');
        return;
    }

    fetch('share_report.php?id=' + encodeURIComponent(currentShareReportId), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ shared_with: email })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (!data.success) throw new Error(data.message || 'Erro ao partilhar');
        alert('Relatório partilhado com sucesso.');
        loadCurrentShares();
    })
    .catch(function(err){ alert(err.message); });
}

function cancelarPartilha(email) {
    if (!currentShareReportId) return;
    if (!confirm('Cancelar partilha com ' + email + '?')) return;

    fetch('revoke_report_share.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ report_id: currentShareReportId, shared_with: email })
    })
    .then(function(r){ return r.json(); })
    .then(function(data){
        if (!data.success) throw new Error(data.message || 'Erro ao cancelar partilha');
        loadCurrentShares();
    })
    .catch(function(err){ alert(err.message); });
}

function eliminarRelatorio(id) {
    if (!confirm('Pretende mover este relatório para apagados?')) return;
    fetch('delete_report.php?id=' + id)
        .then(function(r){ return r.json(); })
        .then(function(data){
            if (!data.success) throw new Error(data.message || 'Erro ao eliminar');
            location.reload();
        })
        .catch(function(err){ alert(err.message); });
}
</script>
</body>
</html>
