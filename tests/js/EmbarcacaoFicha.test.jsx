import { screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import EmbarcacaoFicha from '@/pages/EmbarcacaoFicha';
import { admin, funcionario, renderPagina } from './render';

vi.mock('@inertiajs/react', async (importOriginal) => {
    const original = await importOriginal();
    const { createElement, forwardRef, Suspense } = await import('react');

    return {
        ...original,
        Deferred: ({ children, fallback }) => createElement(Suspense, { fallback }, children),
    };
});

const embarcacao = {
    id: 5,
    nome: 'Estrela do Mar',
    identificacao: 'BR-9999-ZZZ',
    modelo: 'Focker 280',
    tipo: 'lancha',
    comprimento: 8.5,
    ano_fabricacao: 2021,
    cliente_responsavel: 'João da Silva',
    status: 'ativa',
};

const resumo = {
    total_servicos: 12,
    ultimo_servico: '2026-10-15',
    servicos_em_execucao: 2,
    custo_pecas: null,
    tipos_frequentes: [
        { nome: 'Limpeza', total: 8 },
        { nome: 'Motor', total: 4 },
    ],
};

const servicos = {
    data: [
        {
            id: 1,
            data_execucao: '2026-10-15',
            status: 'concluido',
            status_rotulo: 'Concluído',
            observacao: 'Revisão completa',
            funcionario: { id: 7, nome: 'Fábio' },
            tipos: [{ id: 1, nome: 'Motor' }],
            fotos_count: 3,
        },
    ],
    meta: { current_page: 1, last_page: 1, per_page: 20, total: 1 },
};

const fotos = [
    {
        id: 10,
        servico_id: 1,
        disk: 'local',
        path: 'servicos/1/foto.jpg',
        nome_original: 'foto.jpg',
        servico_data: '2026-10-15',
        url: 'https://example.com/foto.jpg',
    },
];

const renderFicha = (props = {}) =>
    renderPagina(
        <EmbarcacaoFicha
            embarcacao={embarcacao}
            resumo={resumo}
            servicos={servicos}
            fotos={fotos}
            {...props}
        />,
        { auth: admin },
    );

describe('EmbarcacaoFicha', () => {
    it('exibe o nome e a identificação da embarcação no cabeçalho', () => {
        renderFicha();

        expect(screen.getByRole('heading', { name: 'Estrela do Mar' })).toBeInTheDocument();
        expect(screen.getByText('BR-9999-ZZZ')).toBeInTheDocument();
    });

    it('exibe o badge de status da embarcação', () => {
        renderFicha();

        expect(screen.getByText('Ativa')).toBeInTheDocument();
    });

    it('exibe os detalhes da embarcação no painel de cabeçalho', () => {
        renderFicha();

        expect(screen.getByText('João da Silva')).toBeInTheDocument();
        expect(screen.getByText('2021')).toBeInTheDocument();
        expect(screen.getByText('8.5 m')).toBeInTheDocument();
    });

    it('renderiza as três abas', () => {
        renderFicha();

        expect(screen.getByRole('tab', { name: /Linha do Tempo/i })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: /Galeria/i })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: /Resumo/i })).toBeInTheDocument();
    });

    it('exibe o serviço na aba Linha do Tempo ativa por padrão', () => {
        renderFicha();

        expect(screen.getByText('Revisão completa')).toBeInTheDocument();
        expect(screen.getByText('Concluído')).toBeInTheDocument();
        expect(screen.getByText('Fábio')).toBeInTheDocument();
    });

    it('exibe badge dos tipos de serviço na linha do tempo', () => {
        renderFicha();

        expect(screen.getByText('Motor')).toBeInTheDocument();
    });

    it('exibe contagem de fotos na linha do tempo', () => {
        renderFicha();

        expect(screen.getByText('3 fotos')).toBeInTheDocument();
    });

    it('não exibe botão "Carregar mais" quando está na última página', () => {
        renderFicha();

        expect(screen.queryByRole('button', { name: /Carregar mais/i })).not.toBeInTheDocument();
    });

    it('exibe botão "Carregar mais" quando há mais páginas', () => {
        renderFicha({
            servicos: {
                ...servicos,
                meta: { current_page: 1, last_page: 3, per_page: 20, total: 55 },
            },
        });

        expect(screen.getByRole('button', { name: /Carregar mais/i })).toBeInTheDocument();
    });

    it('navega para a página seguinte ao clicar em "Carregar mais"', async () => {
        const { router } = await import('@inertiajs/react');

        renderFicha({
            servicos: {
                ...servicos,
                meta: { current_page: 1, last_page: 2, per_page: 20, total: 25 },
            },
        });

        await userEvent.click(screen.getByRole('button', { name: /Carregar mais/i }));

        expect(router.get).toHaveBeenCalledWith(
            route('embarcacoes.show', embarcacao.id),
            { pagina: 2 },
            expect.objectContaining({ only: ['servicos'] }),
        );
    });

    it('troca para a aba Resumo e exibe métricas', async () => {
        renderFicha();

        await userEvent.click(screen.getByRole('tab', { name: /Resumo/i }));

        await waitFor(() => {
            expect(screen.getByText('12')).toBeInTheDocument();
            expect(screen.getByText('Total de serviços')).toBeInTheDocument();
            expect(screen.getByText('Em execução')).toBeInTheDocument();
        });
    });

    it('exibe os tipos frequentes na aba Resumo', async () => {
        renderFicha();

        await userEvent.click(screen.getByRole('tab', { name: /Resumo/i }));

        await waitFor(() => {
            expect(screen.getByText('Limpeza')).toBeInTheDocument();
            expect(screen.getByText('Serviços mais frequentes')).toBeInTheDocument();
        });
    });

    it('troca para a aba Galeria e exibe a miniatura', async () => {
        renderFicha();

        await userEvent.click(screen.getByRole('tab', { name: /Galeria/i }));

        await waitFor(() => {
            expect(screen.getByRole('img', { name: 'foto.jpg' })).toBeInTheDocument();
        });
    });

    it('exibe estado vazio na Galeria quando não há fotos', async () => {
        renderFicha({ fotos: [] });

        await userEvent.click(screen.getByRole('tab', { name: /Galeria/i }));

        await waitFor(() => {
            expect(screen.getByText('Nenhuma foto registrada')).toBeInTheDocument();
        });
    });

    it('exibe estado vazio na Linha do Tempo quando não há serviços', () => {
        renderFicha({ servicos: { data: [], meta: { current_page: 1, last_page: 1, per_page: 20, total: 0 } } });

        expect(screen.getByText('Nenhum serviço registrado')).toBeInTheDocument();
    });

    it('exibe embarcação inativa com badge correto', () => {
        renderFicha({ embarcacao: { ...embarcacao, status: 'inativa' } });

        expect(screen.getByText('Inativa')).toBeInTheDocument();
    });

    it('exibe embarcação em manutenção com badge correto', () => {
        renderFicha({ embarcacao: { ...embarcacao, status: 'em_manutencao' } });

        expect(screen.getByText('Em Manutenção')).toBeInTheDocument();
    });
});
