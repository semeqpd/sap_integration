/*
 * SEMEQ Middleware — porta de entrada da interface.
 *
 * Cada tela é um módulo com init() (liga os eventos, uma vez) e refresh()
 * (busca os dados). Este arquivo só cuida das abas e do ciclo de atualização.
 */

import { api } from './core/api.js';
import { $ } from './core/dom.js';
import * as database from './screens/database.js';
import * as invoices from './screens/invoices.js';
import * as links from './screens/links.js';

const REFRESH_INTERVAL = 30_000;

const screens = [links, invoices, database];

function initTabs() {
    document.querySelectorAll('.tab-btn').forEach((button) => {
        button.onclick = () => {
            document.querySelectorAll('.tab-btn').forEach((b) => b.classList.remove('active'));
            document.querySelectorAll('.tab-panel').forEach((p) => p.classList.remove('active'));

            button.classList.add('active');
            $(`tab-${button.dataset.tab}`).classList.add('active');

            refreshAll();
        };
    });
}

/** Badge de conexão: o /api/tables é o "ping" mais barato que temos. */
async function checkConnection() {
    const badge = $('conn');

    try {
        await api('/api/tables');
        badge.className = 'badge badge-ok';
        badge.textContent = 'conectado';
    } catch {
        badge.className = 'badge badge-err';
        badge.textContent = 'sem conexão';
    }
}

async function refreshAll() {
    await Promise.all([checkConnection(), ...screens.map((screen) => screen.refresh())]);
}

screens.forEach((screen) => screen.init());
initTabs();
refreshAll();
setInterval(refreshAll, REFRESH_INTERVAL);
