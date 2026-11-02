import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Title, Select, Table, Badge, Group, Stack, Paper, Text, ActionIcon,
    Tooltip, Menu, Modal, Textarea, Button, Center,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { notifications } from '@mantine/notifications';
import { IconHistory, IconDotsVertical, IconCheck, IconShip, IconEdit, IconFileSearch } from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';
import CampoFotos from '@/components/CampoFotos';
import GaleriaFotos from '@/components/GaleriaFotos';
import { LIMITE_FOTOS_POR_SERVICO, useEnvioFotos } from '@/utils/fotos';
import CampoTiposServico from '@/components/CampoTiposServico';

const STATUS_CONFIG = {
    em_execucao: { label: 'Em Execução', color: 'blue' },
    aguardando_vistoria: { label: 'Aguardando Vistoria', color: 'yellow' },
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

export default function Historico({ servicos, embarcacoes, tipos_servico, filtros }) {
    const { auth, flash } = usePage().props;
    const isAdmin = auth.user.is_admin;
    const funcionarioId = auth.user.funcionario_id;

    const [editando, setEditando] = useState(null);
    const [salvando, setSalvando] = useState(false);
    const [fotosNovas, setFotosNovas] = useState([]);
    const envioFotos = useEnvioFotos();
    // As fotos mudam a cada envio/remoção; a cópia em `editando` ficaria velha.
    const fotosEditando = servicos.find((s) => s.id === editando?.id)?.fotos ?? [];
    const fotosNovasExcedem = fotosNovas.length > Math.min(10, LIMITE_FOTOS_POR_SERVICO - fotosEditando.length);
    const embarcacaoSelecionada = filtros.embarcacao_id ? String(filtros.embarcacao_id) : null;

    useEffect(() => {
        if (flash?.success) {
            notifications.show({ title: 'Sucesso', message: flash.success, color: 'green' });
        }
    }, [flash]);

    const editForm = useForm({
        initialValues: { tipos: [], observacao: '', status: '' },
        validate: {
            tipos: (v) => (v.length === 0 ? 'Selecione ao menos um serviço' : null),
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
        setFotosNovas([]);
        envioFotos.limparRecusadas();
        editForm.setValues({
            tipos: srv.tipos.map((t) => String(t.id)),
            observacao: srv.observacao || '',
            status: srv.status,
        });
    };

    const salvarEdicao = (values) => {
        setSalvando(true);
        router.put(
            route('servicos.update', editando.id),
            {
                tipos: values.tipos.map(Number),
                observacao: values.observacao || null,
                ...(values.status !== editando.status ? { status: values.status } : {}),
            },
            {
                preserveScroll: true,
                // Sem `return`: o Inertia aguarda a promise do onSuccess antes de
                // processar a próxima resposta, e o envio das fotos travaria.
                onSuccess: () => {
                    enviarFotosEFechar();
                },
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

    /** As fotos vão junto com o salvar; o modal só fecha se todas forem aceitas. */
    const enviarFotosEFechar = async () => {
        if (fotosNovas.length > 0) {
            const naoEnviadas = await envioFotos.enviar(editando.id, fotosNovas);
            setFotosNovas(naoEnviadas);

            if (naoEnviadas.length > 0) {
                return;
            }
        }

        setEditando(null);
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

    const podeConcluir = (srv) =>
        isAdmin && ['em_execucao', 'aguardando_vistoria'].includes(srv.status);

    const statusOptions = [
        ...(editando?.status === 'em_execucao'
            ? [{ value: 'em_execucao', label: STATUS_CONFIG.em_execucao.label }]
            : []),
        { value: 'aguardando_vistoria', label: STATUS_CONFIG.aguardando_vistoria.label },
        ...(isAdmin ? [{ value: 'concluido', label: STATUS_CONFIG.concluido.label }] : []),
    ];

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
                                        <Table.Td>
                                            <Text
                                                size="sm"
                                                component={Link}
                                                href={route('embarcacoes.show', srv.embarcacao_id)}
                                                c="blue"
                                                style={{ textDecoration: 'none' }}
                                            >
                                                {srv.embarcacao_nome || '—'}
                                            </Text>
                                        </Table.Td>
                                        <Table.Td>{srv.funcionario_nome || '—'}</Table.Td>
                                        <Table.Td>
                                            <Text size="sm" lineClamp={2}>
                                                {srv.tipos.map((t) => t.nome).join(', ')}
                                            </Text>
                                            {srv.observacao && (
                                                <Text size="xs" c="dimmed" lineClamp={1}>
                                                    Obs: {srv.observacao}
                                                </Text>
                                            )}
                                            {srv.fotos.length > 0 && (
                                                <div style={{ marginTop: 6 }}>
                                                    <GaleriaFotos fotos={srv.fotos} tamanho={40} />
                                                </div>
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
                                                        <Menu.Item
                                                            component={Link}
                                                            href={route('embarcacoes.show', srv.embarcacao_id)}
                                                            leftSection={<IconFileSearch size={14} />}
                                                        >
                                                            Ver Ficha
                                                        </Menu.Item>
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
                onClose={() => !envioFotos.enviando && setEditando(null)}
                title="Editar Registro de Serviço"
                centered
            >
                <form onSubmit={editForm.onSubmit(salvarEdicao)}>
                    <Stack gap="sm">
                        <CampoTiposServico
                            tipos={tipos_servico}
                            extras={editando?.tipos}
                            {...editForm.getInputProps('tipos')}
                        />

                        <Textarea label="Observações" minRows={3} {...editForm.getInputProps('observacao')} />

                        <Stack gap="xs">
                            <Text size="sm" fw={500}>
                                Fotos ({fotosEditando.length}/{LIMITE_FOTOS_POR_SERVICO})
                            </Text>
                            <GaleriaFotos fotos={fotosEditando} removivel />
                            <CampoFotos
                                value={fotosNovas}
                                onChange={setFotosNovas}
                                restantes={LIMITE_FOTOS_POR_SERVICO - fotosEditando.length}
                                progresso={envioFotos.progresso}
                                recusadas={envioFotos.recusadas}
                                onFecharRecusadas={envioFotos.limparRecusadas}
                            />
                        </Stack>

                        {isAdmin && editando && editando.status !== 'concluido' && (
                            <Select
                                label="Alterar Status"
                                data={statusOptions}
                                allowDeselect={false}
                                {...editForm.getInputProps('status')}
                            />
                        )}

                        <Group justify="flex-end" mt="md">
                            <Button variant="default" onClick={() => setEditando(null)} disabled={envioFotos.enviando}>
                                Cancelar
                            </Button>
                            <Button
                                type="submit"
                                loading={salvando || envioFotos.enviando}
                                disabled={fotosNovasExcedem}
                            >
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
