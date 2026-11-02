import { Deferred, Head, router } from '@inertiajs/react';
import {
    Badge, Button, Center, Group, Image, Modal,
    Paper, SimpleGrid, Skeleton, Stack, Tabs, Text, Title,
    UnstyledButton,
} from '@mantine/core';
import { useState } from 'react';
import {
    IconAnchor, IconCalendar, IconChartBar, IconPhoto,
    IconRuler, IconTimeline, IconTool, IconUser,
} from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';

const STATUS_LABELS = {
    ativa: { label: 'Ativa', color: 'green' },
    inativa: { label: 'Inativa', color: 'gray' },
    em_manutencao: { label: 'Em Manutenção', color: 'yellow' },
};

const STATUS_SERVICO = {
    em_execucao: { label: 'Em Execução', color: 'blue' },
    aguardando_vistoria: { label: 'Aguardando Vistoria', color: 'yellow' },
    concluido: { label: 'Concluído', color: 'green' },
};

const formatarData = (iso) => {
    if (!iso) return '—';
    return new Date(iso + 'T00:00:00').toLocaleDateString('pt-BR');
};

function ItemDetalhe({ icon: Icon, label, valor }) {
    return (
        <Group gap="xs" wrap="nowrap">
            <Icon size={16} stroke={1.5} color="var(--mantine-color-blue-6)" style={{ flexShrink: 0 }} />
            <div>
                <Text size="xs" c="dimmed">{label}</Text>
                <Text size="sm" fw={500}>{valor || '—'}</Text>
            </div>
        </Group>
    );
}

function CabecalhoSkeleton() {
    return (
        <Paper shadow="xs" p="md" radius="md" mb="md">
            <Skeleton height={28} width={280} mb="sm" />
            <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                {Array.from({ length: 8 }).map((_, i) => <Skeleton key={i} height={44} radius="sm" />)}
            </SimpleGrid>
        </Paper>
    );
}

function ResumoSkeleton() {
    return (
        <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
            {Array.from({ length: 4 }).map((_, i) => <Skeleton key={i} height={80} radius="sm" />)}
        </SimpleGrid>
    );
}

function TimelineSkeleton() {
    return (
        <Stack gap="sm">
            {Array.from({ length: 3 }).map((_, i) => <Skeleton key={i} height={80} radius="sm" />)}
        </Stack>
    );
}

function GaleriaSkeleton() {
    return (
        <SimpleGrid cols={{ base: 3, sm: 5, md: 7 }} spacing="xs">
            {Array.from({ length: 14 }).map((_, i) => <Skeleton key={i} height={100} radius="sm" />)}
        </SimpleGrid>
    );
}

function CartaoMetrica({ label, valor, cor = 'blue' }) {
    return (
        <Paper withBorder p="md" radius="md">
            <Text size="xs" c="dimmed" tt="uppercase" fw={600}>{label}</Text>
            <Text size="xl" fw={700} c={cor} mt={4}>{valor ?? '—'}</Text>
        </Paper>
    );
}

function LinhaServico({ servico }) {
    const conf = STATUS_SERVICO[servico.status] ?? { label: servico.status, color: 'gray' };

    return (
        <Paper withBorder p="md" radius="md">
            <Group justify="space-between" mb="xs">
                <Group gap="xs">
                    <IconCalendar size={14} stroke={1.5} color="var(--mantine-color-dimmed)" />
                    <Text size="sm" c="dimmed">{formatarData(servico.data_execucao)}</Text>
                    {servico.funcionario && (
                        <>
                            <Text size="sm" c="dimmed">·</Text>
                            <Text size="sm" c="dimmed">{servico.funcionario.nome}</Text>
                        </>
                    )}
                </Group>
                <Badge variant="light" color={conf.color} size="sm">{conf.label}</Badge>
            </Group>

            {servico.tipos.length > 0 && (
                <Group gap="xs" mb={servico.observacao || servico.fotos_count > 0 ? 'xs' : 0}>
                    {servico.tipos.map((t) => (
                        <Badge key={t.id} variant="dot" color="blue" size="sm">{t.nome}</Badge>
                    ))}
                </Group>
            )}

            {servico.observacao && (
                <Text size="sm" c="dimmed" lineClamp={2}>{servico.observacao}</Text>
            )}

            {servico.fotos_count > 0 && (
                <Group gap="xs" mt="xs">
                    <IconPhoto size={14} stroke={1.5} color="var(--mantine-color-dimmed)" />
                    <Text size="xs" c="dimmed">{servico.fotos_count} foto{servico.fotos_count !== 1 ? 's' : ''}</Text>
                </Group>
            )}
        </Paper>
    );
}

function AbaTimeline({ servicos, embarcacaoId }) {
    const [carregando, setCarregando] = useState(false);

    if (!servicos) {
        return <TimelineSkeleton />;
    }

    const { data, meta } = servicos;
    const temMais = meta.current_page < meta.last_page;

    const carregarMais = () => {
        setCarregando(true);
        router.get(
            route('embarcacoes.show', embarcacaoId),
            { pagina: meta.current_page + 1 },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['servicos'],
                onFinish: () => setCarregando(false),
            },
        );
    };

    if (data.length === 0) {
        return (
            <Center py="xl">
                <Stack align="center" gap="xs">
                    <IconTool size={40} stroke={1} color="var(--mantine-color-gray-4)" />
                    <Text c="dimmed">Nenhum serviço registrado</Text>
                </Stack>
            </Center>
        );
    }

    return (
        <Stack gap="sm">
            {data.map((srv) => <LinhaServico key={srv.id} servico={srv} />)}
            {temMais && (
                <Button
                    variant="default"
                    fullWidth
                    loading={carregando}
                    onClick={carregarMais}
                >
                    Carregar mais ({meta.total - data.length} restantes)
                </Button>
            )}
            <Text size="xs" c="dimmed" ta="right">
                {data.length} de {meta.total} serviço{meta.total !== 1 ? 's' : ''}
            </Text>
        </Stack>
    );
}

function AbaGaleria({ fotos }) {
    const [ampliada, setAmpliada] = useState(null);

    if (!fotos) {
        return <GaleriaSkeleton />;
    }

    if (fotos.length === 0) {
        return (
            <Center py="xl">
                <Stack align="center" gap="xs">
                    <IconPhoto size={40} stroke={1} color="var(--mantine-color-gray-4)" />
                    <Text c="dimmed">Nenhuma foto registrada</Text>
                </Stack>
            </Center>
        );
    }

    return (
        <>
            <SimpleGrid cols={{ base: 3, sm: 5, md: 7 }} spacing="xs">
                {fotos.map((foto) => (
                    <UnstyledButton key={foto.id} onClick={() => setAmpliada(foto)} aria-label={foto.nome_original}>
                        <Image
                            src={foto.url}
                            alt={foto.nome_original}
                            height={100}
                            radius="sm"
                            fit="cover"
                        />
                        {foto.servico_data && (
                            <Text size="xs" c="dimmed" ta="center" mt={2}>
                                {formatarData(foto.servico_data)}
                            </Text>
                        )}
                    </UnstyledButton>
                ))}
            </SimpleGrid>

            <Modal
                opened={!!ampliada}
                onClose={() => setAmpliada(null)}
                title={ampliada?.nome_original}
                size="xl"
                centered
            >
                {ampliada && (
                    <>
                        <Image src={ampliada.url} alt={ampliada.nome_original} fit="contain" mah="75vh" />
                        <Text size="xs" c="dimmed" mt="xs" ta="right">
                            <a href={route('servicos.fotos.show', ampliada.id)} target="_blank" rel="noreferrer">
                                Abrir em nova aba
                            </a>
                        </Text>
                    </>
                )}
            </Modal>
        </>
    );
}

function AbaResumo({ resumo }) {
    if (!resumo) {
        return <ResumoSkeleton />;
    }

    return (
        <Stack gap="md">
            <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                <CartaoMetrica label="Total de serviços" valor={resumo.total_servicos} />
                <CartaoMetrica label="Em execução" valor={resumo.servicos_em_execucao} cor="blue" />
                <CartaoMetrica label="Último serviço" valor={formatarData(resumo.ultimo_servico)} cor="gray" />
            </SimpleGrid>

            {resumo.tipos_frequentes.length > 0 && (
                <Paper withBorder p="md" radius="md">
                    <Group gap="xs" mb="sm">
                        <IconChartBar size={16} stroke={1.5} />
                        <Text size="sm" fw={600}>Serviços mais frequentes</Text>
                    </Group>
                    <Stack gap="xs">
                        {resumo.tipos_frequentes.map((tipo) => (
                            <Group key={tipo.nome} justify="space-between">
                                <Text size="sm">{tipo.nome}</Text>
                                <Badge variant="light" color="blue">{tipo.total}</Badge>
                            </Group>
                        ))}
                    </Stack>
                </Paper>
            )}
        </Stack>
    );
}

export default function EmbarcacaoFicha({ embarcacao, resumo, servicos, fotos }) {
    const status = STATUS_LABELS[embarcacao.status] ?? { label: embarcacao.status, color: 'gray' };

    return (
        <>
            <Head title={`Ficha — ${embarcacao.nome}`} />

            <Group gap="sm" mb="lg" justify="space-between">
                <Group gap="sm">
                    <IconAnchor size={28} stroke={1.5} color="var(--mantine-color-blue-6)" />
                    <div>
                        <Title order={2}>{embarcacao.nome}</Title>
                        <Text size="xs" c="dimmed" ff="monospace">{embarcacao.identificacao}</Text>
                    </div>
                </Group>
                <Badge variant="light" color={status.color} size="lg">{status.label}</Badge>
            </Group>

            <Paper shadow="xs" p="md" radius="md" mb="lg">
                <SimpleGrid cols={{ base: 2, sm: 4 }} spacing="md">
                    <ItemDetalhe icon={IconAnchor} label="Tipo" valor={embarcacao.tipo} />
                    <ItemDetalhe icon={IconTool} label="Modelo" valor={embarcacao.modelo} />
                    <ItemDetalhe icon={IconRuler} label="Comprimento" valor={embarcacao.comprimento ? `${embarcacao.comprimento} m` : null} />
                    <ItemDetalhe icon={IconCalendar} label="Ano de fabricação" valor={embarcacao.ano_fabricacao} />
                    <ItemDetalhe icon={IconUser} label="Cliente responsável" valor={embarcacao.cliente_responsavel} />
                </SimpleGrid>
            </Paper>

            <Tabs defaultValue="timeline" keepMounted={false}>
                <Tabs.List mb="md">
                    <Tabs.Tab value="timeline" leftSection={<IconTimeline size={16} />}>Linha do Tempo</Tabs.Tab>
                    <Tabs.Tab value="galeria" leftSection={<IconPhoto size={16} />}>Galeria</Tabs.Tab>
                    <Tabs.Tab value="resumo" leftSection={<IconChartBar size={16} />}>Resumo</Tabs.Tab>
                </Tabs.List>

                <Tabs.Panel value="timeline">
                    <Deferred data="servicos" fallback={<TimelineSkeleton />}>
                        <AbaTimeline servicos={servicos} embarcacaoId={embarcacao.id} />
                    </Deferred>
                </Tabs.Panel>

                <Tabs.Panel value="galeria">
                    <Deferred data="fotos" fallback={<GaleriaSkeleton />}>
                        <AbaGaleria fotos={fotos} />
                    </Deferred>
                </Tabs.Panel>

                <Tabs.Panel value="resumo">
                    <Deferred data="resumo" fallback={<ResumoSkeleton />}>
                        <AbaResumo resumo={resumo} />
                    </Deferred>
                </Tabs.Panel>
            </Tabs>
        </>
    );
}

EmbarcacaoFicha.layout = (page) => <AppLayout>{page}</AppLayout>;
