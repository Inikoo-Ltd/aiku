<script setup lang="ts">
import { computed, inject } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faFileCsv, faImages } from "@fal"
import { trans } from "laravel-vue-i18n"

library.add(faFileCsv, faImages)

const props = withDefaults(
    defineProps<{
        scope: string
        slug?: string
        type: "csv" | "images"
        variant?: "cell" | "card"
    }>(),
    { variant: "cell" }
)

const resolveRoute = inject<((name: string, params?: object) => string) | null>("route", null)

const feedRouteByScope: Record<string, { name: string; parameter: string }> = {
    family: { name: "iris.catalogue.feeds.product_category", parameter: "productCategory" },
    product: { name: "iris.catalogue.feeds.product", parameter: "product" },
}

const downloadUrl = computed(() => {
    const feedRoute = feedRouteByScope[props.scope]

    if (!feedRoute || !props.slug || !resolveRoute) {
        return null
    }

    const action = props.type === "images" ? "download_img" : "download"

    try {
        return resolveRoute(`${feedRoute.name}.${action}`, { [feedRoute.parameter]: props.slug })
    } catch {
        return null
    }
})

const icon = computed(() => (props.type === "images" ? faImages : faFileCsv))

const label = computed(() =>
    props.type === "images" ? trans("Download images (zip)") : trans("Download products (csv)")
)

const linkClass = computed(() =>
    props.variant === "card"
        ? "flex h-9 w-9 items-center justify-center rounded-lg text-gray-500 transition hover:bg-gray-100 hover:text-primary-600"
        : "text-gray-500 transition hover:text-primary-600"
)
</script>

<template>
    <a
        v-if="downloadUrl"
        :href="downloadUrl"
        target="_blank"
        rel="noopener"
        :class="linkClass"
        :title="label"
        :aria-label="label"
        v-tooltip="label"
    >
        <FontAwesomeIcon :icon="icon" fixed-width aria-hidden="true" />
    </a>
</template>
