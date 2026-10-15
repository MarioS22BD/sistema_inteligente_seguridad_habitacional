/**
 * Genera una clave de idempotencia segura compatible con RFC 4122 v4.
 * Funciona de forma resiliente tanto en contextos seguros (HTTPS o localhost con crypto.randomUUID)
 * como en contextos no seguros (p. ej. acceso HTTP mediante dirección IP privada en red LAN).
 */
export function generateIdempotencyKey(): string {
    if (typeof window !== 'undefined' && window.crypto) {
        if (typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }

        if (typeof window.crypto.getRandomValues === 'function') {
            const bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            bytes[6] = (bytes[6] & 0x0f) | 0x40; // Version 4
            bytes[8] = (bytes[8] & 0x3f) | 0x80; // Variant RFC 4122
            const hex = Array.from(bytes, (b) =>
                b.toString(16).padStart(2, '0'),
            ).join('');
            return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
        }
    }

    // Fallback matemático compatible con la validación de Laravel (regex: /^[a-zA-Z0-9._:-]{8,80}$/)
    const timestamp = Date.now().toString(36);
    const randomPart =
        Math.random().toString(36).substring(2, 15) +
        Math.random().toString(36).substring(2, 15);
    return `idemp-${timestamp}-${randomPart}`;
}
