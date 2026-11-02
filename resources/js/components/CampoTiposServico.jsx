import { Checkbox, SimpleGrid, Stack } from '@mantine/core';

const ordenarPorNome = (a, b) => a.nome.localeCompare(b.nome, 'pt-BR', { sensitivity: 'base' });

/**
 * Checkboxes do catálogo de serviços (D-04), em duas colunas. Os valores são
 * os ids como string, que é o que o `Checkbox.Group` do Mantine trabalha.
 *
 * `tipos` são os ativos; `extras` são tipos já ligados ao serviço que saíram de
 * uso — aparecem na edição para não sumirem do registro sem querer.
 */
export default function CampoTiposServico({ tipos, extras = [], value, onChange, error, ...props }) {
    const idsAtivos = new Set(tipos.map((t) => t.id));
    const opcoes = [...tipos, ...extras.filter((t) => !idsAtivos.has(t.id))].sort(ordenarPorNome);
    const metade = Math.ceil(opcoes.length / 2);

    return (
        <Checkbox.Group label="Serviços Realizados" required value={value} onChange={onChange} error={error} {...props}>
            <SimpleGrid cols={2} spacing="xl" mt="xs">
                {[opcoes.slice(0, metade), opcoes.slice(metade)].map((coluna, index) => (
                    <Stack key={index} gap="xs">
                        {coluna.map((tipo) => (
                            <Checkbox key={tipo.id} value={String(tipo.id)} label={tipo.nome} />
                        ))}
                    </Stack>
                ))}
            </SimpleGrid>
        </Checkbox.Group>
    );
}
