<script setup lang="ts">
import { computed } from "vue"
import { useBannerBackgroundColor } from "@/Composables/useStockList"
import { routeType } from "@/types/route"

const props = defineProps<{
  modelValue: any
  fieldData: any
  bannerType: string
  uploadRoutes: routeType
}>()

const emit = defineEmits(["update:modelValue"])

const model = computed({
  get: () => props.modelValue,
  set: v => emit("update:modelValue", v)
})

const backgroundColorList = useBannerBackgroundColor()

const setColor = (color: string) => {
  model.value = color
}
</script>

<template>
  <div class="flex items-center gap-x-4 navigationSecondActiveCustomer pl-2">
    <div class="whitespace-nowrap">Or a color:</div>

    <div class="h-8 flex items-center w-fit gap-x-1.5">
      <div
        v-for="bgColor in backgroundColorList"
        :key="bgColor"
        class="rounded h-full aspect-square shadow cursor-pointer"
        :style="{ background: bgColor }"
        :class="model === bgColor ? 'ring-2 ring-offset-2 ring-gray-600' : 'hover:ring-2 hover:ring-gray-400'"
        @click="setColor(bgColor)"
      />
    </div>
  </div>
</template>
