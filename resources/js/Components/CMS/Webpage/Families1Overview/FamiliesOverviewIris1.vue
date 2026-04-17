<script setup lang="ts">
import { computed, inject, ref, onMounted } from "vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCube, faLink } from "@fal"
import { faStar, faCircle } from "@fas"
import { faChevronCircleLeft, faChevronCircleRight } from "@far"
import axios from "axios"

import { trans } from "laravel-vue-i18n"
import { getStyles } from "@/Composables/styles"

import Family1Render from "./Families1Render.vue"
import LinkIris from "@/Components/Iris/LinkIris.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"

library.add(
  faCube,
  faLink,
  faStar,
  faCircle,
  faChevronCircleLeft,
  faChevronCircleRight
)

type FamilyOrCollectionType = {
  name: string
  description: string
  images: { source: string }[]
  url?: string
}

const props = defineProps<{
  fieldValue: {
    families: FamilyOrCollectionType[]
    collections: FamilyOrCollectionType[]
    settings?: {
      per_row?: {
        desktop?: number
        tablet?: number
        mobile?: number
      }
    }
    container?: any
    id?: string
    title?: string
    webpage_slug?: string
    parent?: { slug?: string }
  }
  webpageData?: any
  blockData?: Record<string, any>
  screenType: "mobile" | "tablet" | "desktop"
}>()

const layout: any = inject("layout", {})


const families = ref<FamilyOrCollectionType[]>(props.fieldValue?.families || [])

const idxLoading = ref<string | null>(null)
const isLoadingInitial = ref(false)
const isLoadingMore = ref(false)


const sortKey = ref("created_at")
const isAscending = ref(true)
const orderBy = ref("created_at")


onMounted(() => {
  const params = new URLSearchParams(window.location.search)
  const order = params.get("sort")

  if (order) {
    if (order.startsWith("-")) {
      sortKey.value = order.substring(1)
      isAscending.value = false
    } else {
      sortKey.value = order
      isAscending.value = true
    }
    orderBy.value = order
  }

  fetchFamilies()
})


const responsiveGridClass = computed(() => {
  const perRow = props.fieldValue?.settings?.per_row || {}

  const columnMap = {
    desktop: perRow.desktop ?? 4,
    tablet: perRow.tablet ?? 4,
    mobile: perRow.mobile ?? 2,
  }

  return `grid-cols-${columnMap[props.screenType] || 1}`
})

const sortOptions = [
  { label: trans("New arrivals"), value: "product_categories.created_at" },
  { label: trans("Code"), value: "code" },
  { label: trans("Name"), value: "name" },
]


const fetchFamilies = async () => {
  try {
    isLoadingInitial.value = true

    const { data } = await axios.get(
      route("iris.json.website.category.family_list_sorted", {
        webpage: props.fieldValue?.webpage_slug,
        productCategory: props.fieldValue?.parent?.slug,
      }),
      {
        params: {
          sort: orderBy.value,
        },
      }
    )

    families.value = data.data
  } catch (error) {
    console.error("Failed to fetch families:", error)
  } finally {
    isLoadingInitial.value = false
  }
}


const updateQueryParams = () => {
  const url = new URL(window.location.href)

  if (orderBy.value) {
    url.searchParams.set("sort", orderBy.value)
  } else {
    url.searchParams.delete("sort")
  }

  window.history.replaceState({}, "", url.toString())
}


const toggleSort = async (key: string) => {
  if (sortKey.value === key) {
    isAscending.value = !isAscending.value
  } else {
    sortKey.value = key
    isAscending.value = true
  }

  orderBy.value = isAscending.value ? key : `-${key}`

  updateQueryParams()
  await fetchFamilies()
}

const getArrow = (key: string) => {
  if (sortKey.value !== key) return ""
  return isAscending.value ? "↑" : "↓"
}
</script>

<template>
  <div :id="fieldValue?.id || 'families-1'">
    <div
      v-if="families.length"
      class="py-10"
      :style="{
        ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
        ...getStyles(fieldValue?.container?.properties, screenType)
      }"
    >
      <!-- Title -->
      <h2 class="text-2xl font-bold mb-6">
        <span v-html="fieldValue?.title" />
      </h2>

      <!-- Sort -->
      <div class="flex justify-end my-4 space-x-6 overflow-x-auto">
        <button
          v-for="option in sortOptions"
          :key="option.value"
          @click="toggleSort(option.value)"
          class="pb-1 px-4 text-xs font-medium flex items-center gap-1 border-b-2"
          :class="[
            sortKey === option.value
              ? 'border-[var(--iris-color-0)] text-[var(--iris-color-0)]'
              : 'border-gray-300 text-gray-600 hover:text-[var(--iris-color-0)]'
          ]"
          :disabled="isLoadingInitial"
        >
          {{ option.label }} {{ getArrow(option.value) }}
        </button>
      </div>

      <!-- Grid -->
      <div :class="['grid gap-8', responsiveGridClass]">
        <LinkIris
          v-for="(item, index) in families"
          :key="index"
          :href="item.url"
          type="internal"
          class="relative"
          @start="() => (idxLoading = `family${index}`)"
          @finish="() => (idxLoading = null)"
        >
          <template #default>
            <Family1Render :data="item" />

            <div
              v-if="idxLoading === `family${index}`"
              class="absolute inset-0 grid place-items-center bg-black/50 text-white text-5xl rounded"
            >
              <LoadingIcon />
            </div>
          </template>
        </LinkIris>
      </div>
    </div>
  </div>
</template>