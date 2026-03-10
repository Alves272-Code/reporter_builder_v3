let selectedTables = [];
let selectedFields = new Set();
let currentPage = 1;
let currentLimit = 50;
let totalPages = 1;
let currentReportId = null;
let currentReportOwner = true;
window.RB_FIELD_META = window.RB_FIELD_META || {};

function escapeHtml(str) {
    return String((str === null || typeof str === 'undefined') ? '' : str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function cssEscape(value) {
    if (window.CSS && typeof window.CSS.escape === 'function') {
        return window.CSS.escape(String(value));
    }
    return String(value).replace(/([ #;?%&,.+*~':"!^$\[\]()=>|\/@])/g, '\\$1');
}

function rbGuessKindFromType(type) {
    type = String(type || '').toLowerCase();
    if (type.indexOf('date') !== -1 || type.indexOf('time') !== -1 || type.indexOf('year') !== -1) return 'date';
    if (type.indexOf('int') !== -1 || type.indexOf('decimal') !== -1 || type.indexOf('float') !== -1 || type.indexOf('double') !== -1) return 'number';
    if (type.indexOf('tinyint(1)') !== -1 || type.indexOf('bit(1)') !== -1 || type.indexOf('bool') !== -1) return 'boolean';
    return 'text';
}

const FRIENDLY_OPERATORS_BY_KIND = {
    text: [
        {label:'é', value:'eq'},
        {label:'não é', value:'neq'},
        {label:'contém', value:'contains'},
        {label:'começa por', value:'starts'},
        {label:'termina com', value:'ends'}
    ],
    number: [
        {label:'é', value:'eq'},
        {label:'não é', value:'neq'},
        {label:'maior que', value:'gt'},
        {label:'menor que', value:'lt'},
        {label:'entre', value:'between'}
    ],
    date: [
        {label:'é', value:'eq'},
        {label:'depois de', value:'gt'},
        {label:'antes de', value:'lt'},
        {label:'entre', value:'between'}
    ],
    boolean: [
        {label:'é', value:'eq'},
        {label:'não é', value:'neq'}
    ]
};

function rbBuildOperatorOptions(fieldKey) {
    const meta = window.RB_FIELD_META[fieldKey] || {};
    const kind = rbGuessKindFromType(meta.type);
    const ops = FRIENDLY_OPERATORS_BY_KIND[kind] || FRIENDLY_OPERATORS_BY_KIND.text;
    return ops.map(function(op){
        return '<option value="' + escapeHtml(op.value) + '">' + escapeHtml(op.label) + '</option>';
    }).join('');
}


function rbTryParseJson(value) {
    if (typeof value !== 'string') return value;
    const trimmed = value.trim();
    if (!trimmed) return value;
    if ((trimmed[0] !== '{' && trimmed[0] !== '[' && trimmed[0] !== '"') || trimmed === 'null') return value;
    try { return JSON.parse(trimmed); } catch (e) { return value; }
}

function rbNormalizeFieldEntry(entry) {
    if (!entry) return null;
    if (typeof entry === 'string') {
        return entry.indexOf('.') !== -1 ? entry : null;
    }
    if (Array.isArray(entry) && entry.length >= 2) {
        return String(entry[0]) + '.' + String(entry[1]);
    }
    if (typeof entry === 'object') {
        const table = entry.tabela || entry.table || entry.tbl || entry.entity || entry.source || '';
        const field = entry.campo || entry.field || entry.name || entry.col || entry.column || '';
        if (table && field) return String(table) + '.' + String(field);
    }
    return null;
}

function rbNormalizeFilters(input) {
    let filters = input;
    for (let i = 0; i < 3; i++) filters = rbTryParseJson(filters);
    if (!filters) return [];
    if (!Array.isArray(filters)) {
        if (typeof filters === 'object') filters = Object.values(filters);
        else return [];
    }
    return filters.map(function(f){
        if (!f) return null;
        if (typeof f === 'string') {
            return { campo: f, op: 'eq', val: '' };
        }
        const campo = f.campo || f.field || f.column || f.col || '';
        const op = f.op || f.operator || f.condition || 'eq';
        const val = Object.prototype.hasOwnProperty.call(f, 'val') ? f.val : (Object.prototype.hasOwnProperty.call(f, 'value') ? f.value : '');
        if (!campo) return null;
        return { campo: String(campo), op: String(op || 'eq'), val: val };
    }).filter(Boolean);
}

function rbNormalizeReportConfig(input) {
    let cfg = input;
    for (let i = 0; i < 4; i++) cfg = rbTryParseJson(cfg);

    if (!cfg || typeof cfg !== 'object') {
        return null;
    }

    if (cfg.config && typeof cfg.config !== 'undefined' && cfg.config !== cfg) {
        const nestedCfg = rbNormalizeReportConfig(cfg.config);
        if (nestedCfg) return nestedCfg;
    }

    const out = {
        tabelas: [],
        campos: [],
        filtros: [],
        order: { field: '', dir: 'ASC' },
        page: 1,
        limit: 50
    };

    let tables = cfg.tabelas || cfg.tables || cfg.selectedTables || cfg.tableList || cfg.fromTables || [];
    for (let i = 0; i < 3; i++) tables = rbTryParseJson(tables);
    if (typeof tables === 'string') tables = tables.split(',');
    if (!Array.isArray(tables) && tables && typeof tables === 'object') tables = Object.values(tables);
    if (Array.isArray(tables)) {
        out.tabelas = tables.map(function(t){
            if (!t) return null;
            if (typeof t === 'string') return t.trim();
            if (typeof t === 'object') return String(t.id || t.value || t.table || t.tabela || t.name || '').trim();
            return null;
        }).filter(Boolean);
    }

    let fields = cfg.campos || cfg.fields || cfg.selectedFields || cfg.columns || cfg.selected_columns || [];
    for (let i = 0; i < 3; i++) fields = rbTryParseJson(fields);
    if (fields && !Array.isArray(fields) && typeof fields === 'object') {
        const expanded = [];
        Object.keys(fields).forEach(function(key){
            const value = fields[key];
            if (Array.isArray(value)) {
                value.forEach(function(v){
                    if (typeof v === 'string' && v.indexOf('.') === -1) expanded.push(String(key) + '.' + v);
                    else expanded.push(v);
                });
            } else {
                expanded.push(value);
            }
        });
        fields = expanded;
    }
    if (!Array.isArray(fields)) fields = fields ? [fields] : [];
    out.campos = fields.map(rbNormalizeFieldEntry).filter(Boolean);

    [cfg.fieldList, cfg.select, cfg.selection, cfg.selected].forEach(function(bucket){
        if (!bucket) return;
        let parsed = bucket;
        for (let i = 0; i < 3; i++) parsed = rbTryParseJson(parsed);
        if (!Array.isArray(parsed)) return;
        parsed.map(rbNormalizeFieldEntry).filter(Boolean).forEach(function(item){
            if (out.campos.indexOf(item) === -1) out.campos.push(item);
        });
    });

    out.filtros = rbNormalizeFilters(cfg.filtros || cfg.filters || cfg.conditions || cfg.where || []);

    const order = cfg.order || cfg.sort || cfg.orderBy || cfg.orderby || {};
    if (typeof order === 'string') {
        out.order.field = order;
        out.order.dir = 'ASC';
    } else if (order && typeof order === 'object') {
        out.order.field = String(order.field || order.campo || order.column || order.col || '').trim();
        out.order.dir = String(order.dir || order.direction || 'ASC').toUpperCase() === 'DESC' ? 'DESC' : 'ASC';
    }

    const pageVal = parseInt(cfg.page || cfg.pagina || cfg.currentPage || 1, 10);
    const limitVal = parseInt(cfg.limit || cfg.pageSize || cfg.perPage || cfg.currentLimit || 50, 10);
    out.page = Number.isFinite(pageVal) && pageVal > 0 ? pageVal : 1;
    out.limit = Number.isFinite(limitVal) && limitVal > 0 ? limitVal : 50;

    if (!out.tabelas.length) {
        out.campos.forEach(function(c){
            const parts = String(c).split('.');
            if (parts.length > 1 && out.tabelas.indexOf(parts[0]) === -1) out.tabelas.push(parts[0]);
        });
        out.filtros.forEach(function(f){
            const campo = String(f.campo || '');
            const parts = campo.split('.');
            if (parts.length > 1 && out.tabelas.indexOf(parts[0]) === -1) out.tabelas.push(parts[0]);
        });
        const orderParts = String(out.order.field || '').split('.');
        if (orderParts.length > 1 && out.tabelas.indexOf(orderParts[0]) === -1) out.tabelas.push(orderParts[0]);
    }

    out.tabelas = out.tabelas.filter(Boolean);
    out.campos = out.campos.filter(Boolean);
    out.filtros = out.filtros.filter(Boolean);

    return out;
}

function setTableLoading(isLoading) {
    const btn = document.querySelector('button[onclick="addTable()"]');
    if (btn) btn.disabled = isLoading;

    const select = $('#table-select');
    if (!select.length) return;

    select.prop('disabled', isLoading);

    if (!isLoading) {
        select.val('');
        try { select.trigger('change'); } catch (e) {}
    }
}

function normalizeTablesResponse(payload) {
    if (Array.isArray(payload)) return payload;
    if (payload && Array.isArray(payload.tables)) return payload.tables;
    if (payload && payload.success && Array.isArray(payload.data)) return payload.data;
    return [];
}

function buildPayload() {
    return {
        tabelas: selectedTables,
        campos: Array.from(selectedFields),
        filtros: collectFiltersFromUI(),
        order: {
            field: $('#order_field').val() || '',
            dir: $('#order_dir').val() || 'ASC'
        },
        page: currentPage,
        limit: currentLimit
    };
}

function initTableSelector() {
    const sel = $('#table-select');
    if (!sel.length) return;

    fetch('get_tables.php', { cache: 'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(payload) {
            const tables = normalizeTablesResponse(payload);

            sel.empty().append('<option value="">Selecione...</option>');
            tables.forEach(function(t) {
                const id = t && (t.id || t.value || t.table || t.nome);
                const text = t && (t.text || t.label || t.nome || t.id || t.value || t.table);
                if (!id) return;
                sel.append('<option value="' + escapeHtml(id) + '">' + escapeHtml(text || id) + '</option>');
            });

            if ($.fn && typeof $.fn.select2 === 'function') {
                if (sel.hasClass('select2-hidden-accessible')) {
                    sel.select2('destroy');
                }
                sel.select2({
                    width: '100%',
                    placeholder: 'Selecione...'
                });
            }
        })
        .catch(function(err) {
            console.error(err);
            alert('Erro ao carregar tabelas: ' + err.message);
        });
}

function addTable() {
    const table = $('#table-select').val();
    if (!table) return;
    if (selectedTables.indexOf(table) !== -1) return;

    selectedTables.push(table);
    setTableLoading(true);
    loadTableFields(table);
}

function removeTable(table) {
    selectedTables = selectedTables.filter(function(t) { return t !== table; });

    Array.from(selectedFields).forEach(function(f) {
        if (f.indexOf(table + '.') === 0) selectedFields.delete(f);
    });

    $('#acc-' + cssEscape(table)).remove();
    updateActiveFieldsSummary();
    updateOrderOptions();

    $('#conditions-container .condition-row').each(function() {
        const campo = $(this).find('.cond-field').val();
        if (campo && campo.indexOf(table + '.') === 0) {
            $(this).remove();
        }
    });
}

function loadTableFields(table) {
    fetch('get_tables_fields.php?table=' + encodeURIComponent(table), { cache: 'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(resp) {
            if (!resp.success) throw new Error(resp.message || 'Erro ao carregar campos');
            renderTableAccordion(table, resp.fields || []);
            updateOrderOptions();
        })
        .catch(function(err) {
            console.error(err);
            alert('Erro ao carregar campos da tabela ' + table + ': ' + err.message);
            selectedTables = selectedTables.filter(function(t) { return t !== table; });
        })
        .finally(function() {
            setTableLoading(false);
        });
}

function renderTableAccordion(table, fields) {
    const tableId = 'acc-' + table;
    if (document.getElementById(tableId)) return;

    (fields || []).forEach(function(f){
        window.RB_FIELD_META[table + '.' + f.name] = f;
    });

    const items = (fields || []).map(function(f) {
        const key = table + '.' + f.name;
        return '<div class="field-item" data-field="' + escapeHtml(key) + '" onclick="toggleField(\'' +
            escapeHtml(table) + '\',\'' + escapeHtml(f.name) + '\', this)">' +
            '<span class="field-label">' + escapeHtml(f.label || f.name) + '</span>' +
            '</div>';
    }).join('');

    const html = '' +
        '<div class="card mb-2" id="' + escapeHtml(tableId) + '">' +
            '<div class="card-header p-2" id="h-' + escapeHtml(table) + '">' +
                '<button class="btn btn-link text-left w-100" type="button" data-toggle="collapse" data-target="#c-' + escapeHtml(table) + '" aria-expanded="false">' +
                    escapeHtml(table) +
                '</button>' +
            '</div>' +
            '<div id="c-' + escapeHtml(table) + '" class="collapse" data-parent="#tablesAccordion">' +
                '<div class="card-body">' +
                    '<div class="fields-list">' + items + '</div>' +
                    '<button class="btn btn-sm btn-outline-danger mt-2" onclick="removeTable(\'' + escapeHtml(table) + '\')">Remover tabela</button>' +
                '</div>' +
            '</div>' +
        '</div>';

    $('#tablesAccordion').append(html);
}

function toggleField(table, field, el) {
    const key = table + '.' + field;
    if (selectedFields.has(key)) {
        selectedFields.delete(key);
        $(el).removeClass('selected-field');
    } else {
        selectedFields.add(key);
        $(el).addClass('selected-field');
    }
    updateActiveFieldsSummary();
    updateOrderOptions();
}

function updateActiveFieldsSummary() {
    const container = $('#active-fields-summary');
    if (!container.length) return;

    if (selectedFields.size === 0) {
        container.html('<span class="text-muted font-italic">Nenhuma coluna selecionada</span>');
        return;
    }

    container.html(Array.from(selectedFields).map(function(f) {
        return '<span class="badge badge-primary mr-1 mb-1">' + escapeHtml(f) + '</span>';
    }).join(''));
}

function updateOrderOptions() {
    const sel = $('#order_field');
    if (!sel.length) return;

    const current = sel.val();
    sel.empty().append('<option value="">Selecione...</option>');

    Array.from(selectedFields).forEach(function(f) {
        sel.append('<option value="' + escapeHtml(f) + '">' + escapeHtml(f) + '</option>');
    });

    if (Array.from(selectedFields).indexOf(current) !== -1) {
        sel.val(current);
    }
}

function rbApplyFilterInputMode(row, fieldKey) {
    fetch('get_field_values.php?field=' + encodeURIComponent(fieldKey), { cache:'no-store' })
        .then(function(r){ return r.json(); })
        .then(function(resp){
            if (!resp.success) return;

            const valWrap = row.querySelector('.rb-filter-value-wrap');
            const meta = window.RB_FIELD_META[fieldKey] || {};
            const kind = rbGuessKindFromType(meta.type);

            if (resp.mode === 'select' && Array.isArray(resp.values) && resp.values.length) {
                let html = '<select class="form-control form-control-sm cond-val">';
                resp.values.forEach(function(v){
                    html += '<option value="' + escapeHtml(v) + '">' + escapeHtml(v) + '</option>';
                });
                html += '</select>';
                valWrap.innerHTML = html;
            } else if (kind === 'date') {
                valWrap.innerHTML = '<input type="text" class="form-control form-control-sm cond-val" placeholder="AAAA-MM-DD ou data1,data2">';
            } else if (kind === 'number') {
                valWrap.innerHTML = '<input type="text" class="form-control form-control-sm cond-val" placeholder="Número ou n1,n2">';
            } else {
                valWrap.innerHTML = '<input type="text" class="form-control form-control-sm cond-val" placeholder="Valor">';
            }

            const opSel = row.querySelector('.cond-op');
            if (opSel) {
                opSel.innerHTML = rbBuildOperatorOptions(fieldKey);
            }
        })
        .catch(function(e){ console.warn(e); });
}

function addCondition() {
    if (selectedFields.size === 0) {
        alert('Selecione pelo menos um campo antes de adicionar filtros.');
        return;
    }

    const id = 'cond-' + Date.now() + '-' + Math.floor(Math.random() * 9999);
    const selected = Array.from(selectedFields);
    const firstField = selected[0] || '';

    const fieldOptions = selected.map(function(f) {
        return '<option value="' + escapeHtml(f) + '">' + escapeHtml(f) + '</option>';
    }).join('');

    $('#conditions-container').append(
        '<div class="condition-row" id="' + id + '">' +
            '<select class="form-control form-control-sm cond-field" style="max-width:280px">' + fieldOptions + '</select>' +
            '<select class="form-control form-control-sm cond-op" style="max-width:150px">' + rbBuildOperatorOptions(firstField) + '</select>' +
            '<div class="rb-filter-value-wrap" style="flex:1; min-width:180px;"><input type="text" class="form-control form-control-sm cond-val" placeholder="Valor"></div>' +
            '<button class="btn btn-sm btn-outline-danger" onclick="$(\'#' + id + '\').remove()"><i class="fa fa-times"></i></button>' +
        '</div>'
    );

    const row = document.getElementById(id);
    if (row) {
        const fieldSel = row.querySelector('.cond-field');
        if (fieldSel) {
            fieldSel.addEventListener('change', function() {
                rbApplyFilterInputMode(row, this.value);
            });
            rbApplyFilterInputMode(row, fieldSel.value);
        }
    }
}

function collectFiltersFromUI() {
    const filters = [];
    $('#conditions-container .condition-row').each(function() {
        const campo = $(this).find('.cond-field').val();
        const op = $(this).find('.cond-op').val();
        const val = $(this).find('.cond-val').val();
        if (campo && op) {
            filters.push({ campo: campo, op: op, val: val });
        }
    });
    return filters;
}

function renderPreviewTable(data) {
    if (!Array.isArray(data) || data.length === 0) {
        $('#preview').html('<div class="alert alert-info">Sem resultados para os critérios selecionados.</div>');
        return;
    }

    const cols = Object.keys(data[0]);
    const thead = '<tr>' + cols.map(function(c) { return '<th>' + escapeHtml(c) + '</th>'; }).join('') + '</tr>';
    const tbody = data.map(function(row) {
        return '<tr>' + cols.map(function(c) {
            return '<td>' + escapeHtml((row[c] === null || typeof row[c] === 'undefined') ? '' : row[c]) + '</td>';
        }).join('') + '</tr>';
    }).join('');

    $('#preview').html(
        '<div id="preview-controls-inner" class="d-flex flex-wrap align-items-center justify-content-between mb-3">' +
            '<div class="d-flex align-items-center">' +
                '<button class="btn btn-outline-secondary btn-sm mr-2" onclick="prevPage()"><i class="fa fa-chevron-left"></i></button>' +
                '<button class="btn btn-outline-secondary btn-sm mr-2" onclick="nextPage()"><i class="fa fa-chevron-right"></i></button>' +
                '<span class="small text-muted" id="page-info"></span>' +
            '</div>' +
            '<div class="d-flex align-items-center">' +
                '<label class="small font-weight-bold m-0 mr-2">Linhas:</label>' +
                '<select id="page-limit" class="form-control form-control-sm" style="width:auto;" onchange="setLimitFromUI()">' +
                    '<option value="25">25</option>' +
                    '<option value="50">50</option>' +
                    '<option value="100">100</option>' +
                    '<option value="200">200</option>' +
                '</select>' +
            '</div>' +
        '</div>' +
        '<div class="table-responsive">' +
            '<table class="table table-hover mb-0 table-bordered table-preview">' +
                '<thead>' + thead + '</thead>' +
                '<tbody>' + tbody + '</tbody>' +
            '</table>' +
        '</div>'
    );

    $('#page-limit').val(String(currentLimit));
    $('#page-info').text('Página ' + currentPage + ' de ' + totalPages);
}

function refreshPreview() {
    if (selectedTables.length === 0 || selectedFields.size === 0) {
        alert('Selecione tabelas e campos.');
        return;
    }

    $('#preview').html('<div class="text-center p-4">A carregar...</div>');

    fetch('visualizar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload())
    })
    .then(function(r) {
        return r.text().then(function(txt) {
            if (!txt) throw new Error('Resposta vazia do servidor');
            try { return JSON.parse(txt); }
            catch (e) { console.error('Resposta inválida visualizar.php:', txt); throw new Error('JSON inválido na visualização'); }
        });
    })
    .then(function(resp) {
        if (!resp || typeof resp !== 'object') throw new Error('Resposta inválida do servidor');
        if (!resp.success) throw new Error(resp.message || 'Erro na visualização');
        const meta = resp.meta || {};
        currentPage = Number(meta.page || currentPage);
        currentLimit = Number(meta.limit || currentLimit);
        totalPages = Number(meta.totalPages || 1);
        renderPreviewTable(resp.data || []);
    })
    .catch(function(err) {
        console.error(err);
        $('#preview').html('<div class="alert alert-danger">Erro ao visualizar: ' + escapeHtml(err.message) + '</div>');
    });
}

function prevPage() { if (currentPage > 1) { currentPage -= 1; refreshPreview(); } }
function nextPage() { if (currentPage < totalPages) { currentPage += 1; refreshPreview(); } }
function setLimitFromUI() { currentLimit = Number($('#page-limit').val() || 50); currentPage = 1; refreshPreview(); }

function exportarExcel() {
    if (selectedTables.length === 0 || selectedFields.size === 0) {
        alert('Selecione tabelas e campos.');
        return;
    }

    fetch('exportar.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload())
    })
    .then(function(r) { return r.blob(); })
    .then(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'relatorio.xls';
        a.click();
        URL.revokeObjectURL(url);
    })
    .catch(function(err) {
        console.error(err);
        alert('Erro ao exportar.');
    });
}

function exportarCSV() {
    if (selectedTables.length === 0 || selectedFields.size === 0) {
        alert('Selecione tabelas e campos.');
        return;
    }

    fetch('exportar_csv.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(buildPayload())
    })
    .then(function(r) { return r.blob(); })
    .then(function(blob) {
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'relatorio.csv';
        a.click();
        URL.revokeObjectURL(url);
    })
    .catch(function(err) {
        console.error(err);
        alert('Erro ao exportar CSV.');
    });
}

function saveReport() {
    if (selectedTables.length === 0 || selectedFields.size === 0) {
        alert('Selecione tabelas e campos antes de guardar.');
        return;
    }

    const payload = buildPayload();
    if (!Array.isArray(payload.tabelas) || payload.tabelas.length === 0 || !Array.isArray(payload.campos) || payload.campos.length === 0) {
        alert('Não é possível guardar um relatório vazio.');
        return;
    }

    if (!currentReportOwner && currentReportId) {
        const criarCopia = confirm('Este relatório é partilhado e não é seu. Pretende guardar como novo relatório seu?');
        if (!criarCopia) return;
        currentReportId = null;
    }

    const nomeAtual = (window.RB_BOOT_REPORT && window.RB_BOOT_REPORT.name) ? window.RB_BOOT_REPORT.name : '';
    const descAtual = (window.RB_BOOT_REPORT && window.RB_BOOT_REPORT.description) ? window.RB_BOOT_REPORT.description : '';
    const name = prompt('Nome do relatório:', nomeAtual);
    if (!name) return;
    const description = prompt('Descrição (opcional):', descAtual) || '';

    fetch('saved_reports.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            report_id: currentReportId,
            name: name,
            description: description,
            config: payload
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(resp) {
        if (!resp.success) throw new Error(resp.message || 'Erro ao guardar');
        if (resp.report_id) currentReportId = Number(resp.report_id);
        if (window.RB_BOOT_REPORT && typeof window.RB_BOOT_REPORT === 'object') {
            window.RB_BOOT_REPORT.name = name;
            window.RB_BOOT_REPORT.description = description;
        }
        alert(resp.updated ? 'Relatório atualizado com sucesso!' : 'Relatório guardado com sucesso!');
    })
    .catch(function(err) {
        console.error(err);
        alert('Erro ao guardar: ' + err.message);
    });
}

function loadReport() {
    window.location.href = 'saved_reports_list.php';
}

function applyLoadedConfig(config) {
    if (config && typeof config === 'object' && !Array.isArray(config) && ('config' in config || 'rawConfig' in config) && !('tabelas' in config) && !('campos' in config)) {
        config = (config.config ?? config.rawConfig ?? config);
    }

    const normalizedConfig = rbNormalizeReportConfig(config);
    if (!normalizedConfig || ((!Array.isArray(normalizedConfig.tabelas) || normalizedConfig.tabelas.length === 0) && (!Array.isArray(normalizedConfig.campos) || normalizedConfig.campos.length === 0))) {
        console.warn('Configuração inválida para aplicar', config);
        return;
    }

    config = normalizedConfig;

    clearAll(false);

    currentPage = Number(config.page || 1);
    currentLimit = Number(config.limit || 50);
    totalPages = 1;

    const tabelas = config.tabelas.slice();
    const campos = Array.isArray(config.campos) ? config.campos.slice() : [];
    const filtros = Array.isArray(config.filtros) ? config.filtros.slice() : [];
    const orderField = (config.order && config.order.field) ? config.order.field : '';
    const orderDir = (config.order && config.order.dir) ? config.order.dir : 'ASC';

    function addLoadedFilter(i) {
        if (i >= filtros.length) {
            refreshPreview();
            return;
        }

        addCondition();

        const row = $('#conditions-container .condition-row').last();
        const f = filtros[i];

        row.find('.cond-field').val(f.campo || '').trigger('change');

        setTimeout(function() {
            row.find('.cond-op').val(f.op || 'eq');
            row.find('.cond-val').val((typeof f.val === 'undefined' || f.val === null) ? '' : f.val);
            addLoadedFilter(i + 1);
        }, 120);
    }

    function markFieldsAndFilters() {
        campos.forEach(function(campo) {
            selectedFields.add(campo);
            $('.field-item[data-field="' + cssEscape(campo) + '"]').addClass('selected-field');
        });

        updateActiveFieldsSummary();
        updateOrderOptions();
        $('#order_field').val(orderField);
        $('#order_dir').val(orderDir);

        addLoadedFilter(0);
    }

    function loadNextTable() {
        if (tabelas.length === 0) {
            setTimeout(markFieldsAndFilters, 120);
            return;
        }

        const table = tabelas.shift();
        if (selectedTables.indexOf(table) === -1) {
            selectedTables.push(table);
        }

        fetch('get_tables_fields.php?table=' + encodeURIComponent(table), { cache: 'no-store' })
            .then(function(r){ return r.json(); })
            .then(function(resp) {
                if (!resp.success) throw new Error(resp.message || 'Erro ao carregar campos');
                renderTableAccordion(table, resp.fields || []);
                loadNextTable();
            })
            .catch(function(err) {
                console.error('Erro ao reaplicar tabela "' + table + '":', err);
                loadNextTable();
            });
    }

    loadNextTable();
}

function clearAll(withConfirm) {
    if (typeof withConfirm === 'undefined') withConfirm = true;
    if (withConfirm && !confirm('Deseja limpar toda a configuração atual?')) return;

    selectedTables = [];
    selectedFields = new Set();
    currentReportId = null;
    currentReportOwner = true;
    currentPage = 1;
    currentLimit = 50;
    totalPages = 1;

    $('#tablesAccordion').empty();
    $('#conditions-container').empty();
    $('#order_field').empty().append('<option value="">Selecione...</option>');
    $('#order_dir').val('ASC');
    updateActiveFieldsSummary();

    $('#preview').html(
        '<div class="preview-placeholder">' +
            '<i class="fa fa-eye" style="font-size:40px; opacity:.4;"></i>' +
            '<p class="mt-3 mb-0">Selecione as tabelas, colunas e filtros, e depois clique em “Visualizar”.</p>' +
        '</div>'
    );

    try { $('#table-select').val('').trigger('change'); } catch (e) {}
}

$(document).ready(function() {
    if (typeof window.RB_HAS_DB !== 'undefined' && !window.RB_HAS_DB) {
        console.warn('Report Builder sem ligação à BD.');
        return;
    }

    initTableSelector();

    setTimeout(function() {
        try {
            if (window.RB_BOOT_REPORT) {
                const boot = window.RB_BOOT_REPORT;
                currentReportId = boot && boot.reportId ? Number(boot.reportId) : null;
                currentReportOwner = !(boot && boot.isOwner === false);
                const bootConfig = (boot && typeof boot === 'object' && !Array.isArray(boot))
                    ? (boot.config ?? boot.rawConfig ?? boot)
                    : boot;

                if (bootConfig !== null && typeof bootConfig !== 'undefined') {
                    applyLoadedConfig(bootConfig);
                    return;
                }

                console.warn('Relatório carregado sem configuração válida', boot);
            }

            const raw = localStorage.getItem('reportToLoad');
            if (raw) {
                localStorage.removeItem('reportToLoad');
                const cfg = rbTryParseJson(raw);
                applyLoadedConfig(cfg);
            }
        } catch (e) {
            console.warn('Não foi possível carregar configuração guardada', e);
        }
    }, 400);
});

window.addTable = addTable;
window.removeTable = removeTable;
window.toggleField = toggleField;
window.addCondition = addCondition;
window.collectFiltersFromUI = collectFiltersFromUI;
window.refreshPreview = refreshPreview;
window.prevPage = prevPage;
window.nextPage = nextPage;
window.setLimitFromUI = setLimitFromUI;
window.exportarExcel = exportarExcel;
window.exportarCSV = exportarCSV;
window.saveReport = saveReport;
window.loadReport = loadReport;
window.clearAll = clearAll;
window.applyLoadedConfig = applyLoadedConfig;
