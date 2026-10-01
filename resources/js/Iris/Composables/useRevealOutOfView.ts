import { onBeforeUnmount, ref, watch, type Ref } from "vue"

export const useRevealOutOfView = (container: Ref<HTMLElement | null>, hasContent: () => boolean) => {
    const isRevealed = ref(false)
    let observer: IntersectionObserver | null = null

    const reveal = () => {
        isRevealed.value = true
        observer?.disconnect()
        observer = null
    }

    watch(hasContent, (contentArrived) => {
        if (!contentArrived || isRevealed.value || observer) {
            return
        }
        if (!container.value || typeof IntersectionObserver === "undefined") {
            reveal()
            return
        }

        observer = new IntersectionObserver((entries) => {
            if (entries.some((entry) => !entry.isIntersecting)) {
                reveal()
            }
        })
        observer.observe(container.value)
    })

    onBeforeUnmount(() => observer?.disconnect())

    return isRevealed
}
