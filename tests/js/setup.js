import '@testing-library/jest-dom/vitest';
import { vi } from 'vitest';

/**
 * O Ziggy injeta `route()` como global em runtime; no jsdom ele não existe.
 * O stub devolve uma URL legível derivada do nome, o que basta para afirmar
 * qual rota a tela pediu.
 */
globalThis.route = (name, params) => {
    const url = `/${name.replaceAll('.', '/')}`;

    return params === undefined ? url : `${url}/${params}`;
};

/**
 * As páginas recebem as props pelo `usePage()` e navegam pelo `router`, que
 * dependem de uma app Inertia montada. Os testes substituem os dois e afirmam
 * sobre as visitas pedidas, sem servidor nem rede.
 */
vi.mock('@inertiajs/react', async () => {
    const { createElement, forwardRef } = await import('react');

    return {
        Head: () => null,
        Link: forwardRef((props, ref) => createElement('a', { ...props, ref })),
        router: { get: vi.fn(), post: vi.fn(), put: vi.fn(), delete: vi.fn() },
        usePage: vi.fn(),
    };
});

// APIs de layout que o Mantine usa e o jsdom não implementa.
window.matchMedia ??= (query) => ({
    matches: false,
    media: query,
    onchange: null,
    addEventListener: () => {},
    removeEventListener: () => {},
    addListener: () => {},
    removeListener: () => {},
    dispatchEvent: () => false,
});

globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};

Element.prototype.scrollIntoView ??= () => {};

afterEach(() => {
    vi.clearAllMocks();
});
