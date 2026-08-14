/* Pedaços de HTML compartilhados pelas telas: badges, pills e os steps. */

import { $, esc, stateBox } from './dom.js';

const BADGES = {
    linked: 'badge-ok',
    posted: 'badge-ok',
    pending: 'badge-warn',
    blocked: 'badge-warn',
    error: 'badge-err',
    received: 'badge-info',
    ready: 'badge-info',
    ignored: 'badge-off',
};

export function badge(status) {
    return `<span class="badge ${BADGES[status] ?? 'badge-off'}">${esc(status)}</span>`;
}

/** Vínculo como pill "sistema : código", colorido pelo status. */
export function linkPill(link) {
    const modifier = link.status === 'pending' ? ' pend' : link.status === 'error' ? ' err' : '';
    const code = link.external_code || link.status;

    return `<span class="pill${modifier}"><b>${esc(link.system_id)}</b> : ${esc(code)}</span>`;
}

/** Fluxo da última ação: passo com tabela ganha tag colorida, sem tabela é só a frase. */
export function renderSteps(boxId, steps) {
    const box = $(boxId);

    if (!steps || steps.length === 0) {
        box.innerHTML = stateBox('Nenhum passo registrado.');
        return;
    }

    box.innerHTML = steps.map((step, index) => {
        const tag = step.table
            ? `<span class="step-tag op-${esc((step.op ?? '').toLowerCase())}">${esc(step.op)} ${esc(step.table)}</span>`
            : '';

        return `
            <div class="step-row">
                <span class="step-num">${index + 1}.</span>
                ${tag}
                <span class="step-desc">${esc(step.desc)}</span>
            </div>`;
    }).join('');
}

/** Valor monetário no padrão pt-BR. */
export function money(value) {
    const number = Number(value);
    return number ? number.toLocaleString('pt-BR', { minimumFractionDigits: 2 }) : '';
}
