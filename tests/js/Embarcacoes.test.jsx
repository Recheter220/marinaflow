import { screen } from '@testing-library/react';
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
});
