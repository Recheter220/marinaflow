import { useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Title, Button, Table, TextInput, Modal, Group, Stack, Select, NumberInput,
    Badge, ActionIcon, Text, Paper, Center, Tooltip,
} from '@mantine/core';
import { useDebouncedValue } from '@mantine/hooks';
import { useForm } from '@mantine/form';
import { notifications } from '@mantine/notifications';
import { IconPlus, IconSearch, IconEdit, IconShip } from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';

const STATUS_LABELS = {
    ativa: 'Ativa',
    inativa: 'Inativa',
    em_manutencao: 'Em Manutenção',
};

const STATUS_OPTIONS = Object.entries(STATUS_LABELS).map(([value, label]) => ({ value, label }));

const TIPO_OPTIONS = [
    { value: 'lancha', label: 'Lancha' },
    { value: 'veleiro', label: 'Veleiro' },
    { value: 'iate', label: 'Iate' },
    { value: 'jet_ski', label: 'Jet Ski' },
    { value: 'barco_pesca', label: 'Barco de Pesca' },
    { value: 'catamarã', label: 'Catamarã' },
    { value: 'outro', label: 'Outro' },
];

export default function Embarcacoes({ embarcacoes, funcionarios, filtros }) {
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

    // A busca era um comando separado no app Tauri; aqui é uma revisita parcial
    // que só recarrega a lista.
    useEffect(() => {
        if ((filtros.busca ?? '') === buscaDebounced) {
            return;
        }

        router.get(
            route('embarcacoes.index'),
            { busca: buscaDebounced },
            { preserveState: true, preserveScroll: true, replace: true, only: ['embarcacoes', 'filtros'] },
        );
    }, [buscaDebounced]);

    const form = useForm({
        initialValues: {
            nome: '', identificacao: '', modelo: '', tipo: '', comprimento: '',
            ano_fabricacao: '', cliente_responsavel: '', status: 'ativa', funcionario_id: null,
        },
        validate: {
            nome: (v) => (v.trim().length === 0 ? 'Nome é obrigatório' : null),
            identificacao: (v) => (v.trim().length === 0 ? 'Identificação é obrigatória' : null),
        },
    });

    const abrirNovo = () => {
        setEditando(null);
        form.reset();
        setModalAberto(true);
    };

    const abrirEditar = (emb) => {
        setEditando(emb);
        form.setValues({
            nome: emb.nome,
            identificacao: emb.identificacao,
            modelo: emb.modelo || '',
            tipo: emb.tipo || '',
            comprimento: emb.comprimento ?? '',
            ano_fabricacao: emb.ano_fabricacao ?? '',
            cliente_responsavel: emb.cliente_responsavel || '',
            status: emb.status,
            funcionario_id: emb.funcionario_id ? String(emb.funcionario_id) : null,
        });
        setModalAberto(true);
    };

    const salvar = (values) => {
        const dados = {
            nome: values.nome,
            identificacao: values.identificacao,
            modelo: values.modelo || null,
            tipo: values.tipo || null,
            comprimento: values.comprimento === '' ? null : values.comprimento,
            ano_fabricacao: values.ano_fabricacao === '' ? null : values.ano_fabricacao,
            cliente_responsavel: values.cliente_responsavel || null,
            funcionario_id: values.funcionario_id ? Number(values.funcionario_id) : null,
        };

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
            router.put(route('embarcacoes.update', editando.id), { ...dados, status: values.status }, opcoes);
        } else {
            router.post(route('embarcacoes.store'), dados, opcoes);
        }
    };

    const funcionarioOptions = funcionarios.map((f) => ({ value: String(f.id), label: f.nome }));

    return (
        <>
            <Head title="Embarcações" />

            <Group justify="space-between" mb="lg">
                <Group gap="sm">
                    <IconShip size={28} stroke={1.5} color="var(--mantine-color-blue-6)" />
                    <Title order={2}>Embarcações</Title>
                </Group>
                {isAdmin && (
                    <Button leftSection={<IconPlus size={16} />} onClick={abrirNovo}>
                        Nova Embarcação
                    </Button>
                )}
            </Group>

            <Paper shadow="xs" p="md" radius="md" mb="md">
                <TextInput
                    placeholder="Buscar por nome, identificação ou cliente..."
                    leftSection={<IconSearch size={16} />}
                    value={busca}
                    onChange={(e) => setBusca(e.currentTarget.value)}
                />
            </Paper>

            {embarcacoes.length === 0 ? (
                <Paper shadow="xs" p="xl" radius="md">
                    <Center>
                        <Stack align="center" gap="xs">
                            <IconShip size={48} stroke={1} color="var(--mantine-color-gray-4)" />
                            <Text c="dimmed">
                                {isAdmin
                                    ? 'Nenhuma embarcação cadastrada'
                                    : 'Você não possui embarcações vinculadas'}
                            </Text>
                            {isAdmin && (
                                <Button variant="light" size="sm" onClick={abrirNovo}>
                                    Cadastrar primeira embarcação
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
                                <Table.Th>Identificação</Table.Th>
                                <Table.Th>Tipo/Modelo</Table.Th>
                                <Table.Th>Cliente</Table.Th>
                                <Table.Th>Responsável (Equipe)</Table.Th>
                                <Table.Th>Status</Table.Th>
                                {isAdmin && <Table.Th w={60}>Ações</Table.Th>}
                            </Table.Tr>
                        </Table.Thead>
                        <Table.Tbody>
                            {embarcacoes.map((emb) => (
                                <Table.Tr key={emb.id}>
                                    <Table.Td fw={500}>{emb.nome}</Table.Td>
                                    <Table.Td>
                                        <Text size="sm" ff="monospace">
                                            {emb.identificacao}
                                        </Text>
                                    </Table.Td>
                                    <Table.Td>
                                        <Text size="sm">
                                            {emb.tipo
                                                ? TIPO_OPTIONS.find((t) => t.value === emb.tipo)?.label || emb.tipo
                                                : '—'}
                                        </Text>
                                        <Text size="xs" c="dimmed">
                                            {emb.modelo || ''}
                                        </Text>
                                    </Table.Td>
                                    <Table.Td>{emb.cliente_responsavel || '—'}</Table.Td>
                                    <Table.Td>
                                        <Badge variant="outline" color={emb.funcionario_nome ? 'blue' : 'gray'}>
                                            {emb.funcionario_nome || 'Não atribuído'}
                                        </Badge>
                                    </Table.Td>
                                    <Table.Td>
                                        <Badge variant="light" className={`status-${emb.status}`} size="sm">
                                            {STATUS_LABELS[emb.status] || emb.status}
                                        </Badge>
                                    </Table.Td>
                                    {isAdmin && (
                                        <Table.Td>
                                            <Tooltip label="Editar">
                                                <ActionIcon
                                                    variant="subtle"
                                                    color="blue"
                                                    onClick={() => abrirEditar(emb)}
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
                title={editando ? 'Editar Embarcação' : 'Nova Embarcação'}
                size="lg"
            >
                <form onSubmit={form.onSubmit(salvar)}>
                    <Stack gap="sm">
                        <Group grow>
                            <TextInput label="Nome" placeholder="Nome da embarcação" required {...form.getInputProps('nome')} />
                            <TextInput label="Identificação" placeholder="Número de registro" required {...form.getInputProps('identificacao')} />
                        </Group>
                        <Group grow>
                            <Select label="Tipo" placeholder="Selecione o tipo" data={TIPO_OPTIONS} clearable {...form.getInputProps('tipo')} />
                            <TextInput label="Modelo" placeholder="Modelo da embarcação" {...form.getInputProps('modelo')} />
                        </Group>
                        <Group grow>
                            <NumberInput label="Comprimento (m)" placeholder="Em metros" decimalScale={2} min={0} {...form.getInputProps('comprimento')} />
                            <NumberInput label="Ano de Fabricação" placeholder="Ex: 2020" min={1900} max={2100} {...form.getInputProps('ano_fabricacao')} />
                        </Group>
                        <Group grow>
                            <TextInput label="Cliente Responsável (Dono)" placeholder="Nome do cliente" {...form.getInputProps('cliente_responsavel')} />
                            <Select
                                label="Funcionário Responsável (Equipe)"
                                placeholder="Atribuir a um funcionário"
                                data={funcionarioOptions}
                                clearable
                                searchable
                                {...form.getInputProps('funcionario_id')}
                            />
                        </Group>
                        {editando && (
                            <Select label="Status" data={STATUS_OPTIONS} {...form.getInputProps('status')} />
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

Embarcacoes.layout = (page) => <AppLayout>{page}</AppLayout>;
