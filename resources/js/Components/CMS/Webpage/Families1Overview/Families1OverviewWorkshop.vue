<script setup lang="ts">
import { ref, computed, inject } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"

import Family1Render from "@/Iris/Components/Families1Render.vue"
import EmptyState from "@/Components/Utils/EmptyState.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"

import { getStyles } from "@/Composables/styles"
import { sendMessageToParent } from "@/Composables/Workshop"
import Blueprint from "./Blueprint"
import { trans } from "laravel-vue-i18n"
import { routeType } from "@/types/route"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronCircleLeft,
  faChevronCircleRight
)

const props = defineProps<{
  modelValue: {
    families: {
      id: number
      name: string
      description: string
      description_extra?: string
      description_title?: string
      image?: string
      images?: { source: string }[]
    }[]
    collections?: any[]
    container?: { properties?: any }
    settings?: {
      per_row?: {
        desktop?: number
        tablet?: number
        mobile?: number
      }
    }
  }
  routeEditfamily?: routeType
  webpageData?: any
  blockData?: object
  indexBlock?: number
  screenType: "mobile" | "tablet" | "desktop"
}>()


const layout: any = inject("layout", {})
const visibleDrawer = inject("visibleDrawer", undefined)

const sortKey = ref("created_at")


const families = computed(() => props.modelValue?.families || [])

const allItems = computed(() => [...families.value])

const responsiveGridClass = computed(() => {
  const perRow = props.modelValue?.settings?.per_row || {}

  const columnMap = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }

  return `grid-cols-${columnMap[props.screenType] || 1}`
})

const sortOptions = [
  { label: trans("New arrivals"), value: "created_at" },
  { label: trans("Code"), value: "code" },
  { label: trans("Name"), value: "name" },
]

const blueprintKeys =
  Blueprint?.blueprint?.map((b) => b?.key?.join("-")) || []


const activateBlock = () => {
  sendMessageToParent("activeBlock", props.indexBlock)
  sendMessageToParent("activeChildBlock", blueprintKeys[0])
}
</script>

<template>
  <div :id="modelValue?.id || 'families-1'+indexBlock">
    <div
      v-if="allItems.length"
      class="px-4 py-10 mx-[30px]"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, props.screenType),
        ...getStyles(props.modelValue?.container?.properties, props.screenType)
      }"
      @click="activateBlock"
    >
      <!-- Title -->
      <h2 class="text-2xl font-bold mb-6">
           <span v-html="modelValue?.title" />
      </h2>

      <!-- Sort -->
      <div class="flex justify-end my-8 space-x-6 overflow-x-auto">
        <button
          v-for="option in sortOptions"
          :key="option.value"
          class="pb-1 px-4 text-xs font-medium whitespace-nowrap flex items-center gap-1 border-b-2 sort-button"
          :class="[
            sortKey === option.value
              ? 'border-[var(--iris-color-0)] text-[var(--iris-color-0)]'
              : 'border-gray-300 text-gray-600 hover:text-[var(--iris-color-0)]'
          ]"
        >
          {{ option.label }}
        </button>
      </div>

      <!-- Grid -->
      <div :class="['grid gap-8', responsiveGridClass]">
        <div
          v-for="(item, index) in allItems"
          :key="item.id || `item-${index}`"
        >
          <Family1Render :data="item" />
        </div>
      </div>
    </div>

    <!-- Empty -->
    <EmptyState v-else :data="{ title: 'Empty Families' }">
      <template v-if="visibleDrawer !== undefined" #button-empty-state>
        <Button
          label="Select sub-department to preview family list"
          type="secondary"
        />
      </template>
    </EmptyState>
  </div>
</template>