/* Cliente HTTP da API — um lugar só para cabeçalhos e tratamento de erro. */

export class ApiError extends Error {
    constructor(message, status, errors) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.errors = errors ?? null;
    }
}

/**
 * Chama a API e devolve o JSON já parseado.
 * Erro vira ApiError com a mensagem que o Laravel mandou (validação inclusive).
 */
export async function api(path, { method = 'GET', body } = {}) {
    const response = await fetch(path, {
        method,
        headers: {
            'Accept': 'application/json',
            ...(body !== undefined ? { 'Content-Type': 'application/json' } : {}),
        },
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const text = await response.text();
    let data = null;
    try {
        data = text ? JSON.parse(text) : null;
    } catch {
        data = null;
    }

    if (!response.ok) {
        throw new ApiError(errorMessage(data, text, response.status), response.status, data?.errors);
    }

    return data;
}

function errorMessage(data, text, status) {
    if (data?.errors) {
        const first = Object.values(data.errors)[0];
        if (Array.isArray(first) && first.length) return first[0];
    }
    if (data?.message) return data.message;
    return text?.trim() || `Erro ${status}`;
}
