import { useState } from 'react';
import { router } from '@inertiajs/react';

/** RF-04.2 — o servidor valida de novo; aqui só se evita enviar o que ele recusaria. */
export const TIPOS_ACEITOS = ['image/jpeg', 'image/png', 'image/webp'];

export const MOTIVO_FORMATO = 'Formato não aceito: envie JPEG, PNG ou WebP.';

export const LIMITE_FOTOS_POR_SERVICO = 20;

const LADO_MAXIMO = 1920;

const QUALIDADE_JPEG = 0.85;

/** Abaixo disso e dentro do lado máximo, a foto segue como veio. */
const TAMANHO_SEM_REDIMENSIONAR = 1.5 * 1024 * 1024;

/**
 * A Vercel recusa corpo de requisição acima de 4,5 MB antes de chegar ao
 * Laravel. Os lotes ficam abaixo disso com folga para o multipart.
 */
const TAMANHO_MAXIMO_LOTE = 3.5 * 1024 * 1024;

const FOTOS_POR_LOTE = 10;

/**
 * Reduz a foto para no máximo 1920 px no lado maior, em JPEG. Foto de celular
 * passa de ~5 MB para ~400 KB, o que cabe no limite da plataforma e poupa o bucket.
 */
export async function redimensionarFoto(arquivo) {
    const bitmap = await createImageBitmap(arquivo);
    const escala = Math.min(1, LADO_MAXIMO / Math.max(bitmap.width, bitmap.height));

    if (escala === 1 && arquivo.size <= TAMANHO_SEM_REDIMENSIONAR) {
        bitmap.close();

        return arquivo;
    }

    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * escala);
    canvas.height = Math.round(bitmap.height * escala);

    const contexto = canvas.getContext('2d');
    // PNG com transparência ficaria com fundo preto no JPEG.
    contexto.fillStyle = '#fff';
    contexto.fillRect(0, 0, canvas.width, canvas.height);
    contexto.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', QUALIDADE_JPEG));

    return new File([blob], `${arquivo.name.replace(/\.[^.]+$/, '')}.jpg`, { type: 'image/jpeg' });
}

function separarEmLotes(arquivos) {
    return arquivos.reduce((lotes, item) => {
        const atual = lotes.at(-1);
        const tamanhoAtual = atual?.reduce((soma, { foto }) => soma + foto.size, 0) ?? 0;

        if (!atual || atual.length === FOTOS_POR_LOTE || tamanhoAtual + item.foto.size > TAMANHO_MAXIMO_LOTE) {
            lotes.push([item]);
        } else {
            atual.push(item);
        }

        return lotes;
    }, []);
}

function enviarLote(url, lote, aoProgredir) {
    return new Promise((resolve) => {
        let erros = { fotos: 'Não foi possível enviar as fotos. Tente novamente.' };

        router.post(url, { fotos: lote.map(({ foto }) => foto) }, {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,
            onProgress: (evento) => aoProgredir(evento?.loaded ?? 0),
            onSuccess: () => {
                erros = null;
            },
            onError: (e) => {
                erros = e;
            },
            onFinish: () => resolve(erros),
        });
    });
}

/**
 * Envio de fotos para `servicos.fotos.store` com progresso (RF-04.7). Arquivo
 * recusado volta com o motivo e os demais seguem (RF-04.8).
 *
 * `enviar` resolve com os arquivos originais que não foram aceitos, para a
 * tela mantê-los selecionados.
 */
export function useEnvioFotos() {
    const [progresso, setProgresso] = useState(null);
    const [recusadas, setRecusadas] = useState([]);

    const enviar = async (servicoId, arquivos) => {
        const motivos = [];
        const naoEnviados = [];
        const preparados = [];

        setRecusadas([]);
        setProgresso(0);

        for (const original of arquivos) {
            if (!TIPOS_ACEITOS.includes(original.type)) {
                motivos.push({ nome: original.name, motivo: MOTIVO_FORMATO });
                naoEnviados.push(original);
                continue;
            }

            try {
                preparados.push({ original, foto: await redimensionarFoto(original) });
            } catch {
                motivos.push({ nome: original.name, motivo: 'Não foi possível ler esta imagem.' });
                naoEnviados.push(original);
            }
        }

        const total = preparados.reduce((soma, { foto }) => soma + foto.size, 0);
        let concluido = 0;

        for (const lote of separarEmLotes(preparados)) {
            const erros = await enviarLote(route('servicos.fotos.store', servicoId), lote, (carregado) =>
                setProgresso(Math.min(100, Math.round(((concluido + carregado) / total) * 100))),
            );

            concluido += lote.reduce((soma, { foto }) => soma + foto.size, 0);

            if (erros) {
                lote.forEach(({ original }, indice) => {
                    motivos.push({
                        nome: original.name,
                        motivo: erros[`fotos.${indice}`]
                            ?? erros.fotos
                            ?? 'Não enviada porque outra foto do mesmo envio foi recusada. Envie de novo.',
                    });
                    naoEnviados.push(original);
                });
            }
        }

        setRecusadas(motivos);
        setProgresso(null);

        return naoEnviados;
    };

    return { enviar, enviando: progresso !== null, progresso, recusadas, limparRecusadas: () => setRecusadas([]) };
}
