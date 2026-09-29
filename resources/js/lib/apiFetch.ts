const readXsrfToken = (): string | null => {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie
        ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length))
        : null;
};

export const apiFetch = async (
    input: RequestInfo | URL,
    init: RequestInit = {},
): Promise<Response> => {
    const method = (init.method ?? 'GET').toUpperCase();
    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');

    if (!['GET', 'HEAD', 'OPTIONS'].includes(method)) {
        if (!readXsrfToken()) {
            await fetch('/sanctum/csrf-cookie', {
                credentials: 'same-origin',
            });
        }

        const token = readXsrfToken();
        if (token) headers.set('X-XSRF-TOKEN', token);
    }

    return fetch(input, {
        ...init,
        credentials: 'same-origin',
        headers,
    });
};
