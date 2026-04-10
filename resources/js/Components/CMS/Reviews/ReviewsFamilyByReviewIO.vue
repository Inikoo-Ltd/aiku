<script setup lang="ts">
import { onMounted, watch, nextTick } from "vue"

interface ProductResource {
    id: number
    code: string
}

const props = defineProps<{
    products: ProductResource[]   // ✅ FIX: should be array
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
    el.innerHTML = ""

    if (typeof window.carouselInlineWidget !== "function") return

    new window.carouselInlineWidget(getId(), {
        store: props.review?.data?.store,
       /*  sku: sku, */
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

         styles: {
        '--common-star-color': '#facc15',          // ⭐ yellow
        '--common-star-disabled-color': 'rgba(0,0,0,0.25)',
        '--header-star-color': '#facc15',
        '--popup-star-color': '#facc15',

        '--badge-icon-color': '#22c55e',   
        '--badge-text-color': '#22c55e',
    }
    })
}

onMounted(async () => {
    await loadAssets()
    await initWidget()
})

watch(
    () => props.products,
    async () => {
        await initWidget()
    },
    { deep: true }
)

watch(
    () => props.code,
    async () => {
        await initWidget()
    }
)
</script>

<template>
    <div class="p-4">
        <div :id="`reviewsio-carousel-${code}`"></div>
    </div>
</template>