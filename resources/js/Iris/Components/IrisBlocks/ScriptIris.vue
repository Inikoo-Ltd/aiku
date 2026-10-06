<script setup lang="ts">
import { faCube, faStar } from "@fortawesome/free-solid-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import "swiper/swiper-bundle.css" // Swiper styles
import { computed, inject, ref } from "vue"
import { getStyles } from "@/Composables/styles"
import { useHtmlWithScripts } from "@/Composables/useHtmlWithScripts"
library.add(faCube, faStar)

const props = defineProps<{
    fieldValue: {
        value: string
    }
    screenType: "mobile" | "tablet" | "desktop"
    indexBlock:number
    code?: string
}>()
const layout: any = inject("layout", {})

const scriptContainer = ref<HTMLElement | null>(null)
const { htmlWithoutScripts } = useHtmlWithScripts(computed(() => props.fieldValue?.value), scriptContainer)

</script>

<template>
    <div :id="fieldValue?.id ? fieldValue?.id  : 'script'+indexBlock"  component="script">
        <div :style="getStyles(layout?.app?.webpage_layout?.container?.properties, screenType)"
            class="w-full py-6 px-6 flex gap-x-10 editor-class overflow-x-auto font-mono">
            <div ref="scriptContainer" class="w-full" v-html="htmlWithoutScripts"></div>
        </div>
    </div>
</template>
