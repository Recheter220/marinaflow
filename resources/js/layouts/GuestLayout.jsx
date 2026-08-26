import { Box } from '@mantine/core';

/** Fundo compartilhado pelas telas fora do AppShell (login, convite, reset). */
export default function GuestLayout({ children }) {
    return (
        <Box
            style={{
                minHeight: '100vh',
                display: 'flex',
                alignItems: 'center',
                justifyContent: 'center',
                backgroundColor: 'var(--app-bg)',
                backgroundImage:
                    'radial-gradient(circle at 50% 50%, rgba(34, 139, 230, 0.05) 0%, transparent 50%)',
            }}
        >
            {children}
        </Box>
    );
}
