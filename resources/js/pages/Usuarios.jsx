import { useEffect, useState } from 'react';
import { Head, router, useForm as useInertiaForm, usePage } from '@inertiajs/react';
import {
    Title, Text, Paper, Table, Button, Group, ActionIcon, Badge, Modal,
    TextInput, Select, Stack, Menu,
} from '@mantine/core';
import { useForm } from '@mantine/form';
import { notifications } from '@mantine/notifications';
import {
    IconUserPlus, IconRefresh, IconShield, IconPower,
    IconDotsVertical, IconEdit, IconTrash,
} from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';

const ROLE_OPTIONS = [
    { value: 'admin', label: 'Administrador (Acesso Total)' },
    { value: 'funcionario', label: 'Funcionário (Acesso Restrito)' },
];

const emailValido = (value) => (/^\S+@\S+\.\S+$/.test(value ?? '') ? null : 'Informe um e-mail válido');

export default function Usuarios({ usuarios, funcionarios }) {
    const { auth, flash } = usePage().props;
    const [createOpened, setCreateOpened] = useState(false);
    const [editOpened, setEditOpened] = useState(false);
    const [userEditando, setUserEditando] = useState(null);
    const [confirmAction, setConfirmAction] = useState(null);
    const [submitting, setSubmitting] = useState(false);

    const funcionarioOptions = funcionarios.map((f) => ({ value: String(f.id), label: f.nome }));

    // Mensagens do servidor (flash) viram notificações — o servidor é a fonte da
    // verdade do resultado, não o retorno de um invoke().
    useEffect(() => {
        if (flash?.success) {
            notifications.show({ title: 'Sucesso', message: flash.success, color: 'green' });
        }
        if (flash?.error) {
            notifications.show({ title: 'Erro', message: flash.error, color: 'red' });
        }
    }, [flash]);

    const createForm = useForm({
        initialValues: { name: '', email: '', role: 'funcionario', funcionario_id: null },
        validate: { email: emailValido, role: (v) => (v ? null : 'Selecione uma role') },
    });

    const editForm = useForm({
        initialValues: { name: '', email: '', role: '', funcionario_id: null },
        validate: { email: emailValido, role: (v) => (v ? null : 'Selecione uma role') },
    });

    const payload = (values) => ({
        ...values,
        funcionario_id: values.funcionario_id ? parseInt(values.funcionario_id, 10) : null,
    });

    const onError = (errors) =>
        notifications.show({
            title: 'Não foi possível concluir',
            message: Object.values(errors).join(' '),
            color: 'red',
        });

    const handleCreate = (values) => {
        setSubmitting(true);
        router.post(route('usuarios.store'), payload(values), {
            preserveScroll: true,
            onSuccess: () => {
                setCreateOpened(false);
                createForm.reset();
            },
            onError,
            onFinish: () => setSubmitting(false),
        });
    };

    const openEdit = (user) => {
        setUserEditando(user);
        editForm.setValues({
            name: user.name ?? '',
            email: user.email,
            role: user.role,
            funcionario_id: user.funcionario_id ? String(user.funcionario_id) : null,
        });
        setEditOpened(true);
    };

    const handleEdit = (values) => {
        setSubmitting(true);
        router.put(route('usuarios.update', userEditando.id), payload(values), {
            preserveScroll: true,
            onSuccess: () => setEditOpened(false),
            onError,
            onFinish: () => setSubmitting(false),
        });
    };

    const handleToggleAtivo = (user) => {
        setConfirmAction({
            title: `${user.ativo ? 'Desativar' : 'Ativar'} Usuário`,
            message: `Deseja realmente ${user.ativo ? 'desativar' : 'ativar'} o usuário ${user.email}?`,
            color: user.ativo ? 'red' : 'green',
            confirmLabel: user.ativo ? 'Desativar' : 'Ativar',
            onConfirm: () =>
                router.patch(
                    route('usuarios.ativo', user.id),
                    { ativo: !user.ativo },
                    { preserveScroll: true, onError },
                ),
        });
    };

    // Substitui a antiga "Resetar Senha", que devolvia uma senha em texto claro
    // para o admin repassar. Agora apenas reenvia o link assinado por e-mail.
    const handleReenviarConvite = (user) => {
        setConfirmAction({
            title: 'Reenviar link de acesso',
            message: `Enviar um novo link de definição de senha para ${user.email}?`,
            color: 'orange',
            confirmLabel: 'Enviar link',
            onConfirm: () =>
                router.post(route('usuarios.convite', user.id), {}, { preserveScroll: true, onError }),
        });
    };

    const handleDelete = (user) => {
        setConfirmAction({
            title: 'Excluir Permanentemente',
            message: `Deseja realmente EXCLUIR o usuário ${user.email}? Esta ação não pode ser desfeita.`,
            color: 'red',
            confirmLabel: 'Excluir',
            onConfirm: () =>
                router.delete(route('usuarios.destroy', user.id), { preserveScroll: true, onError }),
        });
    };

    return (
        <Stack>
            <Head title="Usuários" />

            <Group justify="space-between" align="flex-end">
                <div>
                    <Title order={2}>Gestão de Usuários</Title>
                    <Text c="dimmed" size="sm">
                        Controle de acesso ao sistema
                    </Text>
                </div>
                <Button radius="md" leftSection={<IconUserPlus size={16} />} onClick={() => setCreateOpened(true)}>
                    Novo Usuário
                </Button>
            </Group>

            <Paper withBorder p="md" radius="md" mt="md">
                <Table verticalSpacing="sm" highlightOnHover>
                    <Table.Thead>
                        <Table.Tr>
                            <Table.Th>E-mail</Table.Th>
                            <Table.Th>Role</Table.Th>
                            <Table.Th>Funcionário Vinculado</Table.Th>
                            <Table.Th>Situação</Table.Th>
                            <Table.Th>Ações</Table.Th>
                        </Table.Tr>
                    </Table.Thead>
                    <Table.Tbody>
                        {usuarios.map((u) => {
                            const ehVoce = u.id === auth.user.id;

                            return (
                                <Table.Tr key={u.id} style={{ opacity: u.ativo ? 1 : 0.6 }}>
                                    <Table.Td>
                                        <Group gap="sm">
                                            <IconShield
                                                size={16}
                                                color={u.role === 'admin' ? 'var(--mantine-color-blue-6)' : 'gray'}
                                            />
                                            <div>
                                                <Text fw={500}>{u.email}</Text>
                                                {u.name && (
                                                    <Text size="xs" c="dimmed">
                                                        {u.name}
                                                    </Text>
                                                )}
                                            </div>
                                        </Group>
                                    </Table.Td>
                                    <Table.Td>
                                        <Badge color={u.role === 'admin' ? 'blue' : 'gray'} variant="light" radius="sm">
                                            {u.role === 'admin' ? 'Admin' : 'Funcionário'}
                                        </Badge>
                                    </Table.Td>
                                    <Table.Td>{u.funcionario_nome || '-'}</Table.Td>
                                    <Table.Td>
                                        <Group gap={4}>
                                            {u.ativo ? (
                                                <Badge color="green" variant="dot">
                                                    Ativo
                                                </Badge>
                                            ) : (
                                                <Badge color="red" variant="dot">
                                                    Inativo
                                                </Badge>
                                            )}
                                            {u.convite_pendente && (
                                                <Badge color="orange" size="xs">
                                                    Convite pendente
                                                </Badge>
                                            )}
                                        </Group>
                                    </Table.Td>
                                    <Table.Td>
                                        <Menu shadow="md" width={220} position="bottom-end">
                                            <Menu.Target>
                                                <ActionIcon variant="subtle" color="gray">
                                                    <IconDotsVertical size={18} />
                                                </ActionIcon>
                                            </Menu.Target>

                                            <Menu.Dropdown>
                                                <Menu.Label>Conta</Menu.Label>
                                                <Menu.Item
                                                    leftSection={<IconEdit size={14} />}
                                                    onClick={() => openEdit(u)}
                                                >
                                                    Editar Usuário
                                                </Menu.Item>

                                                <Menu.Item
                                                    leftSection={<IconRefresh size={14} />}
                                                    onClick={() => handleReenviarConvite(u)}
                                                    disabled={ehVoce || !u.ativo}
                                                    color="orange"
                                                >
                                                    Reenviar link de acesso
                                                </Menu.Item>

                                                <Menu.Divider />

                                                <Menu.Label>Status</Menu.Label>
                                                <Menu.Item
                                                    leftSection={<IconPower size={14} />}
                                                    color={u.ativo ? 'red' : 'green'}
                                                    onClick={() => handleToggleAtivo(u)}
                                                    disabled={ehVoce}
                                                >
                                                    {u.ativo ? 'Desativar' : 'Ativar'}
                                                </Menu.Item>

                                                <Menu.Item
                                                    leftSection={<IconTrash size={14} />}
                                                    color="red"
                                                    onClick={() => handleDelete(u)}
                                                    disabled={ehVoce}
                                                >
                                                    Excluir Permanentemente
                                                </Menu.Item>
                                            </Menu.Dropdown>
                                        </Menu>
                                    </Table.Td>
                                </Table.Tr>
                            );
                        })}
                    </Table.Tbody>
                </Table>
            </Paper>

            {/* Criar — nenhum campo de senha: o convite vai por e-mail. */}
            <Modal opened={createOpened} onClose={() => setCreateOpened(false)} title="Novo Usuário" centered radius="md">
                <form onSubmit={createForm.onSubmit(handleCreate)}>
                    <Stack>
                        <TextInput
                            label="E-mail"
                            type="email"
                            placeholder="joao.silva@exemplo.com"
                            required
                            {...createForm.getInputProps('email')}
                        />
                        <TextInput label="Nome" placeholder="Opcional" {...createForm.getInputProps('name')} />
                        <Select
                            label="Role"
                            placeholder="Selecione..."
                            data={ROLE_OPTIONS}
                            required
                            {...createForm.getInputProps('role')}
                        />
                        <Select
                            label="Funcionário Vinculado"
                            placeholder="Selecione (opcional)..."
                            data={funcionarioOptions}
                            clearable
                            {...createForm.getInputProps('funcionario_id')}
                        />
                        <Text size="xs" c="dimmed">
                            Um convite será enviado por e-mail para que a pessoa defina a própria senha.
                        </Text>
                        <Button type="submit" fullWidth mt="md" radius="md" loading={submitting}>
                            Criar e enviar convite
                        </Button>
                    </Stack>
                </form>
            </Modal>

            <Modal opened={editOpened} onClose={() => setEditOpened(false)} title="Editar Usuário" centered radius="md">
                <form onSubmit={editForm.onSubmit(handleEdit)}>
                    <Stack>
                        <TextInput label="E-mail" type="email" required {...editForm.getInputProps('email')} />
                        <TextInput label="Nome" placeholder="Opcional" {...editForm.getInputProps('name')} />
                        <Select
                            label="Role"
                            placeholder="Selecione..."
                            data={ROLE_OPTIONS}
                            required
                            {...editForm.getInputProps('role')}
                        />
                        <Select
                            label="Funcionário Vinculado"
                            placeholder="Selecione (opcional)..."
                            data={funcionarioOptions}
                            clearable
                            {...editForm.getInputProps('funcionario_id')}
                        />
                        <Button type="submit" fullWidth mt="md" radius="md" loading={submitting}>
                            Salvar Alterações
                        </Button>
                    </Stack>
                </form>
            </Modal>

            <Modal
                opened={!!confirmAction}
                onClose={() => setConfirmAction(null)}
                title={confirmAction?.title}
                centered
                radius="md"
                size="sm"
            >
                <Stack>
                    <Text size="sm">{confirmAction?.message}</Text>
                    <Group justify="flex-end" mt="md">
                        <Button variant="default" onClick={() => setConfirmAction(null)} radius="md">
                            Cancelar
                        </Button>
                        <Button
                            color={confirmAction?.color || 'red'}
                            radius="md"
                            onClick={() => {
                                const action = confirmAction;
                                setConfirmAction(null);
                                action?.onConfirm();
                            }}
                        >
                            {confirmAction?.confirmLabel || 'Confirmar'}
                        </Button>
                    </Group>
                </Stack>
            </Modal>
        </Stack>
    );
}

Usuarios.layout = (page) => <AppLayout>{page}</AppLayout>;
