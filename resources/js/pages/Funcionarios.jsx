import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Title, Button, Table, TextInput, Modal, Group, Stack, Switch, Badge,
    ActionIcon, Text, Paper, Center, Tooltip,
} from '@mantine/core';
import { useDebouncedValue } from '@mantine/hooks';
import { useForm } from '@mantine/form';
import { notifications } from '@mantine/notifications';
import { IconPlus, IconSearch, IconEdit, IconUsers } from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';
import { formatPhoneNumber, normalizePhoneNumber } from '@/utils/phone';

export default function Funcionarios({ funcionarios, filtros }) {
    const { auth, flash } = usePage().props;
    const isAdmin = auth.user.is_admin;

    const [modalAberto, setModalAberto] = useState(false);
    const [editando, setEditando] = useState(null);
    const [busca, setBusca] = useState(filtros.busca ?? '');
    const [buscaDebounced] = useDebouncedValue(busca, 300);
    const [salvando, setSalvando] = useState(false);

    useEffect(() => {
        if (flash?.success) {
            notifications.show({ title: 'Sucesso', message: flash.success, color: 'green' });
        }
    }, [flash]);

    useEffect(() => {
        if ((filtros.busca ?? '') === buscaDebounced) {
            return;
        }

        router.get(
            route('funcionarios.index'),
            { busca: buscaDebounced },
            { preserveState: true, preserveScroll: true, replace: true, only: ['funcionarios', 'filtros'] },
        );
    }, [buscaDebounced]);

    const form = useForm({
        initialValues: { nome: '', cargo: '', telefone: '', email: '', ativo: true },
        validate: {
            nome: (v) => (v.trim().length === 0 ? 'Nome é obrigatório' : null),
            email: (v) => (!v || /^\S+@\S+\.\S+$/.test(v) ? null : 'Informe um e-mail válido'),
        },
    });

    const abrirNovo = () => {
        setEditando(null);
        form.reset();
        setModalAberto(true);
    };

    const abrirEditar = (func) => {
        setEditando(func);
        form.setValues({
            nome: func.nome,
            cargo: func.cargo || '',
            telefone: formatPhoneNumber(func.telefone),
            email: '',
            ativo: func.ativo,
        });
        setModalAberto(true);
    };

    const salvar = (values) => {
        const opcoes = {
            preserveScroll: true,
            onSuccess: () => {
                setModalAberto(false);
                form.reset();
            },
            onError: (errors) => form.setErrors(errors),
            onFinish: () => setSalvando(false),
        };

        setSalvando(true);

        if (editando) {
            router.put(
                route('funcionarios.update', editando.id),
                {
                    nome: values.nome,
                    cargo: values.cargo || null,
                    telefone: normalizePhoneNumber(values.telefone) || null,
                    ativo: values.ativo,
                },
                opcoes,
            );
        } else {
            router.post(
                route('funcionarios.store'),
                {
                    nome: values.nome,
                    cargo: values.cargo || null,
                    telefone: normalizePhoneNumber(values.telefone) || null,
                    email: values.email || null,
                },
                opcoes,
            );
        }
    };

    return (
        <>
            <Head title="Funcionários" />

            <Group justify="space-between" mb="lg">
                <Group gap="sm">
                    <IconUsers size={28} stroke={1.5} color="var(--mantine-color-blue-6)" />
                    <Title order={2}>Funcionários</Title>
                </Group>
                {isAdmin && (
                    <Button leftSection={<IconPlus size={16} />} onClick={abrirNovo}>
                        Novo Funcionário
                    </Button>
                )}
            </Group>

            <Paper shadow="xs" p="md" radius="md" mb="md">
                <TextInput
                    placeholder="Buscar por nome ou cargo..."
                    leftSection={<IconSearch size={16} />}
                    value={busca}
                    onChange={(e) => setBusca(e.currentTarget.value)}
                />
            </Paper>

            {funcionarios.length === 0 ? (
                <Paper shadow="xs" p="xl" radius="md">
                    <Center>
                        <Stack align="center" gap="xs">
                            <IconUsers size={48} stroke={1} color="var(--mantine-color-gray-4)" />
                            <Text c="dimmed">Nenhum funcionário cadastrado</Text>
                            {isAdmin && (
                                <Button variant="light" size="sm" onClick={abrirNovo}>
                                    Cadastrar primeiro funcionário
                                </Button>
                            )}
                        </Stack>
                    </Center>
                </Paper>
            ) : (
                <Paper shadow="xs" radius="md" style={{ overflow: 'hidden' }}>
                    <Table striped highlightOnHover>
                        <Table.Thead>
                            <Table.Tr>
                                <Table.Th>Nome</Table.Th>
                                <Table.Th>Cargo</Table.Th>
                                <Table.Th>Telefone</Table.Th>
                                <Table.Th>Acesso</Table.Th>
                                <Table.Th>Situação</Table.Th>
                                {isAdmin && <Table.Th w={60}>Ações</Table.Th>}
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {funcionarios.map((func) => (
                                <Table.Tr key={func.id} style={{ opacity: func.ativo ? 1 : 0.6 }}>
                                    <Table.Td fw={500}>{func.nome}</Table.Td>
                                    <Table.Td>{func.cargo || '—'}</Table.Td>
                                    <Table.Td>{formatPhoneNumber(func.telefone) || '—'}</Table.Td>
                                    <Table.Td>
                                        {func.email ? (
                                            <Text size="sm">{func.email}</Text>
                                        ) : (
                                            <Text size="sm" c="dimmed">
                                                Sem acesso
                                            </Text>
                                        )}
                                    </Table.Td>
                                    <Table.Td>
                                        <Badge variant="light" color={func.ativo ? 'green' : 'red'} size="sm">
                                            {func.ativo ? 'Ativo' : 'Inativo'}
                                        </Badge>
                                    </Table.Td>
                                    {isAdmin && (
                                        <Table.Td>
                                            <Tooltip label="Editar">
                                                <ActionIcon
                                                    variant="subtle"
                                                    color="blue"
                                                    onClick={() => abrirEditar(func)}
                                                >
                                                    <IconEdit size={16} />
                                                </ActionIcon>
                                            </Tooltip>
                                        </Table.Td>
                                    )}
                                </Table.Tr>
                            ))}
                        </Table.Tbody>
                    </Table>
                </Paper>
            )}

            <Modal
                opened={modalAberto}
                onClose={() => setModalAberto(false)}
                title={editando ? 'Editar Funcionário' : 'Novo Funcionário'}
                size="md"
            >
                <form onSubmit={form.onSubmit(salvar)}>
                    <Stack gap="sm">
                        <TextInput label="Nome" placeholder="Nome completo" required {...form.getInputProps('nome')} />
                        <TextInput label="Cargo" placeholder="Ex: Mecânico, Eletricista, Pintor" {...form.getInputProps('cargo')} />
                        <TextInput
                            label="Telefone"
                            placeholder="(11) 99999-9999"
                            value={form.values.telefone}
                            onChange={(event) =>
                                form.setFieldValue('telefone', formatPhoneNumber(event.currentTarget.value))
                            }
                        />
                        {/* Substitui o usuário auto-criado com senha temporária do app Tauri. */}
                        {!editando && (
                            <TextInput
                                label="E-mail de acesso"
                                type="email"
                                placeholder="Opcional — envia convite para o sistema"
                                description="Se preenchido, cria um usuário vinculado e envia um convite por e-mail."
                                {...form.getInputProps('email')}
                            />
                        )}
                        {editando && (
                            <Switch
                                label="Funcionário ativo"
                                description="Funcionários inativos não podem ser atribuídos a novos serviços"
                                {...form.getInputProps('ativo', { type: 'checkbox' })}
                            />
                        )}
                        <Group justify="flex-end" mt="md">
                            <Button variant="default" onClick={() => setModalAberto(false)}>
                                Cancelar
                            </Button>
                            <Button type="submit" loading={salvando}>
                                {editando ? 'Salvar Alterações' : 'Cadastrar'}
                            </Button>
                        </Group>
                    </Stack>
                </form>
            </Modal>
        </>
    );
}

Funcionarios.layout = (page) => <AppLayout>{page}</AppLayout>;
