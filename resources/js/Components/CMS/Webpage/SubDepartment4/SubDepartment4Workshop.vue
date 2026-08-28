<script setup lang="ts">
import { computed, inject } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@fal"
import Image from "@/Common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"

library.add(faImage)

type ScreenType = "mobile" | "tablet" | "desktop"

interface ResponsiveText {
  desktop?: string
  tablet?: string
  mobile?: string
  use_responsive?: boolean
}

interface SubDepartmentItem {
  code?: string
  slug?: string
  url?: string
  name?: string
  title?: string
  description?: string
  web_images?: {
    main?: {
      gallery?: string
    }
  }
  image?: any
}

const props = defineProps<{
  modelValue: {
    id?: string
    collections?: SubDepartmentItem[]
    sub_departments?: SubDepartmentItem[]
    container?: {
      properties?: Record<string, unknown>
    }
    card?: {
      container?: {
        properties?: Record<string, unknown>
      }
    }
    settings?: {
      per_row?: Partial<Record<ScreenType, number>>
    }
    text?: {
      value?: string | ResponsiveText
      visible?: boolean | null
    }
    button_label?: string
  }
  webpageData?: Record<string, unknown>
  blockData?: Record<string, unknown>
  screenType: ScreenType
  indexBlock?: number | string
}>()

const layout = inject<{ rightbasket?: { show?: boolean } }>("layout", {})

const fallbackPerRow: Record<ScreenType, number> = {
  desktop: 2,
  tablet: 2,
  mobile: 1,
}

const perRow = computed<number>(() => {
  const base = props.modelValue.settings?.per_row?.[props.screenType] ?? fallbackPerRow[props.screenType]

  if (layout.rightbasket?.show && props.screenType !== "mobile") {
    return 1
  }

  return base
})

const mergedItems = computed<SubDepartmentItem[]>(() => [
  ...(props.modelValue.sub_departments ?? []),
  ...(props.modelValue.collections ?? []),
])

const title = computed<string>(() => {
  const rawValue = props.modelValue.text?.value

  if (typeof rawValue === "string") {
    return rawValue
  }

  if (rawValue) {
    const responsiveValue = rawValue.use_responsive ? rawValue[props.screenType] : rawValue.desktop
    return responsiveValue ?? rawValue.desktop ?? ""
  }

  return `<h2 class="text-xl font-bold text-[#1d2d44] sm:text-2xl">${ctrans("Browse By Category")}:</h2>`
})

const isTitleVisible = computed<boolean>(() => props.modelValue.text?.visible !== false)

const buttonLabel = computed<string>(() => props.modelValue.button_label || ctrans("Browse Now"))

const getItemImage = (item: SubDepartmentItem) => item?.web_images?.main?.gallery || item?.image
</script>

<template>
  <div
    :id="'sub-department-4-' + (props.indexBlock ?? '')"
    component="sub-departments-4"
    class="editor-class mx-auto w-full max-w-[1700px] bg-white px-4 py-10 sm:px-8 lg:px-14 2xl:max-w-[1900px]"
    :style="getStyles(modelValue?.container?.properties, screenType)"
  >
    <div v-if="isTitleVisible" class="mb-10 text-center" v-html="title" />

    <div v-if="mergedItems.length" class="grid gap-x-10 gap-y-12" :style="{
      gridTemplateColumns: `repeat(${perRow}, minmax(0, 1fr))`,
    }">
      <div
        v-for="(item, index) in mergedItems"
        :key="item?.code || index"
        class="relative flex items-start gap-5"
        :style="getStyles(modelValue?.card?.container?.properties, screenType)"
      >
        <div class="group block w-[38%] max-w-[200px] shrink-0 sm:w-[180px]">
          <div class="relative aspect-square w-full overflow-hidden bg-gray-100">
            <div v-if="!getItemImage(item)" class="absolute inset-0 grid place-items-center">
              <FontAwesomeIcon icon="fal fa-image" class="text-4xl opacity-30" fixed-width aria-hidden="true" />
            </div>

            <Image
              v-else
              :src="getItemImage(item) as any"
              :alt="item?.name"
              class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
            />
          </div>
        </div>

        <div class="flex min-w-0 flex-1 flex-col items-start">
          <h3 class="text-base font-bold leading-snug text-[#1d2d44] sm:text-lg">
            {{ item?.title || item?.name }}
          </h3>

          <p v-if="item?.description" class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-500"
            v-html="item?.description" />

          <span class="browse-button mt-5 inline-block rounded px-6 py-2.5 text-xs font-medium uppercase tracking-wider">
            {{ buttonLabel }}
          </span>
        </div>
      </div>
    </div>

    <div v-else class="py-16 text-center text-base text-slate-500">
      {{ ctrans('No sub-departments found') }}
    </div>
  </div>
</template>

<style scoped>
.browse-button {
  border: 1px solid color-mix(in srgb, var(--theme-color-0, #1d2d44) 70%, black);
  color: color-mix(in srgb, var(--theme-color-0, #1d2d44) 70%, black);
  transition: all 0.3s ease;
}

.browse-button:hover {
  background: var(--theme-color-0, #1d2d44);
  border-color: var(--theme-color-0, #1d2d44);
  color: white;
}
</style>
