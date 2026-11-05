import { screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/react';
import Historico from '@/pages/Historico';
import { novaVerificacao } from '@/components/VerificacaoItens';
import { admin, funcionario, renderPagina } from './render';

const embarcacoes = [
    { id: 3, nome: 'Maré Alta', identificacao: 'BR-1234' },
    { id: 4, nome: 'Vento Sul', identificacao: 'BR-5678' },
];

const tiposServico = [
    { id: 1, nome: 'Limpeza' },
    { id: 2, nome: 'Motor' },
];

const servico = (dados) => ({
    id: 1,
    embarcacao_nome: 'Maré Alta',
    funcionario_id: 7,
    funcionario_nome: 'Fábio',
    tipos: [{ id: 1, nome: 'Limpeza' }],
    observacao: null,
    status: 'em_execucao',
    fotos: [],
    created_at: '2026-10-01T12:00:00Z',
    updated_at: '2026-10-01T12:00:00Z',
    ...dados,
});

const renderHistorico = ({ servicos = [servico()], filtros = {}, auth = admin } = {}) =>
    renderPagina(<Historico servicos={servicos} embarcacoes={embarcacoes} tipos_servico={tiposServico} filtros={filtros} />, { auth });

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
    it('exibe MEL02 junto das observações e fotos existentes', () => {
        const verificacao = novaVerificacao();
        Object.keys(verificacao.equipamentos).forEach((codigo) => {
            verificacao.equipamentos[codigo] = 'nao_se_aplica';
        });
        verificacao.equipamentos.motor = 'com_defeito';
        verificacao.atividade = 'limpeza';
        renderHistorico({ servicos: [servico({
            verificacao_itens: verificacao,
            observacao: 'Motor falha após ligar',
            fotos: [{ id: 5, url: '/foto-assinada', nome_original: 'motor.jpg' }],
        })] });
        expect(screen.getByText('Motor: Com defeito')).toBeInTheDocument();
        expect(screen.getByText('Limpeza — OK (executada)')).toBeInTheDocument();
        expect(screen.getByText('Obs: Motor falha após ligar')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Ampliar foto motor.jpg' })).toBeInTheDocument();
        expect(screen.queryByText('Registro antigo — sem classificação MEL02')).not.toBeInTheDocument();
    });

    it('mantém itens legados sem inferir classificações', () => {
        renderHistorico({ servicos: [servico({ tipos: [{ id: 2, nome: 'Motor' }] })] });
        expect(screen.getByText('Motor')).toBeInTheDocument();
        expect(screen.getByText('Registro antigo — sem classificação MEL02')).toBeInTheDocument();
        expect(screen.queryByText('Motor: Funcionamento OK')).not.toBeInTheDocument();
    });

    it('mostra itens originais e tipos vinculados depois de classificar legado', () => {
        const verificacao = novaVerificacao();
        Object.keys(verificacao.equipamentos).forEach((codigo) => {
            verificacao.equipamentos[codigo] = 'funcionamento_ok';
        });
        verificacao.atividade = 'lavagem';
        renderHistorico({ servicos: [servico({
            verificacao_itens: verificacao,
            tipos_legados: [{ id: 1, nome: 'Limpeza' }, { id: 3, nome: 'Pintura' }],
            tipos: [{ id: 2, nome: 'Lavagem' }, { id: 3, nome: 'Pintura' }],
        })] });
        expect(screen.getByText('Itens do registro original: Limpeza, Pintura')).toBeInTheDocument();
        expect(screen.getByText('Tipos vinculados: Lavagem, Pintura')).toBeInTheDocument();
        expect(screen.getByText('Lavagem — OK (executada)')).toBeInTheDocument();
    });

});
