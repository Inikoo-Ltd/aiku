<script setup lang="ts">
import { computed, inject, ref, onMounted, onBeforeUnmount } from "vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@fal"
import Image from "@common/Components/Image.vue"
import LinkIris from "@/Iris/Components/LinkIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { getStyles } from "@/Composables/styles"
import { ctrans } from "@/Composables/useTrans"
import { useDepartmentStructuredData } from "@/Iris/Composables/useDepartmentStructuredData"

library.add(faImage)

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

type ScreenType = "mobile" | "tablet" | "desktop"

const props = defineProps<{
  fieldValue: {
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
const injectedWebpageData = inject<any>("webpage_data", null)

const fallbackPerRow: Record<ScreenType, number> = {
  desktop: 2,
  tablet: 2,
  mobile: 1,
}

const perRow = computed<number>(() => {
  const base =
    props.fieldValue.settings?.per_row?.[props.screenType] ??
    fallbackPerRow[props.screenType]

  if (layout.rightbasket?.show && props.screenType !== "mobile") {
    return 1
  }

  return base
})

const mergedItems = computed<SubDepartmentItem[]>(() => [
  ...(props.fieldValue.sub_departments ?? []),
  ...(props.fieldValue.collections ?? []),
])

const title = computed<string>(() => {
  const rawValue = props.fieldValue.text?.value

  if (typeof rawValue === "string") {
    return rawValue
  }

  if (rawValue) {
    const responsiveValue = rawValue.use_responsive ? rawValue[props.screenType] : rawValue.desktop
    return responsiveValue ?? rawValue.desktop ?? ""
  }

  return `<h2 class="text-xl font-bold text-[#1d2d44] sm:text-2xl">${ctrans("Browse By Category")}:</h2>`
})

const isTitleVisible = computed<boolean>(() => props.fieldValue.text?.visible !== false)

const buttonLabel = computed<string>(() => props.fieldValue.button_label || ctrans("Browse Now"))

const itemImageSizes = computed(
  () => `(max-width: 639px) 40vw, (max-width: 1023px) ${Math.round(50 / perRow.value)}vw, ${Math.round(30 / perRow.value)}vw`
)

const getItemImage = (item: SubDepartmentItem) => item?.web_images?.main?.gallery || item?.image

const hasDescription = (item: SubDepartmentItem) =>
  Boolean(item?.description?.replace(/<[^>]*>/g, "").replace(/&nbsp;/g, " ").trim())

const idxLoading = ref<number | null>(null)

const { mountDepartmentStructuredData, removeStructuredDataScript } = useDepartmentStructuredData()
const departmentStructuredDataScript = ref<HTMLScriptElement | null>(null)

onMounted(() => {
  departmentStructuredDataScript.value = mountDepartmentStructuredData({
    subDepartments: props.fieldValue.sub_departments,
    collections: props.fieldValue.collections,
    webpageData: (props.webpageData ?? injectedWebpageData) as any,
    listId: props.fieldValue.id ?? props.indexBlock,
  })
})

onBeforeUnmount(() => {
  removeStructuredDataScript(departmentStructuredDataScript.value)
})
console.log('SubDepartmentsIris4.vue mounted with props:', props)
</script>

<template>
  <div
    v-if="mergedItems.length"
    :id="'sub-department-iris-4-' + (props.indexBlock ?? '')"
    component="sub-department-4"
    class="editor-class mx-auto w-full max-w-[1700px] bg-white px-4 py-10 sm:px-8 lg:px-14 2xl:max-w-[1900px]"
    :style="getStyles(fieldValue?.container?.properties, screenType)"
  >
    <div v-if="isTitleVisible" class="mb-10 text-center" v-html="title" />

    <div class="grid gap-x-10 gap-y-12 sub-departments-4-grid" :style="{ '--cols': perRow }">
      <div
        v-for="(item, index) in mergedItems"
        :key="item?.code || index"
        class="relative flex items-start gap-5"
        :style="getStyles(fieldValue?.card?.container?.properties, screenType)"
      >
        <LinkIris
          :href="`${item?.url}`"
          type="internal"
          :aria-label="`Go to ${item?.name}`"
          class="group block w-[38%] max-w-[200px] shrink-0 sm:w-[180px]"
          @start="() => idxLoading = index"
          @finish="() => idxLoading = null"
        >
          <div class="relative aspect-square w-full overflow-hidden bg-gray-100">
            <div v-if="!getItemImage(item)" class="absolute inset-0 grid place-items-center">
              <FontAwesomeIcon icon="fal fa-image" class="text-4xl opacity-30" fixed-width aria-hidden="true" />
            </div>

            <Image
              v-else
              :src="getItemImage(item) as any"
              :alt="item?.name"
              :sizes="itemImageSizes"
              class="h-full w-full object-cover transition duration-300 group-hover:scale-105"
            />
          </div>
        </LinkIris>

        <div class="flex min-w-0 flex-1 flex-col items-start">
          <LinkIris
            :href="`${item?.url}`"
            type="internal"
            class="block"
            @start="() => idxLoading = index"
            @finish="() => idxLoading = null"
          >
            <h3 class="text-base font-bold leading-snug text-[#1d2d44] hover:underline sm:text-lg">
              {{ item?.title || item?.name }}
            </h3>
          </LinkIris>

          <p
            v-if="hasDescription(item)"
            class="mt-3 line-clamp-3 text-sm leading-relaxed text-slate-500"
            v-html="item?.description"
          />

          <LinkIris
            :href="`${item?.url}`"
            type="internal"
            :aria-label="`${buttonLabel} ${item?.name}`"
            class="mt-5 inline-block border border-[#1d2d44] px-6 rounded py-2.5 text-xs font-medium uppercase tracking-wider text-[#1d2d44] transition-colors hover:border-[var(--theme-color-4,#1d2d44)] hover:bg-[var(--theme-color-4,#1d2d44)] hover:text-[var(--theme-color-5,#ffffff)]"
            @start="() => idxLoading = index"
            @finish="() => idxLoading = null"
          >
            {{ buttonLabel }}
          </LinkIris>
        </div>

        <div v-if="idxLoading === index" class="absolute inset-0 grid place-items-center bg-black/40 text-white">
          <LoadingIcon />
        </div>
      </div>
    </div>
  </div>

  <div v-else></div>
</template>

<style scoped>
.sub-departments-4-grid {
  grid-template-columns: repeat(var(--cols), minmax(0, 1fr));
}
</style>
