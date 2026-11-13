import { useState } from 'react';
import { screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import CampoEquipamentos, { condicoesIniciais } from '@/components/CampoEquipamentos';
import { renderPagina } from './render';

const equipamentos = [
    { id: 1, nome: 'Buzina' },
    { id: 3, nome: 'Motor' },
];

function Formulario({ vinculados }) {
    const [value, setValue] = useState(condicoesIniciais(equipamentos, vinculados));

    return <CampoEquipamentos equipamentos={equipamentos} value={value} onChange={setValue} />;
}

const grupo = (nome) => within(screen.getByRole('radiogroup', { name: nome }));

describe('CampoEquipamentos', () => {
    it('mostra como "Não se aplica" o equipamento sem vínculo', () => {
        renderPagina(<Formulario vinculados={[{ id: 3, condicao: 'com_defeito' }]} />);

        expect(grupo('Buzina').getByRole('radio', { name: 'Não se aplica' })).toBeChecked();
        expect(grupo('Motor').getByRole('radio', { name: 'Com defeito' })).toBeChecked();
    });

    it('troca a condição sem acumular opções no equipamento', async () => {
        renderPagina(<Formulario />);

        await userEvent.click(grupo('Motor').getByRole('radio', { name: 'Funcionamento OK' }));

        expect(grupo('Motor').getByRole('radio', { name: 'Funcionamento OK' })).toBeChecked();
        expect(grupo('Motor').getByRole('radio', { name: 'Não se aplica' })).not.toBeChecked();
        expect(grupo('Buzina').getByRole('radio', { name: 'Não se aplica' })).toBeChecked();
    });
});
