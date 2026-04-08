<script setup lang="ts">
import { onMounted, watch, nextTick } from "vue"

const props = defineProps<{
    review: any
    code: string
}>()

const SCRIPT_ID = "reviews-io-carousel-script"
const STYLE_ID_1 = "reviews-io-carousel-style"
const STYLE_ID_2 = "reviews-io-carousel-icons"

const loadAssets = (): Promise<void> => {
    return new Promise((resolve) => {

        if (!document.getElementById(STYLE_ID_1)) {
            const link1 = document.createElement("link")
            link1.id = STYLE_ID_1
            link1.rel = "stylesheet"
            link1.href = "https://assets.reviews.io/css/widgets/carousel-widget.css?_t=2026040801"
            document.head.appendChild(link1)
        }

        if (!document.getElementById(STYLE_ID_2)) {
            const link2 = document.createElement("link")
            link2.id = STYLE_ID_2
            link2.rel = "stylesheet"
            link2.href = "https://assets.reviews.io/iconfont/reviewsio-icons/style.css?_t=2026040801"
            document.head.appendChild(link2)
        }

        if (document.getElementById(SCRIPT_ID)) {
            resolve()
            return
        }

        const script = document.createElement("script")
        script.id = SCRIPT_ID
        script.src = "https://widget.reviews.io/carousel-inline-iframeless/dist.js?_t=2026040801"
        script.async = true
        script.onload = () => resolve()

        document.body.appendChild(script)
    })
}

const getId = () => `reviewsio-carousel-${props.code}`

const initWidget = async () => {
    await nextTick()

    const el = document.getElementById(getId())
    if (!el) return

    const store = props.review?.data?.store
    if (!store) return

    // clear previous instance
    el.innerHTML = ""

    if (typeof window.carouselInlineWidget !== "function") return

    new window.carouselInlineWidget(getId(), {
        store,
        carousel_type: "default",
        styles_carousel: "CarouselWidget--sideHeader",
        options: {
            general: {
                review_type: "company",
                min_reviews: "1",
                max_reviews: "20",
                enable_auto_scroll: 10000,
                enable_pause_button: false,
            },
        },
    })
}

onMounted(async () => {
    await loadAssets()
    await initWidget()
})

// re-init when code changes
watch(
    () => props.code,
    async () => {
        await initWidget()
    }
)

// re-init when review config changes
watch(
    () => props.review,
    async () => {
        await initWidget()
    },
    { deep: true }
)
</script>

<template>
    <div class="p-4">
        <div :id="`reviewsio-carousel-${code}`"></div>
    </div>
</template>