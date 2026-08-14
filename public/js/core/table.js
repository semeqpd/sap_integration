/*
 * Tabela com busca global, ordenação e paginação.
 *
 * Uma coluna é { key, label, cell(row), sortable?, sortVal?(row), text?(row) }.
 * A tabela guarda o próprio estado (busca, página, ordenação), então filtrar,
 * ordenar ou paginar não refaz request — e o refresh automático a cada 30s
 * não perde o que a pessoa digitou.
 *
 * Tabelas "cruas" (tela Banco) usam as mesmas funções: as colunas viram
 * descritores por índice.
 */

import { $, esc, stateBox } from './dom.js';

const POR_PAGINA_PADRAO = 25;
const OPCOES_POR_PAGINA = [10, 25, 50, 100];
const MAX_BOTOES = 7;

const registry = new Map();

/** Compara valores tentando número primeiro, depois texto pt-BR. */
function compare(a, b, dir) {
    const left = a ?? '';
    const right = b ?? '';
    const na = parseFloat(left);
    const nb = parseFloat(right);

    const numeric = !isNaN(na) && !isNaN(nb)
        && `${left}`.trim() !== '' && `${right}`.trim() !== '';

    const result = numeric
        ? na - nb
        : `${left}`.localeCompare(`${right}`, 'pt-BR', { numeric: true });

    return dir === 'desc' ? -result : result;
}

/** Texto pesquisável de uma célula: o que a pessoa vê, sem as tags. */
function cellText(column, row) {
    if (column.text) return String(column.text(row) ?? '');
    if (column.sortVal) return String(column.sortVal(row) ?? '');

    return String(column.cell(row) ?? '')
        .replace(/<[^>]*>/g, ' ')
        .replace(/&nbsp;/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function rowText(columns, row) {
    return columns.map((column) => cellText(column, row)).join(' ').toLowerCase();
}

export function renderTable(tblId, columns, rows, { emptyMsg = 'Sem dados.', toolbarId = null, pagerId = null } = {}) {
    const anterior = registry.get(tblId);

    registry.set(tblId, {
        columns,
        rows,
        emptyMsg,
        toolbarId,
        pagerId,
        // estado preservado entre refreshes
        sort: anterior?.sort ?? null,
        search: anterior?.search ?? '',
        page: anterior?.page ?? 1,
        perPage: anterior?.perPage ?? POR_PAGINA_PADRAO,
        toolbarMounted: anterior?.toolbarMounted ?? false,
    });

    draw(tblId);
}

/** Tabela genérica da tela Banco: colunas são só nomes, células são texto. */
export function renderRawTable(tblId, columnNames, rows, options = {}) {
    const columns = columnNames.map((name, index) => ({
        key: String(index),
        label: name,
        sortVal: (row) => row[index],
        text: (row) => row[index],
        cell: (row) => `<td title="${esc(row[index])}">${esc(row[index])}</td>`,
    }));

    renderTable(tblId, columns, rows, { emptyMsg: 'Tabela vazia.', ...options });
}

/** Zera busca/ordenação/página — usado quando as colunas mudam. */
export function resetTable(tblId) {
    const entry = registry.get(tblId);
    if (!entry) return;

    entry.sort = null;
    entry.search = '';
    entry.page = 1;
    entry.toolbarMounted = false;
}

export function tableError(tblId, message) {
    $(tblId).innerHTML = `<tbody><tr><td>${stateBox(message, 'error')}</td></tr></tbody>`;

    const entry = registry.get(tblId);
    if (entry?.toolbarId) $(entry.toolbarId).innerHTML = '';
    if (entry?.pagerId) $(entry.pagerId).innerHTML = '';
    if (entry) entry.toolbarMounted = false;
}

/* ---------------------------------------------------------------- desenho */

function draw(tblId) {
    const state = registry.get(tblId);
    const table = $(tblId);
    if (!table) return;

    const { columns, rows, emptyMsg } = state;

    // 1. busca global
    const termo = state.search.trim().toLowerCase();
    const filtradas = termo === ''
        ? rows ?? []
        : (rows ?? []).filter((row) => rowText(columns, row).includes(termo));

    // 2. ordenação
    let ordenadas = filtradas;
    if (state.sort) {
        const column = columns.find((c) => c.key === state.sort.key);
        if (column) {
            const valor = column.sortVal ?? ((row) => row[state.sort.key]);
            ordenadas = [...filtradas].sort((a, b) => compare(valor(a), valor(b), state.sort.dir));
        }
    }

    // 3. paginação
    const total = ordenadas.length;
    const totalPaginas = Math.max(1, Math.ceil(total / state.perPage));
    state.page = Math.min(Math.max(1, state.page), totalPaginas);
    const inicio = (state.page - 1) * state.perPage;
    const pagina = ordenadas.slice(inicio, inicio + state.perPage);

    drawToolbar(tblId, state, total, rows?.length ?? 0);

    if (total === 0) {
        const vazio = termo === '' ? emptyMsg : `Nada encontrado para "${state.search}".`;
        table.innerHTML = `<tbody><tr><td>${stateBox(vazio)}</td></tr></tbody>`;
        if (state.pagerId) $(state.pagerId).innerHTML = '';

        return;
    }

    const head = columns.map((column) => {
        const sortable = column.sortable !== false;
        const seta = state.sort && state.sort.key === column.key
            ? (state.sort.dir === 'asc' ? ' ▲' : ' ▼')
            : '';
        const attrs = sortable ? ` class="sortable" data-col="${esc(column.key)}"` : '';

        return `<th${attrs}>${esc(column.label)}${seta}</th>`;
    }).join('');

    const body = pagina.map((row) => `<tr>${columns.map((c) => c.cell(row)).join('')}</tr>`).join('');

    table.innerHTML = `<thead><tr>${head}</tr></thead><tbody>${body}</tbody>`;

    table.querySelectorAll('th.sortable').forEach((th) => {
        th.onclick = () => {
            const key = th.dataset.col;
            const dir = state.sort && state.sort.key === key && state.sort.dir === 'asc' ? 'desc' : 'asc';
            state.sort = { key, dir };
            draw(tblId);
        };
    });

    drawPager(tblId, state, total, inicio, pagina.length, totalPaginas);
}

/**
 * A toolbar é montada uma vez só: recriar o HTML a cada desenho faria o campo
 * de busca perder o foco no meio da digitação.
 */
function drawToolbar(tblId, state, encontrados, totalBruto) {
    if (!state.toolbarId) return;

    const barra = $(state.toolbarId);
    if (!barra) return;

    if (!state.toolbarMounted) {
        barra.innerHTML = `
            <div class="table-search">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>
                </svg>
                <input type="search" class="search-input" placeholder="Buscar em todas as colunas…"
                       value="${esc(state.search)}" autocomplete="off">
            </div>
            <span class="result-count"></span>
            <label class="per-page">
                por página
                <select class="per-page-select">
                    ${OPCOES_POR_PAGINA.map((n) => `<option value="${n}"${n === state.perPage ? ' selected' : ''}>${n}</option>`).join('')}
                </select>
            </label>`;

        const input = barra.querySelector('.search-input');
        input.oninput = () => {
            state.search = input.value;
            state.page = 1;
            draw(tblId);
        };

        barra.querySelector('.per-page-select').onchange = (evento) => {
            state.perPage = Number(evento.target.value);
            state.page = 1;
            draw(tblId);
        };

        state.toolbarMounted = true;
    }

    const contador = barra.querySelector('.result-count');
    if (contador) {
        contador.textContent = state.search.trim() === ''
            ? `${totalBruto} registro(s)`
            : `${encontrados} de ${totalBruto}`;
    }
}

function drawPager(tblId, state, total, inicio, naPagina, totalPaginas) {
    if (!state.pagerId) return;

    const caixa = $(state.pagerId);
    if (!caixa) return;

    const info = `<span class="page-info">${inicio + 1}–${inicio + naPagina} de ${total}</span>`;

    if (totalPaginas === 1) {
        caixa.innerHTML = `<div class="pagination">${info}</div>`;

        return;
    }

    const botao = (rotulo, pagina, { ativo = false, desabilitado = false } = {}) =>
        `<button type="button" class="page-btn${ativo ? ' active' : ''}"
                 data-page="${pagina}"${desabilitado ? ' disabled' : ''}>${rotulo}</button>`;

    const numeros = janelaDePaginas(state.page, totalPaginas)
        .map((n) => (n === '…'
            ? '<span class="page-gap">…</span>'
            : botao(String(n), n, { ativo: n === state.page })))
        .join('');

    caixa.innerHTML = `
        <div class="pagination">
            ${botao('‹', state.page - 1, { desabilitado: state.page === 1 })}
            ${numeros}
            ${botao('›', state.page + 1, { desabilitado: state.page === totalPaginas })}
            ${info}
        </div>`;

    caixa.querySelectorAll('.page-btn').forEach((botaoEl) => {
        botaoEl.onclick = () => {
            state.page = Number(botaoEl.dataset.page);
            draw(tblId);
        };
    });
}

/** Até 7 botões: 1 … 4 5 6 … 20 */
function janelaDePaginas(atual, total) {
    if (total <= MAX_BOTOES) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const paginas = new Set([1, total, atual, atual - 1, atual + 1]);

    if (atual <= 3) [2, 3, 4].forEach((n) => paginas.add(n));
    if (atual >= total - 2) [total - 1, total - 2, total - 3].forEach((n) => paginas.add(n));

    const ordenadas = [...paginas].filter((n) => n >= 1 && n <= total).sort((a, b) => a - b);

    const comLacunas = [];
    ordenadas.forEach((n, i) => {
        if (i > 0 && n - ordenadas[i - 1] > 1) comLacunas.push('…');
        comLacunas.push(n);
    });

    return comLacunas;
}
