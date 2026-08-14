/* Tela 3 — Banco: contagem das tabelas e conteúdo bruto da tabela escolhida. */

import { api } from '../core/api.js';
import { $, esc, loadingBox, onClick, stateBox } from '../core/dom.js';
import { renderRawTable, resetTable, tableError } from '../core/table.js';

const TABLES = [
    'systems', 'entities', 'links', 'external_records',
    'invoice_staging', 'exchange_rates', 'events',
];

let current = 'events';

export function init() {
    onClick($('db-counts'), '[data-table]', (card) => selectTable(card.dataset.table));
}

export async function refresh() {
    await Promise.all([loadCounts(), loadRows()]);
}

/**
 * Clique numa tabela: o card marca na hora e a área de dados mostra o
 * spinner — o feedback não espera a resposta da API.
 */
async function selectTable(table) {
    current = table;
    resetTable('tbl-db'); // as colunas mudam entre tabelas: zera busca e página

    markActiveCard();
    showLoading();

    await refresh();
}

/** Marca o card escolhido sem redesenhar a lista (não perde o clique). */
function markActiveCard() {
    $('db-counts').querySelectorAll('[data-table]').forEach((card) => {
        card.classList.toggle('active', card.dataset.table === current);
    });
}

function showLoading() {
    $('db-table-title').innerHTML = `${esc(current)} <span class="muted">— carregando…</span>`;
    $('tools-db').innerHTML = '';
    $('pager-db').innerHTML = '';
    $('tbl-db').innerHTML = `<tbody><tr><td>${loadingBox(`Carregando ${current}…`)}</td></tr></tbody>`;
}

async function loadCounts() {
    const box = $('db-counts');

    try {
        const counts = await api('/api/tables');

        box.innerHTML = TABLES.map((table) => `
            <button type="button" class="count-card ${table === current ? 'active' : ''}" data-table="${esc(table)}">
                <div class="n">${counts[table] ?? 0}</div>
                <div class="t">${esc(table)}</div>
            </button>`).join('');
    } catch (error) {
        box.innerHTML = stateBox(error.message, 'error');
    }
}

async function loadRows() {
    $('db-table-title').innerHTML = `${esc(current)} <span class="muted">— últimas linhas</span>`;

    try {
        const result = await api(`/api/tables/${encodeURIComponent(current)}`);
        renderRawTable('tbl-db', result.columns, result.rows, {
            toolbarId: 'tools-db',
            pagerId: 'pager-db',
        });
    } catch (error) {
        tableError('tbl-db', error.message);
    }
}
