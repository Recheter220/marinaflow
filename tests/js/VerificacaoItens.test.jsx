import { useState } from 'react';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import VerificacaoItens, { EQUIPAMENTOS, novaVerificacao } from '@/components/VerificacaoItens';
import { renderPagina } from './render';

function Formulario() {
    const [value, setValue] = useState(novaVerificacao());
    return <form aria-label="Inspeção"><VerificacaoItens value={value} onChange={setValue} /></form>;
}

describe('Verificação MEL02', () => {
    it('exige uma classificação por equipamento e exatamente uma atividade', async () => {
        renderPagina(<Formulario />);
        const form = screen.getByRole('form', { name: 'Inspeção' });
        expect(form.checkValidity()).toBe(false);
        for (const [, nome] of EQUIPAMENTOS) {
            const grupo = screen.getByRole('radiogroup', { name: nome });
            await userEvent.click(within(grupo).getByRole('radio', { name: 'Não se aplica' }));
        }
        expect(form.checkValidity()).toBe(false);
        await userEvent.click(screen.getByRole('radio', { name: 'Lavagem — OK (executada)' }));
        expect(form.checkValidity()).toBe(true);
        await userEvent.click(screen.getByRole('radio', { name: 'Limpeza — OK (executada)' }));
        expect(screen.getByRole('radio', { name: 'Lavagem — OK (executada)' })).not.toBeChecked();
        expect(screen.getByRole('radio', { name: 'Limpeza — OK (executada)' })).toBeChecked();
        expect(screen.queryByRole('textbox')).not.toBeInTheDocument();
    });

    it('substitui a classificação sem acumular opções no equipamento', async () => {
        renderPagina(<Formulario />);
        const motor = within(screen.getByRole('radiogroup', { name: 'Motor' }));
        await userEvent.click(motor.getByRole('radio', { name: 'Com defeito' }));
        await userEvent.click(motor.getByRole('radio', { name: 'Funcionamento OK' }));
        expect(motor.getByRole('radio', { name: 'Com defeito' })).not.toBeChecked();
        expect(motor.getByRole('radio', { name: 'Funcionamento OK' })).toBeChecked();
    });
});
