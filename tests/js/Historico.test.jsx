import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import Historico from '@/pages/Historico';
import { admin, funcionario, renderPagina } from './render';

const embarcacoes = [
    { id: 3, nome: 'Maré Alta', identificacao: 'BR-1234' },
    { id: 4, nome: 'Vento Sul', identificacao: 'BR-5678' },
];

const equipamentos = [
    { id: 1, nome: 'Buzina' },
    { id: 3, nome: 'Motor' },
];

const servico = (dados) => ({
    id: 1,
    embarcacao_nome: 'Maré Alta',
    funcionario_id: 7,
    funcionario_nome: 'Fábio',
    tipos: [{ id: 1, nome: 'Limpeza' }],
    equipamentos: [],
    observacao: null,
    status: 'em_execucao',
    fotos: [],
    created_at: '2026-10-01T12:00:00Z',
    updated_at: '2026-10-01T12:00:00Z',
    ...dados,
});

const renderHistorico = ({ servicos = [servico()], filtros = {}, auth = admin } = {}) =>
    renderPagina(<Historico servicos={servicos} embarcacoes={embarcacoes} equipamentos={equipamentos} filtros={filtros} />, { auth });

const filtro = () => screen.getByRole('textbox', { name: 'Filtrar por Embarcação' });

describe('Historico', () => {
    it('recarrega a lista filtrada pela embarcação escolhida', async () => {
        renderHistorico();

        await userEvent.click(filtro());
        await userEvent.click(screen.getByRole('option', { name: 'Vento Sul — BR-5678' }));

        expect(router.get).toHaveBeenCalledWith(
            route('servicos.index'),
            { embarcacao_id: '4' },
            expect.objectContaining({ only: ['servicos', 'filtros'] }),
        );
    });

    it('volta a todas as embarcações ao limpar o filtro', async () => {
        const { container } = renderHistorico({ filtros: { embarcacao_id: 3 } });

        expect(filtro()).toHaveValue('Maré Alta — BR-1234');

        await userEvent.click(container.querySelector('.mantine-Select-section button'));

        expect(router.get).toHaveBeenCalledWith(route('servicos.index'), { embarcacao_id: '' }, expect.anything());
    });

    it('diz que a embarcação filtrada não tem serviços', () => {
        renderHistorico({ servicos: [], filtros: { embarcacao_id: 3 } });

        expect(screen.getByText('Nenhum serviço registrado para esta embarcação')).toBeInTheDocument();
    });

    it('descreve o filtro conforme o papel', () => {
        renderHistorico({ auth: funcionario });

        expect(filtro()).toHaveAttribute('placeholder', 'Suas embarcações vinculadas');
    });

    it.each([
        ['em_execucao', 'Em Execução'],
        ['aguardando_vistoria', 'Aguardando Vistoria'],
        ['concluido', 'Concluído'],
        ['status_novo', 'status_novo'],
    ])('rotula o status %s como "%s"', (status, rotulo) => {
        renderHistorico({ servicos: [servico({ status })] });

        expect(screen.getByRole('cell', { name: rotulo })).toBeInTheDocument();
    });

    it('resume só os equipamentos vinculados ao serviço', () => {
        renderHistorico({ servicos: [servico({ equipamentos: [{ id: 3, condicao: 'com_defeito' }] })] });

        expect(screen.getByText('Motor: Com defeito')).toBeInTheDocument();
        expect(screen.queryByText(/^Buzina:/)).not.toBeInTheDocument();
    });

    it('envia a verificação completa ao salvar a edição', async () => {
        const { container } = renderHistorico({ servicos: [servico({ equipamentos: [{ id: 3, condicao: 'com_defeito' }] })] });

        // O botão do menu só tem nome no tooltip, que aparece no hover.
        await userEvent.click(container.querySelector('.mantine-ActionIcon-root'));
        await userEvent.click(screen.getByRole('menuitem', { name: 'Editar Registro' }));
        await userEvent.click(screen.getByRole('button', { name: 'Salvar Alterações' }));

        expect(router.put).toHaveBeenCalledWith(
            route('servicos.update', 1),
            expect.objectContaining({ equipamentos: { 1: 'nao_se_aplica', 3: 'com_defeito' } }),
            expect.anything(),
        );
    });

    const salvarEdicao = async (dados) => {
        const { container } = renderHistorico({ servicos: [servico(dados)] });

        await userEvent.click(container.querySelector('.mantine-ActionIcon-root'));
        await userEvent.click(screen.getByRole('menuitem', { name: 'Editar Registro' }));
        await userEvent.click(screen.getByRole('button', { name: 'Salvar Alterações' }));

        return router.put.mock.calls[0][1];
    };

    it('envia a atividade escolhida na edição', async () => {
        expect(await salvarEdicao({ atividade: 'limpeza' })).toMatchObject({ atividade: 'limpeza' });
    });

    it('salva registro antigo sem atividade sem inventar uma', async () => {
        expect(await salvarEdicao({ atividade: null })).not.toHaveProperty('atividade');
    });
});
