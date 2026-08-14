/* Tela 2 — Invoices: poll das filiais e o conteúdo do invoice_staging. */

import { api } from '../core/api.js';
import { $, esc } from '../core/dom.js';
import { badge, money, renderSteps } from '../core/render.js';
import { renderTable, tableError } from '../core/table.js';
import { toast } from '../core/toast.js';

export function init() {
    $('inv-poll').onclick = () => run($('inv-poll'), pollNow);
    $('inv-demo').onclick = () => run($('inv-demo'), injectDemo);
}

export async function refresh() {
    await loadInvoices();
}

/** Desabilita o botão enquanto a ação roda — evita disparo duplo. */
async function run(button, action) {
    button.disabled = true;
    try {
        await action();
    } catch (error) {
        toast(error.message, 'error');
    } finally {
        button.disabled = false;
    }
}

async function pollNow() {
    const result = await api('/api/poll', { method: 'POST' });

    renderSteps('steps-invoices', result.steps);
    toast(result.new > 0
        ? `${result.new} invoice(s) nova(s) processada(s)`
        : 'Nenhuma invoice nova nas filiais');

    await loadInvoices();
}

async function injectDemo() {
    const result = await api('/api/invoices/demo', { method: 'POST' });

    renderSteps('steps-invoices', result.steps);
    toast(`Invoice ${result.reference} processada`);

    await loadInvoices();
}

async function loadInvoices() {
    try {
        const rows = await api('/api/invoices');

        renderTable('tbl-invoices', [
            { key: 'id', label: '#', cell: (i) => `<td>${i.id}</td>` },
            {
                key: 'reference',
                label: 'Referência',
                sortVal: (i) => i.reference || i.external_code,
                cell: (i) => `<td>${esc(i.reference || i.external_code.slice(0, 12))}</td>`,
            },
            { key: 'system_id', label: 'Filial', cell: (i) => `<td>${esc(i.system_id)}</td>` },
            { key: 'status', label: 'Status', cell: (i) => `<td>${badge(i.status)}</td>` },
            { key: 'document_date', label: 'Data doc.', cell: (i) => `<td>${esc(i.document_date)}</td>` },
            { key: 'currency', label: 'Moeda', cell: (i) => `<td>${esc(i.currency)}</td>` },
            { key: 'source_amount', label: 'Valor origem', cell: (i) => `<td>${money(i.source_amount)}</td>` },
            { key: 'exchange_rate_used', label: 'Taxa', cell: (i) => `<td>${i.exchange_rate_used || ''}</td>` },
            { key: 'amount_brl', label: 'R$', cell: (i) => `<td>${i.amount_brl ? `R$ ${money(i.amount_brl)}` : ''}</td>` },
            { key: 'sap_doc_entry', label: 'DocEntry SAP', cell: (i) => `<td>${i.sap_doc_entry || ''}</td>` },
            {
                key: 'block_reason',
                label: 'Motivo/bloqueio',
                sortable: false,
                cell: (i) => `<td title="${esc(i.block_reason)}">${esc(i.block_reason)}</td>`,
            },
        ], rows, {
            emptyMsg: 'Nenhuma invoice no staging ainda.',
            toolbarId: 'tools-invoices',
            pagerId: 'pager-invoices',
        });
    } catch (error) {
        tableError('tbl-invoices', error.message);
    }
}
