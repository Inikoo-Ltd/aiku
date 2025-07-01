<script setup lang="ts">
import { faCube, faStar } from "@fortawesome/free-solid-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import "swiper/swiper-bundle.css" // Swiper styles
import { inject } from "vue"
import { getStyles } from "@/Composables/styles"
library.add(faCube, faStar)

const props = defineProps<{
    fieldValue: {
        value: string
    }
    screenType: "mobile" | "tablet" | "desktop"
}>()
const layout: any = inject("layout", {})

</script>

<template>
    <div id="script">
        <div :style="getStyles(layout?.app?.webpage_layout?.container?.properties, screenType)"
            class="w-full py-6 px-6 flex gap-x-10 editor-class overflow-x-auto font-mono">
            <div v-html="fieldValue.value"></div>
        </div>
    </div>
</template>
