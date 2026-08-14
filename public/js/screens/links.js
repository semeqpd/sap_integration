/* Tela 1 — Vínculos: webhook do SAP, fila de pendências e de-para consolidado. */

import { api } from '../core/api.js';
import { $, esc, onClick } from '../core/dom.js';
import { linkPill, renderSteps } from '../core/render.js';
import { renderTable, tableError } from '../core/table.js';
import { toast } from '../core/toast.js';

const modal = {
    linkId: null,
    createNew: false,
    records: [],   // catálogo carregado do sistema em questão
};

export function init() {
    $('wh-send').onclick = sendWebhook;

    // A tabela é redesenhada a cada refresh: o listener fica no elemento pai.
    onClick($('tbl-pending'), '[data-action="link"]', (button) => openModal(button.dataset));

    $('modal-close').onclick = closeModal;
    $('modal-toggle-new').onclick = () => setMode(true);
    $('modal-toggle-existing').onclick = () => setMode(false);
    $('modal-save').onclick = saveLink;
    $('modal-search').oninput = () => fillOptions($('modal-search').value);

    // Enter na busca escolhe o primeiro resultado.
    $('modal-search').onkeydown = (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('modal-select').focus();
        }
    };

    $('modal').addEventListener('click', (event) => {
        if (event.target === $('modal')) closeModal();
    });
}

export async function refresh() {
    await Promise.all([loadPending(), loadEntities()]);
}

/* ---------- webhook ---------- */

async function sendWebhook() {
    const code = $('wh-code').value.trim();
    const name = $('wh-name').value.trim();

    if (!code || !name) {
        toast('Preencha CardCode e CardName', 'error');
        return;
    }

    try {
        const result = await api('/webhook/sap/customer', {
            method: 'POST',
            body: { CardCode: code, CardName: name, Country: $('wh-country').value },
        });

        renderSteps('steps-links', result.steps);
        toast(`Webhook processado: entidade ${result.entity.id} (${result.entity.name})`);
        await refresh();
    } catch (error) {
        toast(error.message, 'error');
    }
}

/* ---------- tabelas ---------- */

async function loadPending() {
    try {
        const rows = await api('/api/pending');

        renderTable('tbl-pending', [
            { key: 'link_id', label: '#', cell: (row) => `<td>${row.link_id}</td>` },
            {
                key: 'entity_name',
                label: 'Entidade',
                cell: (row) => `<td>${esc(row.entity_name)} <span class="muted">(entidade ${row.entity_id})</span></td>`,
            },
            { key: 'entity_type', label: 'Tipo', cell: (row) => `<td>${esc(row.entity_type)}</td>` },
            { key: 'system_name', label: 'Sistema', cell: (row) => `<td>${esc(row.system_name)}</td>` },
            { key: 'created_at', label: 'Desde', cell: (row) => `<td>${esc(row.created_at.slice(0, 16))}</td>` },
            {
                key: 'action',
                label: '',
                sortable: false,
                cell: (row) => `<td><button class="btn small" data-action="link"
                    data-link-id="${row.link_id}"
                    data-system-id="${esc(row.system_id)}"
                    data-entity-name="${esc(row.entity_name)}"
                    data-entity-type="${esc(row.entity_type)}"
                    data-system-name="${esc(row.system_name)}">Vincular</button></td>`,
            },
        ], rows, {
            emptyMsg: 'Nenhuma pendência — tudo vinculado.',
            toolbarId: 'tools-pending',
            pagerId: 'pager-pending',
        });
    } catch (error) {
        tableError('tbl-pending', error.message);
    }
}

async function loadEntities() {
    try {
        const rows = await api('/api/entities');

        renderTable('tbl-entities', [
            { key: 'id', label: 'ID', sortVal: (r) => r.entity.id, cell: (r) => `<td>${r.entity.id}</td>` },
            { key: 'name', label: 'Nome', sortVal: (r) => r.entity.name, cell: (r) => `<td>${esc(r.entity.name)}</td>` },
            { key: 'type', label: 'Tipo', sortVal: (r) => r.entity.type, cell: (r) => `<td>${esc(r.entity.type)}</td>` },
            {
                key: 'created_from',
                label: 'Nasceu em',
                sortVal: (r) => r.entity.created_from,
                cell: (r) => `<td>${esc(r.entity.created_from)}</td>`,
            },
            {
                key: 'links',
                label: 'Vínculos (sistema : código)',
                sortable: false,
                cell: (r) => `<td class="wrap">${(r.links ?? []).map(linkPill).join('')}</td>`,
            },
        ], rows, {
            emptyMsg: 'Nenhuma entidade ainda.',
            toolbarId: 'tools-entities',
            pagerId: 'pager-entities',
        });
    } catch (error) {
        tableError('tbl-entities', error.message);
    }
}

/* ---------- modal de vínculo manual ---------- */

async function openModal({ linkId, systemId, entityName, entityType, systemName }) {
    modal.linkId = linkId;
    modal.records = [];
    setMode(false);

    $('modal-new-name').value = '';
    $('modal-search').value = '';
    $('modal-title').textContent = `Vincular "${entityName}" no ${systemName}`;
    $('modal-sub').textContent =
        `Escolha qual registro do ${systemName} corresponde a "${entityName}". `
        + 'A lista vem da external_records (catálogo sincronizado).';

    const select = $('modal-select');
    select.innerHTML = '<option>carregando…</option>';
    $('modal-count').textContent = '';
    $('modal').classList.add('open');

    try {
        const records = await api(`/api/external-records?system=${encodeURIComponent(systemId)}&type=${encodeURIComponent(entityType)}`);

        // Só o que ainda não está vinculado a outra entidade.
        modal.records = (records ?? []).filter((record) => !record.linked);
        fillOptions('');
        $('modal-search').focus();
    } catch (error) {
        select.innerHTML = `<option value="">erro: ${esc(error.message)}</option>`;
    }
}

/** Preenche a lista com o que casa com o termo (nome ou código). */
function fillOptions(termo) {
    const select = $('modal-select');
    const busca = termo.trim().toLowerCase();

    const encontrados = busca === ''
        ? modal.records
        : modal.records.filter((record) => `${record.name} ${record.external_code}`.toLowerCase().includes(busca));

    // Um catálogo grande em <option> é lento de renderizar; 200 já é mais do
    // que qualquer pessoa vai percorrer com o olho — o filtro cobre o resto.
    const exibidos = encontrados.slice(0, 200);

    if (modal.records.length === 0) {
        select.innerHTML = '<option value="">(nenhum registro disponível — use "adicionar novo")</option>';
        $('modal-count').textContent = '';

        return;
    }

    select.innerHTML = exibidos.length
        ? exibidos.map((record) =>
            `<option value="${esc(record.external_code)}" data-name="${esc(record.name)}">`
            + `${esc(record.name)} — ${esc(record.external_code)}</option>`).join('')
        : '<option value="">(nada encontrado para esta busca)</option>';

    if (exibidos.length) select.selectedIndex = 0;

    const total = modal.records.length;
    $('modal-count').textContent = busca === ''
        ? `— ${total} disponível(is)${total > exibidos.length ? `, mostrando ${exibidos.length}` : ''}`
        : `— ${encontrados.length} de ${total}${encontrados.length > exibidos.length ? `, mostrando ${exibidos.length}` : ''}`;
}

function closeModal() {
    $('modal').classList.remove('open');
}

function setMode(createNew) {
    modal.createNew = createNew;
    $('modal-existing').hidden = createNew;
    $('modal-new').hidden = !createNew;
}

async function saveLink() {
    const linkedBy = $('modal-by').value.trim() || 'tela';
    let body;

    if (modal.createNew) {
        const name = $('modal-new-name').value.trim();
        if (!name) {
            toast('Informe o nome do novo registro', 'error');
            return;
        }
        body = { create_new: true, external_name: name, linked_by: linkedBy };
    } else {
        const select = $('modal-select');
        if (!select.value) {
            toast('Selecione um registro ou use "adicionar novo"', 'error');
            return;
        }
        body = {
            external_code: select.value,
            external_name: select.selectedOptions[0]?.dataset?.name ?? '',
            linked_by: linkedBy,
        };
    }

    try {
        const result = await api(`/api/links/${modal.linkId}/resolve`, { method: 'POST', body });

        closeModal();
        renderSteps('steps-links', result.steps);
        toast(`Vínculo salvo: ${result.entity.name}`);
        await refresh();
    } catch (error) {
        toast(error.message, 'error');
    }
}
