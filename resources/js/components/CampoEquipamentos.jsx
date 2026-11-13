import { Group, Radio, Stack, Text } from '@mantine/core';

export const CONDICOES = [
    ['nao_se_aplica', 'Não se aplica'],
    ['com_defeito', 'Com defeito'],
    ['funcionamento_ok', 'Funcionamento OK'],
];

const rotuloDaCondicao = (condicao) => CONDICOES.find(([valor]) => valor === condicao)?.[1];

/**
 * Estado do formulário: `{ [id]: condicao }` com todos os equipamentos. O que o
 * serviço não tem vinculado começa em "não se aplica" (MEL02).
 */
export const condicoesIniciais = (equipamentos, vinculados = []) => {
    const porId = Object.fromEntries(vinculados.map((v) => [v.id, v.condicao]));

    return Object.fromEntries(equipamentos.map((e) => [e.id, porId[e.id] ?? 'nao_se_aplica']));
};

/** Uma linha de rádios por equipamento; cada um aceita exatamente uma condição. */
export default function CampoEquipamentos({ equipamentos, value, onChange, errors = {} }) {
    return (
        <Stack gap="xs">
            <Text fw={500} size="sm">Verificação dos equipamentos</Text>
            {equipamentos.map((equipamento) => (
                <Radio.Group
                    key={equipamento.id}
                    name={`equipamento-${equipamento.id}`}
                    label={equipamento.nome}
                    value={value[equipamento.id] ?? 'nao_se_aplica'}
                    onChange={(condicao) => onChange({ ...value, [equipamento.id]: condicao })}
                    error={errors[`equipamentos.${equipamento.id}`]}
                >
                    <Group gap="md" mt={4}>
                        {CONDICOES.map(([condicao, rotulo]) => (
                            <Radio key={condicao} value={condicao} label={rotulo} />
                        ))}
                    </Group>
                </Radio.Group>
            ))}
            {errors.equipamentos && <Text c="red" size="sm">{errors.equipamentos}</Text>}
        </Stack>
    );
}

/** Resumo para a listagem: só os vinculados, já que o resto "não se aplica". */
export function ResumoEquipamentos({ equipamentos, vinculados = [] }) {
    const nomePorId = Object.fromEntries(equipamentos.map((e) => [e.id, e.nome]));

    return vinculados.map((v) => (
        <Text key={v.id} size="xs" c={v.condicao === 'com_defeito' ? 'red' : 'dimmed'}>
            {nomePorId[v.id]}: {rotuloDaCondicao(v.condicao)}
        </Text>
    ));
}
