/*
 * Author: Raul Perusquia <raul@inikoo.com>
 * Created: Thu, 31 Jul 2026 10:00:00 Malaysia Time, Kuala Lumpur, Malaysia
 * Copyright (c) 2026, Raul A Perusquia Flores
 */

// Compact imgproxy gallery emitted by HasCardWebImages::compactGallery():
// { _cig: { u: host, b: base64Source }, v: { avif: "sig/proc|.avif", ... } }
// Reassembles the full url map; passes anything else through untouched.

export interface CompactGallery {
    _cig: { u: string; b: string }
    v: Record<string, string>
}

export function isCompactGallery(value: unknown): value is CompactGallery {
    return !!value && typeof value === 'object' && '_cig' in (value as object)
}

export function expandGallery(gallery: any): any {
    if (!isCompactGallery(gallery)) {
        return gallery
    }

    const { u, b } = gallery._cig
    const out: Record<string, string> = {}

    for (const format in gallery.v) {
        const raw = gallery.v[format]
        const splitAt = raw.lastIndexOf('|')
        const prefix = raw.slice(0, splitAt)
        const ext = raw.slice(splitAt + 1)
        out[format] = `${u}/${prefix}/${b}${ext}`
    }

    return out
}
