import { computed, onBeforeUnmount, ref, watch, type Ref } from "vue"

export const MAX_PREVIEW_HEIGHT = 500

/**
 * Turns a banner ratio into its width divided by height, accepting both the
 * "16/9" and the "1.77" notations. Returns 0 when the ratio is unusable.
 */
export const parseBannerRatio = (ratio: string | undefined | null): number => {
    if (!ratio) {
        return 0
    }

    if (ratio.includes("/")) {
        const [width, height] = ratio.split("/").map(Number)

        return width > 0 && height > 0 ? width / height : 0
    }

    const numericRatio = Number(ratio)

    return Number.isFinite(numericRatio) && numericRatio > 0 ? numericRatio : 0
}

export const getPreviewHeight = (ratio: string | undefined | null, containerWidth: number): number => {
    const aspectRatio = parseBannerRatio(ratio)

    if (!aspectRatio || containerWidth <= 0) {
        return 0
    }

    return containerWidth / aspectRatio
}

export const getPreviewScale = (previewHeight: number): number => {
    return previewHeight > MAX_PREVIEW_HEIGHT ? MAX_PREVIEW_HEIGHT / previewHeight : 1
}

/**
 * Tracks the width of the preview container and scales the banner down when it
 * would grow taller than MAX_PREVIEW_HEIGHT.
 */
export const useBannerPreviewScale = (containerRef: Ref<HTMLElement | null>, ratio: Ref<string>) => {
    const containerWidth = ref(0)

    let resizeObserver: ResizeObserver | null = null

    const measureContainer = () => {
        containerWidth.value = containerRef.value?.offsetWidth ?? 0
    }

    watch(
        containerRef,
        (container) => {
            resizeObserver?.disconnect()
            resizeObserver = null

            if (!container) {
                containerWidth.value = 0
                return
            }

            containerWidth.value = container.offsetWidth

            resizeObserver = new ResizeObserver((entries) => {
                for (const entry of entries) {
                    containerWidth.value = entry.contentRect.width
                }
            })
            resizeObserver.observe(container)
        },
        { immediate: true, flush: "post" }
    )

    onBeforeUnmount(() => {
        resizeObserver?.disconnect()
        resizeObserver = null
    })

    const previewHeight = computed(() => getPreviewHeight(ratio.value, containerWidth.value))
    const needsScale = computed(() => previewHeight.value > MAX_PREVIEW_HEIGHT)
    const scaleValue = computed(() => getPreviewScale(previewHeight.value))

    return { containerWidth, previewHeight, needsScale, scaleValue, measureContainer }
}
