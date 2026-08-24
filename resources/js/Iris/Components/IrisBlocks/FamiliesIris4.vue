<script setup lang="ts">
import Image from '@/Common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import axios from 'axios'
import { ref, watch, computed, inject, onMounted, onBeforeUnmount } from 'vue'
import LoadingText from "@/Components/Utils/LoadingText.vue";
import { ctrans } from "@/Composables/useTrans";
import { getStyles } from "@/Composables/styles"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import MobileShowMoreButton from '@/Iris/Components/MobileShowMoreButton.vue'
import { useDepartmentStructuredData } from "@/Iris/Composables/useDepartmentStructuredData"

interface FilterOptions {
	name: string
	code: string
    url: string
}


const props = defineProps<{
  fieldValue: FieldValue
  webpageData?: Record<string, unknown>
  blockData?: Record<string, unknown>
  screenType: ScreenType
  indexBlock: number
}>()


const loading = ref(false)
const loadingMore = ref(false)
const layout: any = inject("layout", {})
const injectedWebpageData = inject<any>("webpage_data", null)

const selectedOption = ref<string | null>(null)
const sortKey = ref('created_at')
const families = ref(
  Array.isArray(props.fieldValue?.families)
    ? props.fieldValue.families
    : (props.fieldValue?.families?.data ?? [])
)
const isAscending = ref(true)
const orderBy = ref('-created_at')

const sortOptions = computed(() => {
  const baseOptions = [
    { label: ctrans("New arrivals"), value: "created_at" },
    { label: ctrans("Name"), value: "name" },
  ]
  return baseOptions
})

const meta = ref(
  (Array.isArray(props.fieldValue?.families) ? null : props.fieldValue?.families?.meta) ?? {
    current_page: 1,
    from: 0,
    last_page: 1,
    links: [],
    path: '',
    per_page: 500,
    to: 0,
    total: families.value.length,
  }
)

const productCategorySlug = computed(() => props.fieldValue?.product_category?.slug)

const filterOptions = computed(() => props.fieldValue?.filter_options ?? [])

const loadFamilies = async (
  page = 1,
  append = false,
) => {
  if (!productCategorySlug.value) {
    return
  }

  try {
    if (append) {
      loadingMore.value = true
    } else {
      loading.value = true
    }

    const isSubDepartment = props.fieldValue.sub_department_list?.some((item) => item.code === selectedOption.value);
    const filter: Record<string, string> = {[isSubDepartment ? 'category' : 'collection']: selectedOption.value};

    const response = await axios.get(
      route(
        'iris.json.website.category.family_under_department',
        {
          productCategory: productCategorySlug.value,
        }
      ),
      {
        params: {
          filter,
          sort: orderBy.value,
          page,
          per_page : 250
        },
      }
    );

    if (append) {
      families.value = [
        ...families.value,
        ...response.data.data,
      ]
    } else {
      families.value = response.data.data
    }

    meta.value = response.data.meta
  } catch (error) {
    console.error(
      'Failed loading families:',
      error
    )
  } finally {
    loading.value = false
    loadingMore.value = false
  }
}

watch(selectedOption, () => {
  loadFamilies(1)
})

const perRow = computed(() => ({
  mobile: props.fieldValue?.settings?.per_row?.mobile ?? 2,
  tablet: props.fieldValue?.settings?.per_row?.tablet ?? 3,
  desktop: props.fieldValue?.settings?.per_row?.desktop ?? 5,
}))

const familyImageSizes = computed(() =>
  `(max-width: 639px) ${Math.round(100 / perRow.value.mobile)}vw, (max-width: 1023px) ${Math.round(100 / perRow.value.tablet)}vw, ${Math.round(100 / perRow.value.desktop)}vw`
)

const MOBILE_INITIAL_FAMILIES = 12
const showAllFamiliesOnMobile = ref(false)
const mobileHiddenFamiliesCount = computed(() => families.value.length - MOBILE_INITIAL_FAMILIES)
const isMobileCollapsed = computed(() => !showAllFamiliesOnMobile.value && mobileHiddenFamiliesCount.value > 4)

const updateQueryParams = () => {
    const url = new URL(window.location.href)

    if (orderBy.value) {
        url.searchParams.set("order_by", orderBy.value)
    } else {
        url.searchParams.delete("order_by")
    }


    window.history.replaceState({}, "", url.toString())
}


const toggleSort = (key: string) => {
    if (sortKey.value === key) {
        isAscending.value = !isAscending.value
    } else {
        sortKey.value = key
        isAscending.value = true
    }
    orderBy.value = isAscending.value ? key : `-${key}`
    updateQueryParams()
    loadFamilies(1)
}

const getArrow = (key: typeof sortKey.value) => {
  if (sortKey.value !== key) return ""
  return isAscending.value ? "↑" : "↓"
}

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search)
    const sortParam = urlParams.get("order_by")
    if (sortParam) {
        orderBy.value = sortParam
        const key = sortParam.replace("-", "")
        sortKey.value = key as typeof sortKey.value
        isAscending.value = !sortParam.startsWith("-")
    }
})


// Section: Department structured data (SEO)
// Mounted independently here instead of inside the page structured data (useStructuredData),
// so the sub-departments + collections ItemList lives in its own <script> and is easier to maintain.
const { mountDepartmentStructuredData, removeStructuredDataScript } = useDepartmentStructuredData()
const departmentStructuredDataScript = ref<HTMLScriptElement | null>(null)

onMounted(() => {
  departmentStructuredDataScript.value = mountDepartmentStructuredData({
    subDepartments: props.fieldValue.sub_department_list,
    collections: props.fieldValue.collections_list,
    webpageData: (props.webpageData ?? injectedWebpageData) as any,
    listId: props.fieldValue.id ?? props.indexBlock,
  })
})

onBeforeUnmount(() => {
  removeStructuredDataScript(departmentStructuredDataScript.value)
})

</script>

<template>
  <section :id="'families-iris-4-' + (props.indexBlock ?? '')"
    class="editor-class pt-12 mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 lg:px-14 2xl:max-w-[1900px] 2xl:px-14"
    :style="{
      ...getStyles(layout?.app?.webpage_layout?.container?.properties, screenType),
      ...getStyles(fieldValue.container?.properties, screenType),
    }">

    <div class="mb-8">
      <h2 class="text-2xl font-bold text-[#1d2d44] sm:text-[2rem] sm:leading-tight">
        {{ ctrans('All') }} {{ fieldValue.product_category_title || ctrans('Families') }}:
      </h2>

      <div class="mt-4 flex flex-wrap items-center gap-x-10 gap-y-4">
        <div class="text-base text-slate-600 sm:text-lg">
          <span class="font-semibold text-[#1d2d44]">{{ meta.total }}</span>
          {{ ctrans('Products Found') }}
        </div>

        <div v-if="filterOptions.length" class="flex items-center gap-3">
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

        <div class="hidden lg:flex ml-auto gap-6">
          <button v-for="option in sortOptions" :key="option.value" @click="toggleSort(option.value)"
            class="sort-button flex items-center gap-1 whitespace-nowrap border-b-2 px-2 pb-1 text-sm font-medium"
            :class="[
              sortKey === option.value
                ? `border-[var(--iris-color-0)] text-[var(--iris-color-0)]`
                : `border-transparent text-slate-500 hover:text-[var(--iris-color-0)]`
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

    <div v-else class="grid gap-x-6 gap-y-8 families-grid" :style="{
      '--cols-mobile': perRow.mobile,
      '--cols-tablet': perRow.tablet,
      '--cols-desktop': perRow.desktop,
    }">
      <LinkIris v-for="(family, familyIndex) in families" :key="family.id" :href="family.url" type="internal"
        class="family-card group block"
        :class="{ 'max-lg:hidden': isMobileCollapsed && familyIndex >= MOBILE_INITIAL_FAMILIES }">
        <div class="relative aspect-square overflow-hidden bg-white">
          <div v-if="!family.image" class="absolute inset-0 grid place-items-center">
            <FontAwesomeIcon icon="fal fa-image" class="text-5xl opacity-30" fixed-width aria-hidden="true" />
          </div>
          <Image :src="family.image" :alt="family.name"
            :srcset="family.srcset"
            :sizes="familyImageSizes"
            class="h-full w-full object-contain transition duration-300 group-hover:scale-105"
            :class="!family.image ? 'opacity-0' : ''" />
        </div>

        <div class="family-label mt-3  flex min-h-[54px] items-center rounded-sm px-3 py-2">
          <span class="line-clamp-2 text-sm font-medium leading-snug text-white">
            {{ family.name }}
          </span>
        </div>
      </LinkIris>
    </div>

    <div v-if="isMobileCollapsed" class="lg:hidden mt-4 mb-7 px-2">
      <MobileShowMoreButton :count="mobileHiddenFamiliesCount" @show="showAllFamiliesOnMobile = true" />
    </div>

    <div v-if="!loading && meta.current_page < meta.last_page" class="mt-12 flex justify-center"
      :class="{ 'max-lg:hidden': isMobileCollapsed }">
      <button type="button" class="load-more-button rounded-sm px-14 py-3.5 text-sm font-semibold uppercase tracking-widest"
        :disabled="loadingMore" @click="loadFamilies(meta.current_page + 1, true)">
        <LoadingText v-if="loadingMore" />
        <template v-else>{{ ctrans('Load More') }}</template>
      </button>
    </div>
  </section>
</template>

<style scoped>
.families-grid {
  grid-template-columns: repeat(var(--cols-mobile), minmax(0, 1fr));
}

@media (min-width: 640px) {
  .families-grid {
    grid-template-columns: repeat(var(--cols-tablet), minmax(0, 1fr));
  }
}

@media (min-width: 1024px) {
  .families-grid {
    grid-template-columns: repeat(var(--cols-desktop), minmax(0, 1fr));
  }
}

.category-select {
  border: 1px solid color-mix(in srgb, var(--iris-color-0, #1d2d44) 45%, transparent);
  color: color-mix(in srgb, var(--iris-color-0, #1d2d44) 85%, black);
}

.category-select:focus {
  outline: none;
  border-color: var(--iris-color-0, #1d2d44);
  box-shadow: 0 0 0 2px color-mix(in srgb, var(--iris-color-0, #1d2d44) 20%, transparent);
}

.family-label {
  background: color-mix(in srgb, var(--iris-color-0, #1d2d44) 55%, white);
  transition: background-color 0.3s ease;
}

.family-card:hover .family-label {
  background: var(--iris-color-0, #1d2d44);
}

.load-more-button {
  border: 1px solid color-mix(in srgb, var(--iris-color-0, #1d2d44) 70%, black);
  color: color-mix(in srgb, var(--iris-color-0, #1d2d44) 70%, black);
  transition: all 0.3s ease;
}

.load-more-button:hover:not(:disabled) {
  background: var(--iris-color-0, #1d2d44);
  border-color: var(--iris-color-0, #1d2d44);
  color: white;
}

.load-more-button:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
