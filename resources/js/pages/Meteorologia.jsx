import { Head } from '@inertiajs/react';
import { Alert, Anchor, Paper, Stack, Table, Text, Title } from '@mantine/core';
import AppLayout from '@/layouts/AppLayout';

const numero = (valor) => valor == null ? 'Indisponível' : new Intl.NumberFormat('pt-BR', { maximumFractionDigits: 2 }).format(valor);
const horario = (valor) => new Intl.DateTimeFormat('pt-BR', {
    timeZone: 'America/Sao_Paulo', day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
}).format(new Date(valor));

export default function Meteorologia({ previsao }) {
    const { registros, status, consultado_em: consultadoEm } = previsao;
    return (
        <>
            <Head title="Meteorologia" />
            <Stack>
                <Title order={2}>Meteorologia</Title>
                <Text>São Sebastião — SP · Horário de São Paulo (UTC−3)</Text>
                <Text size="sm">{registros.length} registros horários disponibilizados pela fonte. Data de geração não informada.</Text>
                {consultadoEm && <Text size="sm">Consultado em: {horario(consultadoEm)}</Text>}
                {status === 'cache' && <Alert color="yellow">Não foi possível atualizar a fonte. Exibindo a última consulta válida em cache.</Alert>}
                {status === 'expirada' && <Alert color="yellow">O período desta previsão já terminou. Os registros abaixo são antigos.</Alert>}
                {status === 'indisponivel' && <Alert color="yellow">Previsão indisponível no momento. Tente novamente mais tarde.</Alert>}
                {registros.length > 0 && (
                    <Paper shadow="xs" radius="md" p="md">
                        <Table.ScrollContainer minWidth={1200}>
                            <Table striped highlightOnHover>
                                <Table.Caption>Previsão horária de São Sebastião</Table.Caption>
                                <Table.Thead><Table.Tr>
                                    {['Data e hora', 'Temperatura (°C)', 'Chuva (%)', 'Chuva (mm)', 'Vento (km/h)', 'Rajadas (km/h)', 'Direção do vento (°)', 'Visibilidade (m)', 'Ondas (m)', 'Período das ondas (s)', 'Direção das ondas (°)'].map((label) => <Table.Th key={label}>{label}</Table.Th>)}
                                </Table.Tr></Table.Thead>
                                <Table.Tbody>
                                    {registros.map((registro) => (
                                        <Table.Tr key={registro.data_hora}>
                                            <Table.Td>{horario(`${registro.data_hora}:00-03:00`)}</Table.Td>
                                            {['temperatura_c', 'chance_chuva_pct', 'volume_chuva_mm', 'vento_kmh', 'vento_rajada_kmh', 'vento_direcao_graus', 'visibilidade_m', 'onda_altura_m', 'onda_periodo_s', 'onda_direcao_graus'].map((campo) => <Table.Td key={campo}>{numero(registro[campo])}</Table.Td>)}
                                        </Table.Tr>
                                    ))}
                                </Table.Tbody>
                            </Table>
                        </Table.ScrollContainer>
                    </Paper>
                )}
                <Text size="sm" c="dimmed">Fonte: <Anchor href="https://github.com/leonardogoncales43-ctrl/PrevisaoMarinaFlow" target="_blank" rel="noopener noreferrer">PrevisaoMarinaFlow / Leonardo</Anchor> · Dados meteorológicos e marítimos: Open-Meteo.</Text>
            </Stack>
        </>
    );
}

Meteorologia.layout = (page) => <AppLayout>{page}</AppLayout>;
