import { Head, Link, useForm } from '@inertiajs/react';
import {
    Paper, TextInput, Button, Title, Text, Container, Stack, Alert, Anchor, Center,
} from '@mantine/core';
import { IconMail, IconShieldLock } from '@tabler/icons-react';
import GuestLayout from '@/layouts/GuestLayout';

export default function EsqueciSenha({ status }) {
    const { data, setData, post, processing, errors } = useForm({ email: '' });

    const submit = (e) => {
        e.preventDefault();
        post(route('password.email'));
    };

    return (
        <GuestLayout>
            <Head title="Esqueci minha senha" />
            <Container size={420} my={40}>
                <Stack align="center" mb="xl">
                    <Center>
                        <IconShieldLock size={48} color="var(--mantine-color-blue-6)" />
                    </Center>
                    <Title ta="center" order={2}>
                        Redefinir senha
                    </Title>
                    <Text c="dimmed" size="sm" ta="center">
                        Informe seu e-mail e enviaremos um link para você definir uma nova senha.
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
                                leftSection={<IconMail size={16} />}
                                value={data.email}
                                onChange={(e) => setData('email', e.currentTarget.value)}
                                error={errors.email}
                            />
                            <Button type="submit" fullWidth mt="md" loading={processing} radius="md">
                                Enviar link
                            </Button>
                            <Anchor component={Link} href={route('login')} size="sm" ta="center">
                                Voltar para o login
                            </Anchor>
                        </Stack>
                    </form>
                </Paper>
            </Container>
        </GuestLayout>
    );
}
