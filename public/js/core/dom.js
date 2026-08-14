/* Utilidades de DOM usadas por todas as telas. */

export const $ = (id) => document.getElementById(id);

/** Escapa texto que vai para innerHTML — tudo que vem da API passa por aqui. */
export function esc(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
    }[c]));
}

/** Caixa de estado vazio/erro dentro de um container qualquer. */
export function stateBox(message, kind = '') {
    return `<div class="state-box ${kind}"><span>${esc(message)}</span></div>`;
}

/** Mesma caixa, com spinner — para espera provocada por um clique. */
export function loadingBox(message = 'Carregando…') {
    return `<div class="state-box"><div class="spinner"></div><span>${esc(message)}</span></div>`;
}

/**
 * Delegação de clique: um único listener no container atende os elementos
 * que a tela redesenha o tempo todo.
 */
export function onClick(container, selector, handler) {
    container.addEventListener('click', (event) => {
        const target = event.target.closest(selector);
        if (target && container.contains(target)) handler(target, event);
    });
}
