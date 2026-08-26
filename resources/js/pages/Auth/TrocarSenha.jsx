import { Head, useForm } from '@inertiajs/react';
import {
    Paper, PasswordInput, Button, Title, Text, Container, Stack, Center,
} from '@mantine/core';
import { IconLock, IconShieldLock } from '@tabler/icons-react';
import AppLayout from '@/layouts/AppLayout';

/**
 * Troca voluntária de senha, com o usuário já autenticado.
 *
 * O ramo de "primeiro acesso obrigatório" da versão Tauri não existe mais: quem
 * ainda não definiu senha não consegue fazer login, então não há como chegar
 * aqui nesse estado.
 */
export default function TrocarSenha() {
    const { data, setData, put, processing, errors, reset } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('senha.update'), {
            onSuccess: () => reset(),
        });
    };

    return (
        <>
            <Head title="Alterar senha" />
            <Container size={420} my={40}>
                <Stack align="center" mb="xl">
                    <Center>
                        <IconShieldLock size={48} color="var(--mantine-color-blue-6)" />
                    </Center>
                    <Title ta="center" order={2}>
                        Alterar Senha
                    </Title>
                    <Text c="dimmed" size="sm" ta="center">
                        Defina uma nova senha para sua conta.
                    </Text>
                </Stack>

                <Paper withBorder shadow="md" p={30} radius="md">
                    <form onSubmit={submit}>
                        <Stack>
                            <PasswordInput
                                label="Senha Atual"
                                placeholder="Informe sua senha atual"
                                required
                                autoComplete="current-password"
                                leftSection={<IconLock size={16} />}
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.currentTarget.value)}
                                error={errors.current_password}
                            />
                            <PasswordInput
                                label="Nova Senha"
                                placeholder="Mínimo 8 caracteres"
                                required
                                autoComplete="new-password"
                                leftSection={<IconLock size={16} />}
                                value={data.password}
                                onChange={(e) => setData('password', e.currentTarget.value)}
                                error={errors.password}
                            />
                            <PasswordInput
                                label="Confirmar Nova Senha"
                                placeholder="Repita a nova senha"
                                required
                                autoComplete="new-password"
                                leftSection={<IconLock size={16} />}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.currentTarget.value)}
                                error={errors.password_confirmation}
                            />

                            <Button type="submit" fullWidth mt="xl" loading={processing} radius="md">
                                Atualizar Senha
                            </Button>
                        </Stack>
                    </form>
                </Paper>
            </Container>
        </>
    );
}

TrocarSenha.layout = (page) => <AppLayout>{page}</AppLayout>;
