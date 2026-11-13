import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import Embarcacoes from '@/pages/Embarcacoes';
import { admin, funcionario, renderPagina } from './render';

const embarcacao = {
    id: 1,
    nome: 'Maré Alta',
    identificacao: 'BR-1234',
    modelo: 'Focker 240',
    tipo: 'lancha',
    comprimento: 7.2,
    ano_fabricacao: 2019,
    cliente_responsavel: 'Carlos Lima',
    status: 'ativa',
    funcionario_id: 7,
    funcionario_nome: 'Fábio',
};

const renderEmbarcacoes = (auth, embarcacoes = [embarcacao]) =>
    renderPagina(<Embarcacoes embarcacoes={embarcacoes} funcionarios={[]} filtros={{}} />, { auth });

describe('Embarcacoes', () => {
    it('oferece o cadastro de embarcação ao admin', () => {
        renderEmbarcacoes(admin);

        expect(screen.getByRole('button', { name: 'Nova Embarcação' })).toBeInTheDocument();
    });

    it('não oferece o cadastro de embarcação ao funcionário', () => {
        renderEmbarcacoes(funcionario);

        expect(screen.queryByRole('button', { name: 'Nova Embarcação' })).not.toBeInTheDocument();
        expect(screen.queryByRole('columnheader', { name: 'Ações' })).not.toBeInTheDocument();
    });

    it('não oferece o primeiro cadastro ao funcionário sem embarcações', () => {
        renderEmbarcacoes(funcionario, []);

        expect(screen.getByText('Você não possui embarcações vinculadas')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Cadastrar primeira embarcação' })).not.toBeInTheDocument();
    });

    it('oferece o primeiro cadastro ao admin com a lista vazia', () => {
        renderEmbarcacoes(admin, []);

        expect(screen.getByRole('button', { name: 'Cadastrar primeira embarcação' })).toBeInTheDocument();
    });

    it('abre o QR Code da embarcação com download do SVG e impressão', async () => {
        renderEmbarcacoes(funcionario);

        await userEvent.click(screen.getByRole('button', { name: 'QR Code de Maré Alta' }));

        expect(await screen.findByRole('img', { name: 'QR Code de Maré Alta' }))
            .toHaveAttribute('src', '/embarcacoes/qrcode/1');
        expect(screen.getByRole('link', { name: 'Baixar SVG' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Imprimir' })).toBeInTheDocument();
    });

    it('oferece a folha de etiquetas só ao admin', () => {
        renderEmbarcacoes(funcionario);
        expect(screen.queryByRole('button', { name: 'Etiquetas' })).not.toBeInTheDocument();
    });

    it('gera a folha de etiquetas pelo admin', async () => {
        renderEmbarcacoes(admin);

        await userEvent.click(screen.getByRole('button', { name: 'Etiquetas' }));

        expect(await screen.findByRole('link', { name: 'Gerar PDF' })).toHaveAttribute('target', '_blank');
    });
});
