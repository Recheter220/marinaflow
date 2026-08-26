import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Title, Select, Table, Badge, Group, Stack, Paper, Text, ActionIcon,
    Tooltip, Menu, Modal, Textarea, Button, Checkbox, SimpleGrid, Center,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { notifications } from '@mantine/notifications';
import { IconHistory, IconDotsVertical, IconCheck, IconShip, IconEdit } from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';
import { SERVICO_OPTION_COLUMNS } from '@/utils/servicos';

const STATUS_CONFIG = {
    em_execucao: { label: 'Em Execução', color: 'blue' },
    concluido: { label: 'Concluído', color: 'green' },
};

const formatarDataHora = (iso) => {
    if (!iso) {
        return '—';
    }

    const data = new Date(iso);

    if (Number.isNaN(data.getTime())) {
        return iso;
    }

    return data.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

export default function Historico({ servicos, embarcacoes, filtros }) {
    const { auth, flash } = usePage().props;
    const isAdmin = auth.user.is_admin;
    const funcionarioId = auth.user.funcionario_id;

    const [editando, setEditando] = useState(null);
    const [salvando, setSalvando] = useState(false);
    const embarcacaoSelecionada = filtros.embarcacao_id ? String(filtros.embarcacao_id) : null;

    useEffect(() => {
        if (flash?.success) {
            notifications.show({ title: 'Sucesso', message: flash.success, color: 'green' });
        }
    }, [flash]);

    const editForm = useForm({
        initialValues: { descricao: [], observacao: '', status: '' },
        validate: {
            descricao: (v) => (v.length === 0 ? 'Selecione ao menos um serviço' : null),
        },
    });

    const filtrarPorEmbarcacao = (valor) => {
        router.get(
            route('servicos.index'),
            { embarcacao_id: valor ?? '' },
            { preserveState: true, preserveScroll: true, replace: true, only: ['servicos', 'filtros'] },
        );
    };

    const abrirEditar = (srv) => {
        setEditando(srv);
        editForm.setValues({
            descricao: srv.descricao || [],
            observacao: srv.observacao || '',
            status: srv.status,
        });
    };

    const salvarEdicao = (values) => {
        setSalvando(true);
        router.put(
            route('servicos.update', editando.id),
            {
                descricao: values.descricao,
                observacao: values.observacao || null,
                status: values.status,
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditando(null),
                onError: (errors) => {
                    editForm.setErrors(errors);
                    notifications.show({
                        title: 'Erro ao atualizar',
                        message: Object.values(errors).join(' '),
                        color: 'red',
                    });
                },
                onFinish: () => setSalvando(false),
            },
        );
    };

    const concluir = (srv) => {
        router.put(
            route('servicos.update', srv.id),
            { status: 'concluido' },
            {
                preserveScroll: true,
                onError: (errors) =>
                    notifications.show({
                        title: 'Erro ao atualizar',
                        message: Object.values(errors).join(' '),
                        color: 'red',
                    }),
            },
        );
    };

    const podeEditar = (srv) =>
        isAdmin || (srv.status !== 'concluido' && srv.funcionario_id === funcionarioId);

    const podeConcluir = (srv) => isAdmin && srv.status === 'em_execucao';

    const embarcacaoOptions = embarcacoes.map((e) => ({
        value: String(e.id),
        label: `${e.nome} — ${e.identificacao}`,
    }));

    return (
        <>
            <Head title="Histórico" />

            <Group gap="sm" mb="lg">
                <IconHistory size={28} stroke={1.5} color="var(--mantine-color-blue-6)" />
                <Title order={2}>Histórico de Serviços</Title>
            </Group>

            <Paper shadow="xs" p="md" radius="md" mb="md">
                <Select
                    label="Filtrar por Embarcação"
                    placeholder={isAdmin ? 'Todas as embarcações' : 'Suas embarcações vinculadas'}
                    data={embarcacaoOptions}
                    searchable
                    clearable
                    nothingFoundMessage="Nenhuma embarcação encontrada"
                    leftSection={<IconShip size={16} />}
                    value={embarcacaoSelecionada}
                    onChange={filtrarPorEmbarcacao}
                />
            </Paper>

            {servicos.length === 0 ? (
                <Paper shadow="xs" p="xl" radius="md">
                    <Center>
                        <Stack align="center" gap="xs">
                            <IconHistory size={48} stroke={1} color="var(--mantine-color-gray-4)" />
                            <Text c="dimmed">
                                {embarcacaoSelecionada
                                    ? 'Nenhum serviço registrado para esta embarcação'
                                    : 'Nenhum serviço registrado'}
                            </Text>
                        </Stack>
                    </Center>
                </Paper>
            ) : (
                <Paper shadow="xs" radius="md" style={{ overflow: 'hidden' }}>
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th>Embarcação</Table.Th>
                                <Table.Th>Funcionário</Table.Th>
                                <Table.Th>Serviços Realizados</Table.Th>
                                <Table.Th>Status</Table.Th>
                                <Table.Th>Criado em</Table.Th>
                                <Table.Th>Atualizado em</Table.Th>
                                <Table.Th w={60}>Ações</Table.Th>
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {servicos.map((srv) => {
                                const statusConf = STATUS_CONFIG[srv.status] ?? {
                                    label: srv.status,
                                    color: 'gray',
                                };
                                const editavel = podeEditar(srv);
                                const concluivel = podeConcluir(srv);

                                return (
                                    <Table.Tr key={srv.id}>
                                        <Table.Td>{srv.embarcacao_nome || '—'}</Table.Td>
                                        <Table.Td>{srv.funcionario_nome || '—'}</Table.Td>
                                        <Table.Td>
                                            <Text size="sm" lineClamp={2}>
                                                {srv.descricao?.join(', ')}
                                            </Text>
                                            {srv.observacao && (
                                                <Text size="xs" c="dimmed" lineClamp={1}>
                                                    Obs: {srv.observacao}
                                                </Text>
                                            )}
                                        </Table.Td>
                                        <Table.Td>
                                            <Badge variant="light" color={statusConf.color} size="sm">
                                                {statusConf.label}
                                            </Badge>
                                        </Table.Td>
                                        <Table.Td>
                                            <Text size="xs" c="dimmed">
                                                {formatarDataHora(srv.created_at)}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td>
                                            <Text size="xs" c="dimmed">
                                                {formatarDataHora(srv.updated_at)}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td>
                                            {(editavel || concluivel) && (
                                                <Menu shadow="md" width={200}>
                                                    <Menu.Target>
                                                        <Tooltip label="Opções">
                                                            <ActionIcon variant="subtle" color="gray">
                                                                <IconDotsVertical size={16} />
                                                            </ActionIcon>
                                                        </Tooltip>
                                                    </Menu.Target>
                                                    <Menu.Dropdown>
                                                        {editavel && (
                                                            <Menu.Item
                                                                leftSection={<IconEdit size={14} />}
                                                                onClick={() => abrirEditar(srv)}
                                                            >
                                                                Editar Registro
                                                            </Menu.Item>
                                                        )}
                                                        {concluivel && (
                                                            <Menu.Item
                                                                leftSection={<IconCheck size={14} />}
                                                                color="green"
                                                                onClick={() => concluir(srv)}
                                                            >
                                                                Finalizar Serviço
                                                            </Menu.Item>
                                                        )}
                                                    </Menu.Dropdown>
                                                </Menu>
                                            )}
                                        </Table.Td>
                                    </Table.Tr>
                                );
                            })}
                        </Table.Tbody>
                    </Table>
                </Paper>
            )}

            <Modal
                opened={!!editando}
                onClose={() => setEditando(null)}
                title="Editar Registro de Serviço"
                centered
            >
                <form onSubmit={editForm.onSubmit(salvarEdicao)}>
                    <Stack gap="sm">
                        <Checkbox.Group label="Serviços Realizados" required {...editForm.getInputProps('descricao')}>
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

                        <Textarea label="Observações" minRows={3} {...editForm.getInputProps('observacao')} />

                        {isAdmin && editando?.status !== 'concluido' && (
                            <Select
                                label="Alterar Status"
                                data={[
                                    { value: 'em_execucao', label: 'Em Execução' },
                                    { value: 'concluido', label: 'Concluído' },
                                ]}
                                {...editForm.getInputProps('status')}
                            />
                        )}

                        <Group justify="flex-end" mt="md">
                            <Button variant="default" onClick={() => setEditando(null)}>
                                Cancelar
                            </Button>
                            <Button type="submit" loading={salvando}>
                                Salvar Alterações
                            </Button>
                        </Group>
                    </Stack>
                </form>
            </Modal>

            {servicos.length > 0 && (
                <Text size="sm" c="dimmed" mt="sm" ta="right">
                    {servicos.length} serviço{servicos.length !== 1 ? 's' : ''} encontrado
                    {servicos.length !== 1 ? 's' : ''}
                </Text>
            )}
        </>
    );
}

Historico.layout = (page) => <AppLayout>{page}</AppLayout>;
