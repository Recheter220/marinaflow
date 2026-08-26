import { Head, useForm } from '@inertiajs/react';
import {
    Paper, TextInput, PasswordInput, Button, Title, Text, Container, Stack, Center,
} from '@mantine/core';
import { IconLock, IconMail, IconShieldLock } from '@tabler/icons-react';
import GuestLayout from '@/layouts/GuestLayout';

/**
 * Tela de destino do link enviado por e-mail — o que substitui a "senha
 * temporária" que o admin repassava à mão no app Tauri.
 */
export default function DefinirSenha({ token, email }) {
    const { data, setData, post, processing, errors } = useForm({
        token,
        email: email ?? '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.store'), {
            onFinish: () => setData((d) => ({ ...d, password: '', password_confirmation: '' })),
        });
    };

    return (
        <GuestLayout>
            <Head title="Definir senha" />
            <Container size={420} my={40}>
                <Stack align="center" mb="xl">
                    <Center>
                        <IconShieldLock size={48} color="var(--mantine-color-blue-6)" />
                    </Center>
                    <Title ta="center" order={2}>
                        Definir senha
                    </Title>
                    <Text c="dimmed" size="sm" ta="center">
                        Escolha a senha que você usará para entrar no MarinaFlow.
                    </Text>
                </Stack>

                <Paper withBorder shadow="md" p={30} radius="md">
                    <form onSubmit={submit}>
                        <Stack>
                            <TextInput
                                label="E-mail"
                                type="email"
                                required
                                leftSection={<IconMail size={16} />}
                                value={data.email}
                                onChange={(e) => setData('email', e.currentTarget.value)}
                                error={errors.email}
                            />
                            <PasswordInput
                                label="Nova senha"
                                placeholder="Mínimo 8 caracteres"
                                required
                                autoComplete="new-password"
                                leftSection={<IconLock size={16} />}
                                value={data.password}
                                onChange={(e) => setData('password', e.currentTarget.value)}
                                error={errors.password}
                            />
                            <PasswordInput
                                label="Confirmar nova senha"
                                placeholder="Repita a nova senha"
                                required
                                autoComplete="new-password"
                                leftSection={<IconLock size={16} />}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.currentTarget.value)}
                                error={errors.password_confirmation}
                            />
                            <Button type="submit" fullWidth mt="xl" loading={processing} radius="md">
                                Definir senha
                            </Button>
                        </Stack>
                    </form>
                </Paper>
            </Container>
        </GuestLayout>
    );
}
