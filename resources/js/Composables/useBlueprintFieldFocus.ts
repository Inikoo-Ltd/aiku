import { nextTick, onBeforeUnmount, reactive, ref, type Ref } from "vue"

export interface BlueprintFieldLocation {
    sectionIndex: number
    fieldPath: string
}

export interface BlueprintFieldFocus {
    path: string | null
    token: number
}

const fieldPath = (field: any): string => {
    return Array.isArray(field?.name) ? field.name.join(".") : String(field?.name ?? "")
}

/**
 * Blueprint field that owns a path. A field wins on an exact match, or on being
 * the longest prefix of the path, so `layout.corners.topLeft` resolves to the
 * `layout.corners` field that renders all six corners.
 */
export const findBlueprintField = (blueprint: any[], path: string): BlueprintFieldLocation | null => {
    let found: BlueprintFieldLocation | null = null

    blueprint?.forEach((section: any, sectionIndex: number) => {
        section?.fields?.forEach((field: any) => {
            const name = fieldPath(field)

            if (!name || (path !== name && !path.startsWith(`${name}.`))) {
                return
            }

            if (!found || name.length > found.fieldPath.length) {
                found = { sectionIndex, fieldPath: name }
            }
        })
    })

    return found
}

const HIGHLIGHT_MS = 1600

/**
 * Opens the blueprint field a canvas selection points at: right tab, scrolled
 * into view, briefly highlighted and, for a plain input, focused and selected.
 */
export const useBlueprintFieldFocus = (
    container: Ref<HTMLElement | null>,
    blueprint: () => any[],
    current: Ref<number>
) => {
    const highlightedPath = ref<string | null>(null)
    const focusState = reactive<BlueprintFieldFocus>({ path: null, token: 0 })

    let highlightTimeout: ReturnType<typeof setTimeout> | null = null

    const focusFieldPath = async (path: string | null | undefined) => {
        if (!path) {
            return
        }

        const location = findBlueprintField(blueprint(), path)

        if (!location) {
            return
        }

        current.value = location.sectionIndex
        focusState.path = path
        focusState.token++

        await nextTick()

        const field = container.value?.querySelector<HTMLElement>(`[data-field-path="${location.fieldPath}"]`)

        if (!field) {
            return
        }

        field.scrollIntoView({ block: "center", behavior: "smooth" })

        highlightedPath.value = location.fieldPath
        if (highlightTimeout) {
            clearTimeout(highlightTimeout)
        }
        highlightTimeout = setTimeout(() => (highlightedPath.value = null), HIGHLIGHT_MS)

        const input = field.querySelector<HTMLInputElement>('input:not([type="hidden"]), textarea')

        if (input && !input.disabled && !input.readOnly) {
            input.focus()
        }
    }

    onBeforeUnmount(() => {
        if (highlightTimeout) {
            clearTimeout(highlightTimeout)
        }
    })

    return { highlightedPath, focusState, focusFieldPath }
}
