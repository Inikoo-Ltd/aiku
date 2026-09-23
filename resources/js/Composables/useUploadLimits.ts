/**
 * Author: Andi Ferdiawan <dev@aw-advantage.com>
 * Copyright: 2026
 */
import { computed } from "vue"
import { usePage } from "@inertiajs/vue3"
import { ctrans } from "@/Composables/useTrans"

// Room for the message text, the other form fields and the multipart boundaries that travel with
// the files. Staying a little under the server's limit costs nothing; going a byte over costs the
// whole request, including the files that would have been fine on their own.
const POST_OVERHEAD_BYTES = 256 * 1024

export const formatBytes = (bytes: number): string => {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(bytes % (1024 * 1024) === 0 ? 0 : 1)}MB`
    }

    return `${Math.max(1, Math.round(bytes / 1024))}KB`
}

/**
 * What this server will really accept, read from its own php.ini rather than guessed in the
 * browser. A limit the browser invents is a promise the server never made: a file under the
 * invented one and over the real one is refused after the upload, with nothing to say why.
 */
export const useUploadLimits = () => {
    const page = usePage()
    const limits = computed<Record<string, any>>(() => (page.props as any)?.upload ?? {})

    const maxFileBytes = computed<number | null>(() => Number(limits.value.max_file_bytes) || null)

    const maxBatchBytes = computed<number | null>(() => {
        const post = Number(limits.value.max_post_bytes) || null

        return post ? Math.max(0, post - POST_OVERHEAD_BYTES) : null
    })

    const smallest = (...caps: (number | null | undefined)[]): number | null => {
        const real = caps.filter((cap): cap is number => typeof cap === "number" && cap > 0)

        return real.length ? Math.min(...real) : null
    }

    /**
     * Why this file cannot join the batch, or null when it can. The batch is checked as well as
     * the file, which is the case that used to fail: two files each inside the limit, together
     * over it, and the one that was fine went down with the one that was not.
     */
    const rejectionFor = (
        file: File,
        alreadySelected: { size: number }[] = [],
        ownCapBytes?: number | null
    ): string | null => {
        const cap = smallest(ownCapBytes, maxFileBytes.value, maxBatchBytes.value)

        if (cap && file.size > cap) {
            return ctrans("This file is :size. The largest this server accepts is :max.", {
                size: formatBytes(file.size),
                max: formatBytes(cap),
            })
        }

        const batch = maxBatchBytes.value

        if (!batch) {
            return null
        }

        const total = alreadySelected.reduce((sum, selected) => sum + selected.size, 0) + file.size

        if (total > batch) {
            return ctrans(
                "Adding this would make :total to send at once, and this server takes :max. Send what you have attached first, then add this one.",
                { total: formatBytes(total), max: formatBytes(batch) }
            )
        }

        return null
    }

    return { maxFileBytes, maxBatchBytes, rejectionFor, formatBytes }
}
