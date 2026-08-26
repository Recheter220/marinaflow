import { Link, router, usePage } from '@inertiajs/react';
import {
    AppShell,
    NavLink,
    Group,
    Title,
    Text,
    ThemeIcon,
    Box,
    Divider,
    ActionIcon,
    Tooltip,
} from '@mantine/core';
import {
    IconShip,
    IconUsers,
    IconTool,
    IconHistory,
    IconAnchor,
    IconLogout,
    IconShieldLock,
} from '@tabler/icons-react';

const navItems = [
    { label: 'Embarcações', icon: IconShip, path: '/embarcacoes', description: 'Cadastro e gestão' },
    { label: 'Funcionários', icon: IconUsers, path: '/funcionarios', description: 'Equipe e cargos' },
    { label: 'Registrar Serviço', icon: IconTool, path: '/servicos/novo', description: 'Novo registro' },
    { label: 'Histórico', icon: IconHistory, path: '/historico', description: 'Consulta de serviços' },
];

/**
 * Recria o AppShell de `src/App.jsx`. O destaque do item ativo vem de
 * `usePage().url` em vez de `useLocation()`, e a navegação usa `<Link>` do
 * Inertia no lugar de `useNavigate()`.
 */
export default function AppLayout({ children }) {
    const { props, url: currentUrl } = usePage();
    const user = props.auth?.user;

    return (
        <AppShell header={{ height: 60 }} navbar={{ width: 260, breakpoint: 'sm' }} padding="lg">
            <AppShell.Header
                style={{
                    borderBottom: '1px solid var(--header-border)',
                    backgroundColor: 'var(--header-bg)',
                }}
            >
                <Group h="100%" px="lg" justify="space-between">
                    <Group gap="sm">
                        <ThemeIcon size="lg" radius="md" variant="gradient" gradient={{ from: 'blue.6', to: 'cyan.4' }}>
                            <IconAnchor size={22} />
                        </ThemeIcon>
                        <Box>
                            <Title order={4} style={{ lineHeight: 1.2 }}>
                                MarinaFlow
                            </Title>
                            <Text size="xs" c="dimmed">
                                Sistema de Gerenciamento de Serviços
                            </Text>
                        </Box>
                    </Group>

                    {user && (
                        <Group gap="md">
                            <Box ta="right" visibleFrom="xs">
                                <Text size="sm" fw={600}>
                                    {user.name || user.email}
                                </Text>
                                <Text size="xs" c="dimmed">
                                    {user.is_admin ? 'Administrador' : 'Funcionário'}
                                </Text>
                            </Box>
                            <Tooltip label="Sair do sistema">
                                <ActionIcon
                                    variant="light"
                                    color="red"
                                    size="lg"
                                    radius="md"
                                    onClick={() => router.post(route('logout'))}
                                >
                                    <IconLogout size={20} />
                                </ActionIcon>
                            </Tooltip>
                        </Group>
                    )}
                </Group>
            </AppShell.Header>

            <AppShell.Navbar
                p="md"
                style={{ borderRight: '1px solid var(--sidebar-border)', backgroundColor: 'var(--sidebar-bg)' }}
            >
                <Text size="xs" fw={600} c="dimmed" tt="uppercase" mb="sm" px="sm">
                    Menu Principal
                </Text>

                {navItems.map((item) => (
                    <NavLink
                        key={item.path}
                        component={Link}
                        href={item.path}
                        label={item.label}
                        description={item.description}
                        leftSection={<item.icon size={20} stroke={1.5} />}
                        active={currentUrl === item.path}
                        variant="light"
                        style={{ borderRadius: 'var(--mantine-radius-md)', marginBottom: 4 }}
                    />
                ))}

                {user?.is_admin && (
                    <>
                        <Divider my="sm" label="Administração" labelPosition="center" />
                        <NavLink
                            component={Link}
                            href={route('usuarios.index')}
                            label="Usuários"
                            description="Gestão de acessos"
                            leftSection={<IconShieldLock size={20} stroke={1.5} />}
                            active={currentUrl.startsWith('/usuarios')}
                            variant="light"
                            style={{ borderRadius: 'var(--mantine-radius-md)', marginBottom: 4 }}
                        />
                    </>
                )}

                <Box style={{ marginTop: 'auto' }}>
                    <Divider my="sm" />
                    <Text size="xs" c="dimmed" px="sm" ta="center">
                        Projeto Integrador — UNIVESP
                    </Text>
                </Box>
            </AppShell.Navbar>

            <AppShell.Main style={{ backgroundColor: 'var(--app-bg)' }}>
                <div className="page-container">{children}</div>
            </AppShell.Main>
        </AppShell>
    );
}
