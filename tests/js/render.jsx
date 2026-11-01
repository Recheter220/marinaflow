import { render } from '@testing-library/react';
import { usePage } from '@inertiajs/react';
import { MantineProvider } from '@mantine/core';
import { theme } from '@/theme';

/**
 * Renderiza uma página como o Inertia faria: props da página no componente,
 * props compartilhadas (`auth`, `flash`) no `usePage()`.
 */
export function renderPagina(ui, { auth, flash = {}, url = '/' } = {}) {
    usePage.mockReturnValue({ props: { auth, flash }, url });

    return render(<MantineProvider theme={theme} env="test">{ui}</MantineProvider>);
}

export const admin = { user: { id: 1, name: 'Ana Admin', email: 'admin@marinaflow.local', is_admin: true, funcionario_id: null } };

export const funcionario = { user: { id: 2, name: 'Fábio', email: 'fabio@marinaflow.local', is_admin: false, funcionario_id: 7 } };
