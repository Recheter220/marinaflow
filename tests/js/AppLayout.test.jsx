import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import AppLayout from '@/layouts/AppLayout';
import { admin, funcionario, renderPagina } from './render';

const menu = () => within(screen.getByRole('navigation'));

describe('AppLayout', () => {
    it('mostra o menu principal a qualquer usuário', () => {
        renderPagina(<AppLayout />, { auth: funcionario });

        for (const [rotulo, href] of [
            ['Embarcações', '/embarcacoes'],
            ['Funcionários', '/funcionarios'],
            ['Registrar Serviço', '/servicos/novo'],
            ['Histórico', '/historico'],
        ]) {
            expect(menu().getByRole('link', { name: new RegExp(rotulo) })).toHaveAttribute('href', href);
        }
    });

    it('esconde a área de administração do funcionário', () => {
        renderPagina(<AppLayout />, { auth: funcionario });

        expect(menu().queryByRole('link', { name: /Usuários/ })).not.toBeInTheDocument();
        expect(menu().queryByText('Administração')).not.toBeInTheDocument();
        expect(screen.getByText('Funcionário')).toBeInTheDocument();
    });

    it('mostra a área de administração ao admin', () => {
        renderPagina(<AppLayout />, { auth: admin });

        expect(menu().getByText('Administração')).toBeInTheDocument();
        expect(menu().getByRole('link', { name: /Usuários/ })).toHaveAttribute('href', route('usuarios.index'));
        expect(screen.getByText('Administrador')).toBeInTheDocument();
    });

    it('destaca o item da página atual', () => {
        renderPagina(<AppLayout />, { auth: funcionario, url: '/historico' });

        expect(menu().getByRole('link', { name: /Histórico/ })).toHaveAttribute('data-active', 'true');
        expect(menu().getByRole('link', { name: /Embarcações/ })).not.toHaveAttribute('data-active');
    });

    it('encerra a sessão pelo botão de sair', async () => {
        renderPagina(<AppLayout />, { auth: funcionario });

        await userEvent.click(screen.getByRole('button'));

        expect(router.post).toHaveBeenCalledWith(route('logout'));
    });
});
