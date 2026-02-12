<script setup lang="ts">
import 'vue-color-kit/dist/vue-color-kit.css'
import { useBannerBackgroundColor } from "@/Composables/useStockList"
import { ref, watch, computed } from "vue"
import { faPaintBrushAlt, faText } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faPaintBrushAlt, faText)

const props = withDefaults(defineProps<{
    modelValue?: string | null
    screenView?: string
}>(), {
    screenView: 'desktop'
})

const emit = defineEmits([
    "update:modelValue",
])

const local = ref(props.modelValue)

watch(() => props.modelValue, v => {
    local.value = v
})

watch(local, v => {
    emit("update:modelValue", v)
})

const backgroundColorList = useBannerBackgroundColor()

const gradientList = computed(() =>
    backgroundColorList.filter(c => c.includes("linear-gradient"))
)
</script>

<template>
    <div class="flex gap-3">
        <div class="h-8 flex items-center w-fit gap-x-1.5">
            <div
                v-for="bg in gradientList"
                :key="bg"
                @click="local = bg"
                class="rounded h-full aspect-square shadow cursor-pointer"
                :class="local === bg
                    ? 'ring-2 ring-offset-2 ring-gray-600'
                    : 'hover:ring-2 hover:ring-gray-500'"
                :style="{ background: bg }"
            />
        </div>
    </div>
</template>

<style scoped>
.colors{
    display:none;
}
.hu-color-picker{
    position:absolute;
    left:0;
    bottom:0;
}
</style>
