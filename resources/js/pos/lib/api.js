/**
 * Pemanggil API kasir. Memakai sesi login yang sama (cookie) + token CSRF.
 * Kesalahan selalu diubah menjadi kalimat sederhana untuk ditampilkan ke kasir.
 */

export class ApiError extends Error {
    constructor(message, { status = 0, errors = {}, offline = false } = {}) {
        super(message);
        this.status = status;
        this.errors = errors;
        this.offline = offline;
    }
}

function xsrfToken() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

export async function request(method, url, body = undefined) {
    let response;
    try {
        response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
    } catch {
        throw new ApiError('Tidak tersambung ke internet. Periksa sinyal lalu coba lagi.', { offline: true });
    }

    if (response.status === 401 || response.status === 419) {
        throw new ApiError('Sesi masuk sudah habis. Silakan masuk lagi.', { status: response.status });
    }

    let json = {};
    try {
        json = await response.json();
    } catch {
        json = {};
    }

    if (!response.ok) {
        const errors = json.errors ?? {};
        const first = Object.values(errors)[0]?.[0];
        const message =
            first ??
            json.message ??
            (response.status === 403 ? 'Anda tidak punya izin untuk ini.' : 'Terjadi masalah. Silakan coba lagi.');
        throw new ApiError(message, { status: response.status, errors });
    }

    return json;
}

export const api = {
    get: (url) => request('GET', url),
    post: (url, body = {}) => request('POST', url, body),
    put: (url, body = {}) => request('PUT', url, body),
};
