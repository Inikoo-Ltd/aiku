<script setup lang="ts">
import { faMedal } from "@fas"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { getStyles } from "@/Composables/styles"
import { ref, watch, computed } from "vue"
import { get } from "lodash-es"

const props = defineProps<{
    data: any
    topSeller: number
    screenType?: "mobile" | "tablet" | "desktop"
}>()

const bestSeller1Icon = ref(getStyles(props.data?.bestseller1?.icon, props.screenType, false))
const bestSeller2Icon = ref(getStyles(props.data?.bestseller2?.icon, props.screenType, false))
const bestSeller3Icon = ref(getStyles(props.data?.bestseller3?.icon, props.screenType, false))

const bestSeller1Text = ref(getStyles(props.data?.bestseller1?.text?.properties, props.screenType, false))
const bestSeller2Text = ref(getStyles(props.data?.bestseller2?.text?.properties, props.screenType, false))
const bestSeller3Text = ref(getStyles(props.data?.bestseller3?.text?.properties, props.screenType, false))

const bestSeller1Layout = ref(getStyles(props.data?.bestseller1?.container?.properties, props.screenType, false))
const bestSeller2Layout = ref(getStyles(props.data?.bestseller2?.container?.properties, props.screenType, false))
const bestSeller3Layout = ref(getStyles(props.data?.bestseller3?.container?.properties, props.screenType, false))

watch(
    () => props.data,
    () => {
        bestSeller1Icon.value = getStyles(props.data?.bestseller1?.icon, props.screenType, false)
        bestSeller2Icon.value = getStyles(props.data?.bestseller2?.icon, props.screenType, false)
        bestSeller3Icon.value = getStyles(props.data?.bestseller3?.icon, props.screenType, false)

        bestSeller1Text.value = getStyles(props.data?.bestseller1?.text?.properties, props.screenType, false)
        bestSeller2Text.value = getStyles(props.data?.bestseller2?.text?.properties, props.screenType, false)
        bestSeller3Text.value = getStyles(props.data?.bestseller3?.text?.properties, props.screenType, false)

        bestSeller1Layout.value = getStyles(props.data?.bestseller1?.container?.properties, props.screenType, false)
        bestSeller2Layout.value = getStyles(props.data?.bestseller2?.container?.properties, props.screenType, false)
        bestSeller3Layout.value = getStyles(props.data?.bestseller3?.container?.properties, props.screenType, false)
    },
    { deep: true }
)
const isMobile = computed(() => props.screenType == "mobile")

const showText = computed(() => {
    if (!isMobile.value) return true

    if (props.topSeller == 1) {
        return !get(props.data, ['bestseller1', 'icon', 'use_icon'], true)
    }

    if (props.topSeller == 2) {
        return !get(props.data, ['bestseller2', 'icon', 'use_icon'], true)
    }

    if (props.topSeller == 3) {
        return !get(props.data, ['bestseller3', 'icon', 'use_icon'], true)
    }

    return true
})

</script>
<template>
    <div class="absolute top-2 left-2 border border-black text-xs font-bold px-2 py-0.5 rounded z-10" :class="{
        'best-seller-1-container': props.topSeller === 1,
        'best-seller-2-container': props.topSeller === 2,
        'best-seller-3-container': props.topSeller === 3
    }">
        <!-- Best Seller 1 Icon -->
        <FontAwesomeIcon v-if="props.topSeller === 1 && get(props.data, ['bestseller1', 'icon', 'use_icon'], true)" :icon="faMedal"
            class="w-3.5 h-3.5 mr-1" :class="{ 'best-seller-1-icon': props.topSeller === 1 }" />

        <!-- Best Seller 2 Icon -->
        <FontAwesomeIcon v-if="props.topSeller === 2 && get(props.data, ['bestseller2', 'icon', 'use_icon'], true)" :icon="faMedal"
            class="w-3.5 h-3.5 mr-1" :class="{ 'best-seller-2-icon': props.topSeller === 2 }" />

        <!-- Best Seller 3 Icon -->
        <FontAwesomeIcon v-if="props.topSeller === 3 && get(props.data, ['bestseller3', 'icon', 'use_icon'], true)" :icon="faMedal"
            class="w-3.5 h-3.5 mr-1" :class="{ 'best-seller-3-icon': props.topSeller === 3 }" />

        <span v-if="showText" :class="{
            'best-seller-1-text': props.topSeller === 1,
            'best-seller-2-text': props.topSeller === 2,
            'best-seller-3-text': props.topSeller === 3
        }">
            BESTSELLER
        </span>
    </div>

</template>

<style scoped>
.best-seller-1-icon {
    color: v-bind(bestSeller1Icon?.color || '#FFD700');
    font-family: v-bind(bestSeller1Icon?.fontFamily);
    font-size: v-bind(bestSeller1Icon?.fontSize);
    font-style: v-bind(bestSeller1Icon?.fontStyle);
}

.best-seller-2-icon {
    color: v-bind(bestSeller2Icon?.color || '#C0C0C0');
    font-family: v-bind(bestSeller2Icon?.fontFamily);
    font-size: v-bind(bestSeller2Icon?.fontSize);
    font-style: v-bind(bestSeller2Icon?.fontStyle);
}

.best-seller-3-icon {
    color: v-bind(bestSeller3Icon?.color || '#CD7F32');
    font-family: v-bind(bestSeller3Icon?.fontFamily);
    font-size: v-bind(bestSeller3Icon?.fontSize);
    font-style: v-bind(bestSeller3Icon?.fontStyle);
}

.best-seller-1-text {
    color: v-bind(bestSeller1Text?.color);
    font-family: v-bind(bestSeller1Text?.fontFamily);
    font-size: v-bind(bestSeller1Text?.fontSize);
    font-style: v-bind(bestSeller1Text?.fontStyle);
}

.best-seller-2-text {
    color: v-bind(bestSeller2Text?.color);
    font-family: v-bind(bestSeller2Text?.fontFamily);
    font-size: v-bind(bestSeller2Text?.fontSize);
    font-style: v-bind(bestSeller2Text?.fontStyle);
}

.best-seller-3-text {
    color: v-bind(bestSeller3Text?.color);
    font-family: v-bind(bestSeller3Text?.fontFamily);
    font-size: v-bind(bestSeller3Text?.fontSize);
    font-style: v-bind(bestSeller3Text?.fontStyle);
}


.best-seller-1-container {
  background: v-bind(bestSeller1Layout?.background || 'white') !important;
  border-top: v-bind(bestSeller1Layout?.borderTop || '1px solid black') !important;
  border-bottom: v-bind(bestSeller1Layout?.borderBottom || '1px solid black') !important;
  border-left: v-bind(bestSeller1Layout?.borderLeft || '1px solid black') !important;
  border-right: v-bind(bestSeller1Layout?.borderRight || '1px solid black') !important;

  border-top-left-radius: v-bind(bestSeller1Layout?.borderTopLeftRadius || '0.25rem') !important;
  border-top-right-radius: v-bind(bestSeller1Layout?.borderTopRightRadius || '0.25rem') !important;
  border-bottom-left-radius: v-bind(bestSeller1Layout?.borderBottomLeftRadius || '0.25rem') !important;
  border-bottom-right-radius: v-bind(bestSeller1Layout?.borderBottomRightRadius || '0.25rem') !important;
}

.best-seller-2-container {
  background: v-bind(bestSeller2Layout?.background || 'white') !important;
  border-top: v-bind(bestSeller2Layout?.borderTop || '1px solid black') !important;
  border-bottom: v-bind(bestSeller2Layout?.borderBottom || '1px solid black') !important;
  border-left: v-bind(bestSeller2Layout?.borderLeft || '1px solid black') !important;
  border-right: v-bind(bestSeller2Layout?.borderRight || '1px solid black') !important;

  border-top-left-radius: v-bind(bestSeller2Layout?.borderTopLeftRadius || '0.25rem') !important;
  border-top-right-radius: v-bind(bestSeller2Layout?.borderTopRightRadius || '0.25rem') !important;
  border-bottom-left-radius: v-bind(bestSeller2Layout?.borderBottomLeftRadius || '0.25rem') !important;
  border-bottom-right-radius: v-bind(bestSeller2Layout?.borderBottomRightRadius || '0.25rem') !important;
}

.best-seller-3-container {
  background: v-bind(bestSeller3Layout?.background || 'white') !important;
  border-top: v-bind(bestSeller3Layout?.borderTop || '1px solid black') !important;
  border-bottom: v-bind(bestSeller3Layout?.borderBottom || '1px solid black') !important;
  border-left: v-bind(bestSeller3Layout?.borderLeft || '1px solid black') !important;
  border-right: v-bind(bestSeller3Layout?.borderRight || '1px solid black') !important;

  border-top-left-radius: v-bind(bestSeller3Layout?.borderTopLeftRadius || '0.25rem') !important;
  border-top-right-radius: v-bind(bestSeller3Layout?.borderTopRightRadius || '0.25rem') !important;
  border-bottom-left-radius: v-bind(bestSeller3Layout?.borderBottomLeftRadius || '0.25rem') !important;
  border-bottom-right-radius: v-bind(bestSeller3Layout?.borderBottomRightRadius || '0.25rem') !important;
}

</style>
