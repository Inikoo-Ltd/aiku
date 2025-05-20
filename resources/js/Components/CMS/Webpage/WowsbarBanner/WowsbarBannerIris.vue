<script setup lang="ts">
// import SliderLandscape from "@/Components/Banners/Slider/SliderLandscape.vue";
// import SliderSquare from "@/Components/Banners/Slider/SliderSquare.vue";
import { getStyles } from "@/Composables/styles";
import { trans } from "laravel-vue-i18n"
import { defineAsyncComponent } from "vue"

const SliderSquare = defineAsyncComponent(() => import("@/Components/Banners/Slider/SliderSquare.vue"));
const SliderLandscape = defineAsyncComponent(() => import("@/Components/Banners/Slider/SliderLandscape.vue"));


const props = defineProps<{ 
    fieldValue: { 
        compiled_layout?: any, 
        container : { 
            properties : any
        }
    }
    screenType: 'mobile' | 'tablet' | 'desktop'
}>();

</script>

<template>
    <div :style="getStyles(fieldValue?.container?.properties,screenType)">
        <SliderLandscape v-if="fieldValue?.compiled_layout?.type === 'landscape'" :data="fieldValue.compiled_layout"
            :production="true" />
        <SliderSquare v-else-if="fieldValue?.compiled_layout?.type === 'square'" :data="fieldValue.compiled_layout" :production="true" />
        <div v-else class="py-4 w-full bg-gray-100 text-center text-gray-400 italic">
            {{ trans("Banner is empty") }}
        </div>
    </div>
</template>