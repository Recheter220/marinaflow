import { Group, Radio, Stack, Text } from '@mantine/core';

export const EQUIPAMENTOS = [
    ['buzina', 'Buzina'],
    ['guincho_ancora', 'Guincho da âncora'],
    ['motor', 'Motor'],
    ['luzes_navegacao', 'Luzes de navegação'],
    ['radio_som', 'Rádio de som'],
    ['radio_vhf', 'Rádio VHF'],
];

const CONDICOES = [
    ['nao_se_aplica', 'Não se aplica'],
    ['com_defeito', 'Com defeito'],
    ['funcionamento_ok', 'Funcionamento OK'],
];

export const novaVerificacao = () => ({
    equipamentos: Object.fromEntries(EQUIPAMENTOS.map(([codigo]) => [codigo, ''])),
    atividade: '',
});

export default function VerificacaoItens({ value, onChange, errors = {} }) {
    return (
        <Stack gap="md">
            <Text fw={500}>Verificação dos equipamentos</Text>
            {EQUIPAMENTOS.map(([codigo, nome]) => (
                <Radio.Group
                    key={codigo}
                    name={`equipamento-${codigo}`}
                    label={nome}
                    withAsterisk
                    value={value.equipamentos[codigo]}
                    onChange={(condicao) => onChange({
                        ...value,
                        equipamentos: { ...value.equipamentos, [codigo]: condicao },
                    })}
                    error={errors[`verificacao_itens.equipamentos.${codigo}`]}
                >
                    <Group gap="sm" mt="xs">
                        {CONDICOES.map(([condicao, label]) => (
                            <Radio key={condicao} value={condicao} label={label} required />
                        ))}
                    </Group>
                </Radio.Group>
            ))}
            <Radio.Group
                name="atividade-executada"
                label="Atividade executada (OK)"
                description="Selecione uma atividade executada."
                withAsterisk
                value={value.atividade}
                onChange={(atividade) => onChange({ ...value, atividade })}
                error={errors['verificacao_itens.atividade']}
            >
                <Group mt="xs">
                    <Radio value="lavagem" label="Lavagem — OK (executada)" required />
                    <Radio value="limpeza" label="Limpeza — OK (executada)" required />
                </Group>
            </Radio.Group>
            {(errors.verificacao_itens || errors['verificacao_itens.equipamentos']) && (
                <Text c="red" size="sm">{errors.verificacao_itens || errors['verificacao_itens.equipamentos']}</Text>
            )}
        </Stack>
    );
}

export function ResumoVerificacao({ value }) {
    return (
        <Stack gap={2}>
            {EQUIPAMENTOS.map(([codigo, nome]) => {
                const condicao = value.equipamentos[codigo];
                return (
                    <Text key={codigo} size="sm" c={condicao === 'com_defeito' ? 'red' : undefined}>
                        {nome}: {CONDICOES.find(([key]) => key === condicao)?.[1] ?? 'Sem classificação'}
                    </Text>
                );
            })}
            <Text size="sm">{value.atividade === 'lavagem' ? 'Lavagem' : 'Limpeza'} — OK (executada)</Text>
        </Stack>
    );
}
