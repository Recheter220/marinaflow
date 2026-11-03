/**
 * Formata um CEP com máscara (00000-000), aceitando só números e no máximo
 * 8 dígitos. Aceita o valor com ou sem máscara.
 */
export function formatCep(valor) {
    const digitos = (valor ?? '').replace(/\D/g, '').slice(0, 8);

    if (digitos.length <= 5) {
        return digitos;
    }

    return `${digitos.slice(0, 5)}-${digitos.slice(5)}`;
}