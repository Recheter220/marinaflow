import { useRef, useState } from 'react';
import { Group, Loader, SimpleGrid, TextInput } from '@mantine/core';
import { notifications } from '@mantine/notifications';
import { formatCep } from '@/utils/cep';

/**
 * Campos de endereço do funcionário. Quem guarda os valores é o formulário da
 * página (`form`, do useForm do Mantine). Ao completar o CEP, consulta a rota
 * de integração e preenche logradouro, bairro, cidade e UF.
 */
export default function CamposEndereco({ form }) {
    const [buscando, setBuscando] = useState(false);

    const numeroRef = useRef(null);

    const buscarEndereco = async (cepDigitos) => {
        setBuscando(true);

        try {
            const resposta = await fetch(route('integracoes.cep', cepDigitos), {
                headers: { Accept: 'application/json' },
            });
            const dados = await resposta.json();

            if (!resposta.ok) {
                notifications.show({
                    message: dados.message ?? 'Não foi possível consultar o CEP.',
                    color: 'orange',
                });
                return;
            }

            form.setValues({
                logradouro: dados.logradouro ?? '',
                bairro: dados.bairro ?? '',
                cidade: dados.cidade ?? '',
                uf: dados.uf ?? '',
            });
            numeroRef.current?.focus();
        } catch {
            notifications.show({
                message: 'Não foi possível consultar o CEP. Preencha o endereço manualmente.',
                color: 'orange',
            });
        } finally {
            setBuscando(false);
        }
    };

    const aoDigitarCep = (event) => {
        const texto = formatCep(event.currentTarget.value);
        const digitosAntes = form.values.cep.replace(/\D/g, '');
        const digitos = texto.replace(/\D/g, '');

        form.setFieldValue('cep', texto);

        if (digitos.length === 8 && digitos !== digitosAntes) {
            buscarEndereco(digitos);
        }
    };

    return (
        <>
            <TextInput
                label="CEP"
                placeholder="00000-000"
                rightSection={buscando ? <Loader size="xs" /> : null}
                {...form.getInputProps('cep')}
                onChange={aoDigitarCep}
            />
            <TextInput label="Logradouro" placeholder="Rua, avenida..." {...form.getInputProps('logradouro')} />
            <SimpleGrid cols={2}>
                <TextInput label="Número" ref={numeroRef} {...form.getInputProps('numero')} />
                <TextInput label="Complemento" {...form.getInputProps('complemento')} />
            </SimpleGrid>
            <TextInput label="Bairro" {...form.getInputProps('bairro')} />
            <Group align="flex-start" gap="sm">
                <TextInput label="Cidade" style={{ flex: 1 }} {...form.getInputProps('cidade')} />
                <TextInput label="UF" w={80} maxLength={2} {...form.getInputProps('uf')} />
            </Group>
        </>
    );
}