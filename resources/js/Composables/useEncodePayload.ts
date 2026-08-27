export const encodePayload = (payload: unknown): string | undefined => {
    if (payload === undefined) {
        return undefined
    }

    const bytes = new TextEncoder().encode(JSON.stringify(payload))

    let binary = ''
    for (const byte of bytes) {
        binary += String.fromCharCode(byte)
    }

    return btoa(binary)
}
