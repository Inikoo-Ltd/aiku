<script setup lang="ts">
import { ref, computed, inject, watch } from "vue"
import axios from "axios"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@fal"
import Image from "@/Common/Components/Image.vue"
import LoadingText from "@/Components/Utils/LoadingText.vue"
import { ctrans } from "@/Composables/useTrans"
import { getStyles } from "@/Composables/styles"

library.add(faImage)

interface FilterOption {
  name: string
  code: string
  url?: string
}

interface Family {
  id: number
  name: string
  image: any
  srcset?: string
  url: string
}

const props = defineProps<{
  modelValue: {
    id?: string
    product_category_title?: string
    department?: { slug?: string; name?: string }
    families?: Family[] | { data: Family[]; meta?: Record<string, any> }
    filter_options?: FilterOption[]
    sub_department_list?: FilterOption[]
    collections_list?: FilterOption[]
    settings?: { per_row?: { desktop?: number; tablet?: number; mobile?: number } }
    container?: { properties?: Record<string, unknown> }
  }
  webpageData?: Record<string, unknown>
  blockData?: Record<string, unknown>
  screenType: "mobile" | "tablet" | "desktop"
  indexBlock?: number | string
}>()

const layout: any = inject("layout", {})

const loading = ref(false)
const loadingMore = ref(false)
const selectedOption = ref<string | null>(null)
const sortKey = ref("created_at")
const isAscending = ref(true)
const orderBy = ref("-created_at")

const initialFamilies = computed<Family[]>(() =>
  Array.isArray(props.modelValue?.families)
    ? props.modelValue.families
    : (props.modelValue?.families?.data ?? [])
)

const families = ref<Family[]>(initialFamilies.value)

const filterOptions = computed<FilterOption[]>(
  () => props.modelValue?.filter_options
    ?? [...(props.modelValue?.sub_department_list ?? []), ...(props.modelValue?.collections_list ?? [])]
)

const sortOptions = computed(() => [
  { label: ctrans("New arrivals"), value: "created_at" },
  { label: ctrans("Name"), value: "name" },
])

const meta = ref(
  (Array.isArray(props.modelValue?.families) ? null : props.modelValue?.families?.meta) ?? {
    current_page: 1,
    from: 0,
    last_page: 1,
    links: [],
    path: "",
    per_page: 250,
    to: 0,
    total: families.value.length,
  }
)

const loadFamilies = async (page = 1, append = false) => {
  if (!props.modelValue?.department?.slug) {
    return
  }

  try {
    if (append) {
      loadingMore.value = true
    } else {
      loading.value = true
    }

    const isSubDepartment = props.modelValue.sub_department_list?.some(
      (item) => item.code === selectedOption.value
    )
    const filter: Record<string, string | null> = {
      [isSubDepartment ? "category" : "collection"]: selectedOption.value,
    }

    const response = await axios.get(
      route("grp.json.website.category.family_under_department", {
        productCategory: props.modelValue.department.slug,
      }),
      {
        params: {
          filter,
          sort: orderBy.value,
          page,
          per_page: 250,
        },
      }
    )

    families.value = append ? [...families.value, ...response.data.data] : response.data.data
    meta.value = response.data.meta
  } catch (error) {
    console.error("Failed loading families:", error)
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

watch(initialFamilies, (newFamilies) => {
  families.value = newFamilies
})

watch(selectedOption, () => {
  loadFamilies(1)
})

const toggleSort = (key: string) => {
  if (sortKey.value === key) {
    isAscending.value = !isAscending.value
  } else {
    sortKey.value = key
    isAscending.value = true
  }
  orderBy.value = isAscending.value ? key : `-${key}`
  loadFamilies(1)
}

const getArrow = (key: string) => {
  if (sortKey.value !== key) {
    return ""
  }
  return isAscending.value ? "↑" : "↓"
}

const perRow = computed(() => {
  const settings = props.modelValue?.settings?.per_row

  if (props.screenType === "mobile") {
    return settings?.mobile ?? 2
  }

  if (props.screenType === "tablet") {
    return settings?.tablet ?? 3
  }

  return settings?.desktop ?? 5
})
</script>

<template>
  <section :id="'families-4-' + (props.indexBlock ?? '')"
    component="families-4"
    class="editor-class pt-12 mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 lg:px-14 2xl:max-w-[1900px] 2xl:px-14"
    :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(modelValue.container?.properties, screenType),
    }">

    <div class="mb-8">
      <h2 class="text-2xl font-bold text-[#1d2d44] sm:text-[2rem] sm:leading-tight">
        {{ ctrans('All') }}
        {{ modelValue?.product_category_title || modelValue?.department?.name || ctrans('Families') }}:
      </h2>

      <div class="mt-4 flex flex-wrap items-center gap-x-10 gap-y-4">
        <div class="text-base text-slate-600 sm:text-lg">
          <span class="font-semibold text-[#1d2d44]">{{ meta.total }}</span>
          {{ ctrans('Products Found') }}
        </div>

        <div class="flex items-center gap-3">
          <span class="text-base text-slate-600 sm:text-lg">
            {{ ctrans('Filter By Category') }}:
          </span>

          <select v-model="selectedOption" :aria-label="ctrans('Filter By Category')"
            class="category-select h-10 min-w-[140px] max-w-[220px] rounded-md bg-white px-4 text-center text-base">
            <option :value="null">
              {{ ctrans('All') }}
            </option>

            <option v-for="option in filterOptions" :key="option.code" :value="option.code">
              {{ option.name }}
            </option>
          </select>
        </div>

        <div v-if="screenType === 'desktop'" class="ml-auto flex gap-6">
          <button v-for="option in sortOptions" :key="option.value" @click="toggleSort(option.value)"
            class="flex items-center gap-1 whitespace-nowrap border-b-2 px-2 pb-1 text-sm font-medium"
            :class="[
              sortKey === option.value
                ? `border-[var(--theme-color-0)] text-[var(--theme-color-0)]`
                : `border-transparent text-slate-500 hover:text-[var(--theme-color-0)]`
            ]">
            {{ option.label }} {{ getArrow(option.value) }}
          </button>
        </div>
      </div>
    </div>

    <div v-if="loading" class="py-16 text-center text-base text-slate-500">
      {{ ctrans('Loading...') }}
    </div>

    <div v-else-if="families.length === 0" class="py-16 text-center text-base text-slate-500">
      {{ ctrans('No families found') }}
    </div>

    <div v-else class="grid gap-x-6 gap-y-8" :style="{
      gridTemplateColumns: `repeat(${perRow}, minmax(0, 1fr))`,
    }">
      <div v-for="family in families" :key="family.id" class="family-card group block">
        <div class="relative aspect-square overflow-hidden bg-white">
          <div v-if="!family.image" class="absolute inset-0 grid place-items-center">
            <FontAwesomeIcon icon="fal fa-image" class="text-5xl opacity-30" fixed-width aria-hidden="true" />
          </div>
          <Image :src="family.image" :alt="family.name"
            class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
            :class="!family.image ? 'opacity-0' : ''" />
        </div>

        <div class="family-label flex min-h-[54px] items-center rounded-sm px-3 py-2">
          <span class="line-clamp-2 text-sm font-medium leading-snug text-white">
            {{ family.name }}
          </span>
        </div>
      </div>
    </div>

    <div v-if="!loading && meta.current_page < meta.last_page" class="mt-12 flex justify-center">
      <button type="button"
        class="load-more-button rounded-sm px-14 py-3.5 text-sm font-semibold uppercase tracking-widest"
        :disabled="loadingMore" @click="loadFamilies(meta.current_page + 1, true)">
        <LoadingText v-if="loadingMore" />
        <template v-else>{{ ctrans('Load More') }}</template>
      </button>
    </div>
  </section>
</template>

<style scoped>
.category-select {
  border: 1px solid color-mix(in srgb, var(--theme-color-0, #1d2d44) 45%, transparent);
  color: color-mix(in srgb, var(--theme-color-0, #1d2d44) 85%, black);
}

.category-select:focus {
  outline: none;
  border-color: var(--theme-color-0, #1d2d44);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--theme-color-0, #1d2d44) 20%, transparent);
}

.family-label {
  background: color-mix(in srgb, var(--theme-color-0, #1d2d44) 55%, white);
  transition: background-color 0.3s ease;
}

.family-card:hover .family-label {
  background: var(--theme-color-0, #1d2d44);
}

.load-more-button {
  border: 1px solid color-mix(in srgb, var(--theme-color-0, #1d2d44) 70%, black);
  color: color-mix(in srgb, var(--theme-color-0, #1d2d44) 70%, black);
  transition: all 0.3s ease;
}

.load-more-button:hover:not(:disabled) {
  background: var(--theme-color-0, #1d2d44);
  border-color: var(--theme-color-0, #1d2d44);
  color: white;
}

.load-more-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
