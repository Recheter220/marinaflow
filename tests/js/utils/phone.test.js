import { formatPhoneNumber, normalizePhoneNumber } from '@/utils/phone';

describe('normalizePhoneNumber', () => {
    it('mantém só os dígitos e corta em onze', () => {
        expect(normalizePhoneNumber('(11) 98765-4321 ramal 9')).toBe('11987654321');
    });

    it('trata valor vazio como texto vazio', () => {
        expect(normalizePhoneNumber(null)).toBe('');
        expect(normalizePhoneNumber(undefined)).toBe('');
    });
});

describe('formatPhoneNumber', () => {
    it.each([
        ['', ''],
        ['1', '(1'],
        ['11', '(11'],
        ['113', '(11) 3'],
        ['113456', '(11) 3456'],
        ['1134567', '(11) 3456-7'],
        ['1134567890', '(11) 3456-7890'],
        ['11987654321', '(11) 98765-4321'],
    ])('formata %j como %j enquanto a pessoa digita', (digitado, esperado) => {
        expect(formatPhoneNumber(digitado)).toBe(esperado);
    });

    it('reformata um valor já mascarado sem duplicar a máscara', () => {
        expect(formatPhoneNumber('(11) 98765-4321')).toBe('(11) 98765-4321');
    });
});
