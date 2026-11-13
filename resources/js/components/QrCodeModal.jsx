import { Button, Center, Group, Image, Modal, Stack, Text } from '@mantine/core';
import { IconDownload, IconExternalLink, IconPrinter } from '@tabler/icons-react';

/**
 * RF-09.4 — QR da embarcação com download do SVG e impressão. Os links são
 * `<a>` comuns, não `<Link>`: o destino é arquivo, não página Inertia.
 */
export default function QrCodeModal({ embarcacao, onClose }) {
    const aberto = embarcacao !== null;
    const svgUrl = aberto ? route('embarcacoes.qrcode', embarcacao.id) : '';

    const imprimir = () => {
        const janela = window.open('', '_blank', 'noopener=no');
        if (!janela) return;

        const doc = janela.document;
        doc.title = `QR Code — ${embarcacao.nome}`;
        doc.body.style.cssText = 'font-family: sans-serif; text-align: center; margin: 2cm;';

        const img = doc.createElement('img');
        img.src = svgUrl;
        img.alt = `QR Code de ${embarcacao.nome}`;
        img.style.cssText = 'width: 6cm; height: 6cm;';
        img.onload = () => janela.print();

        const nome = doc.createElement('p');
        nome.textContent = embarcacao.nome;
        nome.style.cssText = 'font-size: 16pt; font-weight: bold; margin: 8pt 0 2pt;';

        const identificacao = doc.createElement('p');
        identificacao.textContent = embarcacao.identificacao;
        identificacao.style.margin = '0';

        doc.body.append(img, nome, identificacao);
    };

    return (
        <Modal opened={aberto} onClose={onClose} title="QR Code da embarcação" centered>
            {aberto && (
                <Stack gap="md">
                    <Center>
                        <Image
                            src={svgUrl}
                            alt={`QR Code de ${embarcacao.nome}`}
                            w={240}
                            h={240}
                        />
                    </Center>
                    <div>
                        <Text ta="center" fw={600}>{embarcacao.nome}</Text>
                        <Text ta="center" size="sm" c="dimmed">{embarcacao.identificacao}</Text>
                    </div>
                    <Group justify="center">
                        <Button
                            component="a"
                            href={route('embarcacoes.qrcode', { embarcacao: embarcacao.id, download: 1 })}
                            variant="default"
                            leftSection={<IconDownload size={16} />}
                        >
                            Baixar SVG
                        </Button>
                        <Button
                            component="a"
                            href={route('embarcacoes.qr', embarcacao.ulid)}
                            target="_blank"
                            rel="noopener"
                            variant="default"
                            leftSection={<IconExternalLink size={16} />}
                        >
                            Abrir link
                        </Button>
                        <Button leftSection={<IconPrinter size={16} />} onClick={imprimir}>
                            Imprimir
                        </Button>
                    </Group>
                </Stack>
            )}
        </Modal>
    );
}
