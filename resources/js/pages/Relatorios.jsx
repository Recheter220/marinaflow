import { useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Title, Button, Select, Group, Stack, Paper, Text, Alert, SimpleGrid } from '@mantine/core';
import { DatePickerInput } from '@mantine/dates';
import { IconFileText, IconDownload, IconAlertCircle } from '@tabler/icons-react';
import dayjs from 'dayjs';
import AppLayout from '@/layouts/AppLayout';
import 'dayjs/locale/pt-br';

/** Mesmo teto de `RelatorioServicosRequest::MAXIMO_DIAS`. */
const MAXIMO_DIAS = 366;
const FORMATO = 'YYYY-MM-DD';

/** O que o servidor recusaria, avisado antes de sair da tela. */
const validarPeriodo = (inicio, fim) => {
    const erros = {};

    if (!inicio) {
        erros.inicio = 'Informe a data inicial.';
    }

    if (!fim) {
        erros.fim = 'Informe a data final.';
    } else if (inicio && dayjs(fim).isBefore(inicio, 'day')) {
        erros.fim = 'A data final deve ser igual ou posterior à inicial.';
    } else if (inicio && dayjs(fim).diff(inicio, 'day') + 1 > MAXIMO_DIAS) {
        erros.fim = `O período pode ter no máximo ${MAXIMO_DIAS} dias.`;
    }

    return erros;
};

export default function Relatorios({ embarcacoes, funcionarios }) {
    const { auth, errors: errosDoServidor } = usePage().props;
    const isAdmin = auth.user.is_admin;

    const [inicio, setInicio] = useState(dayjs().startOf('month').format(FORMATO));
    const [fim, setFim] = useState(dayjs().endOf('month').format(FORMATO));
    const [embarcacaoId, setEmbarcacaoId] = useState(null);
    const [funcionarioId, setFuncionarioId] = useState(null);
    const [erros, setErros] = useState({});

    const erro = (campo) => erros[campo] ?? errosDoServidor?.[campo];

    /**
     * Navegação comum, não visita do Inertia: a resposta é um arquivo. Se o
     * servidor recusar, ele redireciona de volta para cá com `errors`.
     */
    const gerar = (e) => {
        e.preventDefault();

        const encontrados = validarPeriodo(inicio, fim);
        setErros(encontrados);

        if (Object.keys(encontrados).length > 0) {
            return;
        }

        window.location.assign(
            route('relatorios.servicos', {
                inicio,
                fim,
                ...(embarcacaoId ? { embarcacao_id: embarcacaoId } : {}),
                ...(isAdmin && funcionarioId ? { funcionario_id: funcionarioId } : {}),
            }),
        );
    };

    return (
        <>
            <Head title="Relatórios" />

            <Group gap="sm" mb="lg">
                <IconFileText size={28} stroke={1.5} color="var(--mantine-color-blue-6)" />
                <Title order={2}>Relatórios</Title>
            </Group>

            <Paper shadow="xs" p="xl" radius="md" maw={700}>
                <form onSubmit={gerar} noValidate>
                    <Stack gap="md">
                        <div>
                            <Title order={4}>Serviços executados</Title>
                            <Text size="sm" c="dimmed">
                                PDF com a listagem por data e o resumo do período
                                {isAdmin ? '.' : ', apenas com os seus serviços.'}
                            </Text>
                        </div>

                        <SimpleGrid cols={{ base: 1, xs: 2 }}>
                            <DatePickerInput
                                label="Data inicial"
                                required
                                locale="pt-br"
                                valueFormat="DD/MM/YYYY"
                                value={inicio}
                                onChange={setInicio}
                                error={erro('inicio')}
                            />
                            <DatePickerInput
                                label="Data final"
                                required
                                locale="pt-br"
                                valueFormat="DD/MM/YYYY"
                                minDate={inicio ?? undefined}
                                value={fim}
                                onChange={setFim}
                                error={erro('fim')}
                            />
                        </SimpleGrid>

                        <Select
                            label="Embarcação"
                            placeholder="Todas"
                            data={embarcacoes.map((e) => ({ value: String(e.id), label: `${e.nome} — ${e.identificacao}` }))}
                            searchable
                            clearable
                            nothingFoundMessage="Nenhuma embarcação encontrada"
                            value={embarcacaoId}
                            onChange={setEmbarcacaoId}
                            error={erro('embarcacao_id')}
                        />

                        {isAdmin && (
                            <Select
                                label="Funcionário"
                                placeholder="Todos"
                                data={funcionarios.map((f) => ({
                                    value: String(f.id),
                                    label: f.ativo ? f.nome : `${f.nome} (inativo)`,
                                }))}
                                searchable
                                clearable
                                nothingFoundMessage="Nenhum funcionário encontrado"
                                value={funcionarioId}
                                onChange={setFuncionarioId}
                                error={erro('funcionario_id')}
                            />
                        )}

                        {errosDoServidor?.formato && (
                            <Alert icon={<IconAlertCircle size={16} />} color="red">
                                {errosDoServidor.formato}
                            </Alert>
                        )}

                        <Group justify="flex-end">
                            <Button type="submit" leftSection={<IconDownload size={16} />}>
                                Gerar PDF
                            </Button>
                        </Group>
                    </Stack>
                </form>
            </Paper>
        </>
    );
}

Relatorios.layout = (page) => <AppLayout>{page}</AppLayout>;
