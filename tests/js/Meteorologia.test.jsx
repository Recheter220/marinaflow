import { screen, within } from '@testing-library/react';
import Meteorologia from '@/pages/Meteorologia';
import AppLayout from '@/layouts/AppLayout';
import dados from '../Fixtures/meteorologia.json';
import { funcionario, admin, renderPagina } from './render';

const previsao = { registros: dados, status: 'disponivel', consultado_em: '2026-11-13T18:00:00Z' };

describe('Meteorologia', () => {
    it('exibe 24 horas, unidades originais, zero e horário de São Paulo', () => {
        renderPagina(<Meteorologia previsao={previsao} />, { auth: funcionario });
        expect(screen.getAllByRole('row')).toHaveLength(25);
        expect(screen.getByText('13/11, 10:00')).toBeInTheDocument();
        expect(screen.getByText('Consultado em: 13/11, 15:00')).toBeInTheDocument();
        for (const label of ['Temperatura (°C)', 'Chuva (%)', 'Chuva (mm)', 'Vento (km/h)', 'Rajadas (km/h)', 'Visibilidade (m)', 'Ondas (m)', 'Período das ondas (s)']) {
            expect(screen.getByRole('columnheader', { name: label })).toBeInTheDocument();
        }
        expect(within(screen.getAllByRole('row')[1]).getByText('0')).toBeInTheDocument();
        expect(screen.getByText(/Data de geração não informada/)).toBeInTheDocument();
    });

    it.each([
        ['cache', /última consulta válida em cache/],
        ['expirada', /período desta previsão já terminou/],
        ['indisponivel', /Previsão indisponível no momento/],
    ])('informa o estado %s', (status, mensagem) => {
        renderPagina(<Meteorologia previsao={{ ...previsao, status, registros: status === 'indisponivel' ? [] : dados }} />);
        expect(screen.getByText(mensagem)).toBeInTheDocument();
        if (status === 'indisponivel') expect(screen.queryByRole('table')).not.toBeInTheDocument();
    });

    it('mostra valor nulo como indisponível', () => {
        renderPagina(<Meteorologia previsao={{ ...previsao, registros: [{ ...dados[0], onda_altura_m: null }] }} />);
        expect(screen.getByText('Indisponível')).toBeInTheDocument();
    });

    it.each([funcionario, admin])('oferece o menu aos dois perfis', (auth) => {
        renderPagina(<AppLayout />, { auth, url: '/meteorologia' });
        const link = screen.getByRole('link', { name: /Meteorologia/ });
        expect(link).toHaveAttribute('href', '/meteorologia');
        expect(link).toHaveAttribute('data-active', 'true');
    });
});
