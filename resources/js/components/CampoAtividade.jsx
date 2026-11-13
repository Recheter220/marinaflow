import { Group, Radio } from '@mantine/core';

export const ATIVIDADES = [
    ['lavagem', 'Lavagem — OK (executada)'],
    ['limpeza', 'Limpeza — OK (executada)'],
];

/**
 * Atividade executada da verificação (MEL02): exatamente uma. Na edição não é
 * obrigatória, para o registro antigo sem atividade continuar podendo ser salvo.
 */
export default function CampoAtividade({ value, onChange, error, obrigatorio = true }) {
    return (
        <Radio.Group
            name="atividade"
            label="Atividade executada (OK)"
            withAsterisk={obrigatorio}
            value={value ?? ''}
            onChange={onChange}
            error={error}
        >
            <Group gap="md" mt={4}>
                {ATIVIDADES.map(([atividade, rotulo]) => (
                    <Radio key={atividade} value={atividade} label={rotulo} required={obrigatorio} />
                ))}
            </Group>
        </Radio.Group>
    );
}
