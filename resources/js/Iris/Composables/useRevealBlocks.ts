import { computed, nextTick, onBeforeUnmount, onMounted, ref } from "vue"

const cleanKey = (value?: string | null): string => (value ?? "").toString().trim().replace(/^#/, "")

const asList = (webBlocks: any): any[] => (Array.isArray(webBlocks) ? webBlocks : Object.values(webBlocks ?? {}))

export function useRevealBlocks(getWebBlocks: () => any) {
    const revealedKeys = ref<string[]>([])
    const labelsBeforeReveal = new Map<HTMLElement, string>()

    const revealConfigs = computed(() => {
        const configs = new Map<string, { close_label?: string | null; scroll_to?: boolean }>()

        for (const webBlock of asList(getWebBlocks())) {
            const key = cleanKey(webBlock?.reveal?.key)

            if (key) {
                configs.set(key, webBlock.reveal)
            }
        }

        return configs
    })

    const isBlockVisible = (webBlock: any): boolean => {
        const key = cleanKey(webBlock?.reveal?.key)

        return !key || revealedKeys.value.includes(key)
    }

    const findLabelElement = (trigger: HTMLElement): HTMLElement | null => {
        let element = trigger

        while (element.children.length === 1) {
            element = element.children[0] as HTMLElement
        }

        return element.children.length === 0 && (element.textContent ?? "").trim() ? element : null
    }

    const setTriggerLabels = (key: string, isRevealedNow: boolean) => {
        const labelWhenRevealed = revealConfigs.value.get(key)?.close_label

        if (!labelWhenRevealed) {
            return
        }

        const triggers = document.querySelectorAll<HTMLElement>(
            `[data-reveal-target="${key}"], a[href="#${key}"], a[href$="/#${key}"]`
        )

        for (const trigger of triggers) {
            const label = findLabelElement(trigger)

            if (!label) {
                continue
            }

            if (isRevealedNow) {
                if (!labelsBeforeReveal.has(label)) {
                    labelsBeforeReveal.set(label, label.textContent ?? "")
                }

                label.textContent = labelWhenRevealed
                continue
            }

            const labelBeforeReveal = labelsBeforeReveal.get(label)

            if (labelBeforeReveal !== undefined) {
                label.textContent = labelBeforeReveal
                labelsBeforeReveal.delete(label)
            }
        }
    }

    const toggleReveal = (key: string) => {
        const config = revealConfigs.value.get(key)

        if (!config) {
            return
        }

        const isRevealedNow = !revealedKeys.value.includes(key)

        revealedKeys.value = isRevealedNow
            ? [...revealedKeys.value, key]
            : revealedKeys.value.filter(revealedKey => revealedKey !== key)

        setTriggerLabels(key, isRevealedNow)

        if (isRevealedNow && config.scroll_to !== false) {
            nextTick(() => {
                document.getElementById(key)?.scrollIntoView({ behavior: "smooth", block: "start" })
            })
        }
    }

    const handleDocumentClick = (event: MouseEvent) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return
        }

        const trigger = (event.target as Element | null)?.closest?.("[data-reveal-target], a[href]") as HTMLElement | null

        if (!trigger) {
            return
        }

        const href = trigger.getAttribute("href") ?? ""
        const hashIndex = href.indexOf("#")
        const key = cleanKey(trigger.getAttribute("data-reveal-target") || (hashIndex < 0 ? "" : href.slice(hashIndex)))

        if (!key || !revealConfigs.value.has(key)) {
            return
        }

        event.preventDefault()
        toggleReveal(key)
    }

    const revealFromHash = () => {
        const key = cleanKey(window.location.hash)

        if (key && !revealedKeys.value.includes(key)) {
            toggleReveal(key)
        }
    }

    onMounted(() => {
        document.addEventListener("click", handleDocumentClick)
        window.addEventListener("hashchange", revealFromHash)
        revealFromHash()
    })

    onBeforeUnmount(() => {
        document.removeEventListener("click", handleDocumentClick)
        window.removeEventListener("hashchange", revealFromHash)
    })

    return { isBlockVisible }
}
