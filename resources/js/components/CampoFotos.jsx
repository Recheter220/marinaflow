import { Alert, FileInput, List, Progress, Stack, Text } from '@mantine/core';
import { IconAlertCircle, IconPhoto } from '@tabler/icons-react';
import { TIPOS_ACEITOS } from '@/utils/fotos';

/**
 * Seleção de fotos com a barra de progresso do envio (RF-04.7) e a lista de
 * recusadas com o motivo (RF-04.8). O estado do envio vem de `useEnvioFotos`.
 */
export default function CampoFotos({ value, onChange, restantes, progresso, recusadas, onFecharRecusadas }) {
    const enviando = progresso !== null;
    const limite = Math.min(10, restantes);
    const erro = value.length <= limite
        ? null
        : (limite < 10 ? `Este serviço aceita só mais ${restantes} foto(s).` : 'Envie no máximo 10 fotos por vez.');

    return (
        <Stack gap="xs">
            <FileInput
                label="Fotos do serviço"
                description={`JPEG, PNG ou WebP. Até ${limite} por envio.`}
                placeholder={restantes > 0 ? 'Selecione as fotos' : 'Limite de fotos atingido'}
                leftSection={<IconPhoto size={16} />}
                accept={TIPOS_ACEITOS.join(',')}
                multiple
                clearable
                disabled={enviando || restantes <= 0}
                value={value}
                onChange={onChange}
                error={erro}
            />

            {enviando && (
                <Progress value={progresso} animated aria-label="Progresso do envio das fotos" />
            )}

            {recusadas.length > 0 && (
                <Alert
                    icon={<IconAlertCircle size={16} />}
                    color="red"
                    title="Algumas fotos não foram enviadas"
                    withCloseButton
                    onClose={onFecharRecusadas}
                >
                    <List size="sm">
                        {recusadas.map(({ nome, motivo }) => (
                            <List.Item key={nome}>
                                <Text span fw={500} size="sm">{nome}</Text>: {motivo}
                            </List.Item>
                        ))}
                    </List>
                </Alert>
            )}
        </Stack>
    );
}
