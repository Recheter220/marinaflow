/** Catálogo de serviços oferecido nos checkboxes de registro/edição. */
export const SERVICO_OPTIONS = [
    'Limpeza',
    'Lavagem',
    'Motor',
    'Guincho da âncora',
    'Luzes de navegação',
    'Rádio de som',
    'radio VHF',
    'Buzina',
].sort((a, b) => a.localeCompare(b, 'pt-BR', { sensitivity: 'base' }));

export const SERVICO_OPTION_COLUMNS = [SERVICO_OPTIONS.slice(0, 4), SERVICO_OPTIONS.slice(4, 8)];
