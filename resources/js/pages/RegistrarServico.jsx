import { useState } from 'react';
import { Head, useForm as useInertiaForm, usePage } from '@inertiajs/react';
import {
    Title, Button, Select, Checkbox, Textarea, Group, Stack, Paper, Text,
    Alert, SimpleGrid,
} from '@mantine/core';
import { DateInput } from '@mantine/dates';
import { notifications } from '@mantine/notifications';
import { IconTool, IconCheck, IconAlertCircle } from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';
import { SERVICO_OPTION_COLUMNS } from '@/utils/servicos';
import 'dayjs/locale/pt-br';

export default function RegistrarServico({ embarcacoes, funcionarios }) {
    const { auth } = usePage().props;
    const isAdmin = auth.user.is_admin;
    const funcionarioId = auth.user.funcionario_id;

    const [sucesso, setSucesso] = useState(false);

    const { data, setData, post, processing, errors, reset } = useInertiaForm({
        embarcacao_id: null,
        funcionario_id: isAdmin ? null : (funcionarioId ? String(funcionarioId) : null),
        descricao: [],
        data_execucao: new Date(),
        observacao: '',
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('servicos.store'), {
            // O backend espera uma string; a UI trabalha com uma lista de checkboxes.
            transform: (d) => ({
                ...d,
                embarcacao_id: d.embarcacao_id ? Number(d.embarcacao_id) : null,
                funcionario_id: d.funcionario_id ? Number(d.funcionario_id) : null,
                descricao: d.descricao.join(', '),
                data_execucao: d.data_execucao
                    ? new Date(d.data_execucao).toISOString().split('T')[0]
                    : null,
                observacao: d.observacao || null,
            }),
            preserveScroll: true,
            onSuccess: () => {
                reset('embarcacao_id', 'descricao', 'observacao');
                if (!isAdmin && funcionarioId) {
                    setData('funcionario_id', String(funcionarioId));
                }
                setSucesso(true);
                notifications.show({
                    title: 'Serviço registrado',
                    message: "O serviço foi registrado e está 'Em Execução'",
                    color: 'green',
                    icon: <IconCheck size={16} />,
                });
            },
        });
    };

    const embarcacaoOptions = embarcacoes.map((e) => ({
        value: String(e.id),
        label: `${e.nome} — ${e.identificacao}`,
    }));

    const funcionarioOptions = funcionarios.map((f) => ({
        value: String(f.id),
        label: `${f.nome}${f.cargo ? ` (${f.cargo})` : ''}`,
    }));

    const faltaCadastro = embarcacoes.length === 0 || (isAdmin && funcionarios.length === 0);

    return (
        <>
            <Head title="Registrar Serviço" />

            <Group gap="sm" mb="lg">
                <IconTool size={28} stroke={1.5} color="var(--mantine-color-blue-6)" />
                <Title order={2}>Registrar Serviço</Title>
            </Group>

            {sucesso && (
                <Alert
                    icon={<IconCheck size={16} />}
                    title="Serviço registrado com sucesso!"
                    color="green"
                    mb="md"
                    withCloseButton
                    onClose={() => setSucesso(false)}
                >
                    O serviço foi iniciado e já está disponível no histórico.
                </Alert>
            )}

            {faltaCadastro ? (
                <Alert icon={<IconAlertCircle size={16} />} title="Cadastros necessários" color="yellow">
                    <Text size="sm">
                        {isAdmin
                            ? 'Para registrar um serviço, é necessário ter pelo menos uma embarcação e um funcionário ativo cadastrados.'
                            : 'Você não possui embarcações vinculadas ou não há funcionários ativos.'}
                    </Text>
                </Alert>
            ) : (
                <Paper shadow="xs" p="xl" radius="md" maw={700}>
                    <form onSubmit={submit}>
                        <Stack gap="md">
                            <Select
                                label="Embarcação"
                                placeholder="Selecione a embarcação"
                                data={embarcacaoOptions}
                                searchable
                                required
                                nothingFoundMessage="Nenhuma embarcação vinculada encontrada"
                                value={data.embarcacao_id}
                                onChange={(v) => setData('embarcacao_id', v)}
                                error={errors.embarcacao_id}
                            />

                            <Select
                                label="Funcionário Responsável"
                                placeholder="Selecione o funcionário"
                                data={funcionarioOptions}
                                searchable
                                required
                                disabled={!isAdmin}
                                nothingFoundMessage="Nenhum funcionário ativo encontrado"
                                value={data.funcionario_id}
                                onChange={(v) => setData('funcionario_id', v)}
                                error={errors.funcionario_id}
                            />

                            <DateInput
                                label="Data de Execução"
                                placeholder="Selecione a data"
                                required
                                disabled={!isAdmin}
                                locale="pt-br"
                                valueFormat="DD/MM/YYYY"
                                value={data.data_execucao}
                                onChange={(v) => setData('data_execucao', v)}
                                error={errors.data_execucao}
                            />

                            <Checkbox.Group
                                label="Serviços Realizados"
                                required
                                value={data.descricao}
                                onChange={(v) => setData('descricao', v)}
                                error={errors.descricao}
                            >
                                <SimpleGrid cols={2} spacing="xl" mt="xs">
                                    {SERVICO_OPTION_COLUMNS.map((column, index) => (
                                        <Stack key={index} gap="xs">
                                            {column.map((servico) => (
                                                <Checkbox key={servico} value={servico} label={servico} />
                                            ))}
                                        </Stack>
                                    ))}
                                </SimpleGrid>
                            </Checkbox.Group>

                            <Textarea
                                label="Observações"
                                placeholder="Observações adicionais (opcional)"
                                minRows={2}
                                autosize
                                value={data.observacao}
                                onChange={(e) => setData('observacao', e.currentTarget.value)}
                                error={errors.observacao}
                            />

                            <Group justify="flex-end" mt="md">
                                <Button
                                    variant="default"
                                    onClick={() => {
                                        reset('embarcacao_id', 'descricao', 'observacao');
                                        if (!isAdmin && funcionarioId) {
                                            setData('funcionario_id', String(funcionarioId));
                                        }
                                    }}
                                >
                                    Limpar
                                </Button>
                                <Button type="submit" loading={processing}>
                                    Registrar e Iniciar Serviço
                                </Button>
                            </Group>
                        </Stack>
                    </form>
                </Paper>
            )}
        </>
    );
}

RegistrarServico.layout = (page) => <AppLayout>{page}</AppLayout>;
