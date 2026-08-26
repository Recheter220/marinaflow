import { Head, Link, useForm } from '@inertiajs/react';
import {
    Paper,
    TextInput,
    PasswordInput,
    Button,
    Title,
    Text,
    Container,
    Anchor,
    ThemeIcon,
    Stack,
    Alert,
} from '@mantine/core';
import { IconAnchor, IconLock, IconMail } from '@tabler/icons-react';
import GuestLayout from '@/layouts/GuestLayout';

export default function Login({ status }) {
    // O `login` do app Tauri virou `email`: identificador de autenticação padrão
    // na web, validado por formato e unicidade.
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('login'), { onFinish: () => setData('password', '') });
    };

    return (
        <GuestLayout>
            <Head title="Entrar" />
            <Container size={420} my={40}>
                <Stack align="center" mb="xl">
                    <ThemeIcon size={64} radius={100} variant="gradient" gradient={{ from: 'blue.6', to: 'cyan.4' }}>
                        <IconAnchor size={36} />
                    </ThemeIcon>
                    <Title ta="center" order={2} style={{ fontWeight: 900 }}>
                        MarinaFlow
                    </Title>
                    <Text c="dimmed" size="sm" ta="center">
                        Sistema de Gerenciamento de Serviços
                    </Text>
                </Stack>

                {status && (
                    <Alert color="green" radius="md" mb="md">
                        {status}
                    </Alert>
                )}

                <Paper withBorder shadow="md" p={30} radius="md">
                    <form onSubmit={submit}>
                        <Stack>
                            <TextInput
                                label="E-mail"
                                type="email"
                                placeholder="voce@exemplo.com"
                                required
                                autoComplete="username"
                                leftSection={<IconMail size={16} />}
                                value={data.email}
                                onChange={(e) => setData('email', e.currentTarget.value)}
                                error={errors.email}
                            />
                            <PasswordInput
                                label="Senha"
                                placeholder="Sua senha"
                                required
                                autoComplete="current-password"
                                leftSection={<IconLock size={16} />}
                                value={data.password}
                                onChange={(e) => setData('password', e.currentTarget.value)}
                                error={errors.password}
                            />

                            <Button type="submit" fullWidth mt="xl" loading={processing} radius="md">
                                Entrar no Sistema
                            </Button>

                            <Anchor component={Link} href={route('password.request')} size="sm" ta="center">
                                Esqueci minha senha
                            </Anchor>
                        </Stack>
                    </form>
                </Paper>

                <Text c="dimmed" size="xs" ta="center" mt="xl">
                    Projeto Integrador — UNIVESP
                </Text>
            </Container>
        </GuestLayout>
    );
}
