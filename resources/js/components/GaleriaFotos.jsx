import { useState } from 'react';
import { router } from '@inertiajs/react';
import { ActionIcon, Group, Image, Modal, Text, Tooltip, UnstyledButton } from '@mantine/core';
import { notifications } from '@mantine/notifications';
import { IconTrash } from '@tabler/icons-react';

/**
 * Miniaturas das fotos de um serviço (RF-04.4). Clique abre em tamanho cheio;
 * com `removivel`, cada miniatura ganha o botão de remover (RF-04.5).
 */
export default function GaleriaFotos({ fotos, tamanho = 64, removivel = false }) {
    const [ampliada, setAmpliada] = useState(null);
    const [removendo, setRemovendo] = useState(null);

    if (!fotos?.length) {
        return null;
    }

    const remover = (foto) => {
        if (!window.confirm(`Remover a foto "${foto.nome_original}"?`)) {
            return;
        }

        setRemovendo(foto.id);
        router.delete(route('servicos.fotos.destroy', foto.id), {
            preserveScroll: true,
            preserveState: true,
            onError: (errors) =>
                notifications.show({
                    title: 'Erro ao remover foto',
                    message: Object.values(errors).join(' '),
                    color: 'red',
                }),
            onFinish: () => setRemovendo(null),
        });
    };

    return (
        <>
            <Group gap="xs">
                {fotos.map((foto) => (
                    <div key={foto.id} style={{ position: 'relative' }}>
                        <UnstyledButton
                            onClick={() => setAmpliada(foto)}
                            aria-label={`Ampliar foto ${foto.nome_original}`}
                        >
                            <Image
                                src={foto.url}
                                alt={foto.nome_original}
                                w={tamanho}
                                h={tamanho}
                                radius="sm"
                                fit="cover"
                            />
                        </UnstyledButton>
                        {removivel && (
                            <Tooltip label="Remover foto">
                                <ActionIcon
                                    size="sm"
                                    color="red"
                                    variant="filled"
                                    radius="xl"
                                    loading={removendo === foto.id}
                                    onClick={() => remover(foto)}
                                    aria-label={`Remover foto ${foto.nome_original}`}
                                    style={{ position: 'absolute', top: -6, right: -6 }}
                                >
                                    <IconTrash size={12} />
                                </ActionIcon>
                            </Tooltip>
                        )}
                    </div>
                ))}
            </Group>

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
