<script setup lang="ts">
import { getStyles } from "@/Composables/styles";
import { trans } from "laravel-vue-i18n"
import { defineAsyncComponent, inject } from "vue"

const SliderSquare = defineAsyncComponent(() => import("@/Components/Banners/Slider/SliderSquare.vue"));
const SliderLandscape = defineAsyncComponent(() => import("@/Components/Banners/Slider/SliderLandscape.vue"));


const props = defineProps<{
    fieldValue: {
        compiled_layout?: any,
        container: {
            properties: any
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
}>();

const layout: any = inject("layout", {})
</script>

<template>
    <div id="banner">
        <div :style="{
            ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
            ...getStyles(fieldValue.container?.properties, screenType)
        }">
            <SliderLandscape v-if="fieldValue?.compiled_layout?.type === 'landscape'" :data="fieldValue.compiled_layout"
                :production="true" :view="screenType" />
            <SliderSquare v-else-if="fieldValue?.compiled_layout?.type === 'square'" :data="fieldValue.compiled_layout"
                :production="true" :view="screenType" />
            <div v-else class="py-4 w-full bg-gray-100 text-center text-gray-400 italic">
                {{ trans("Banner is empty") }}
            </div>
        </div>
    </div>
</template>