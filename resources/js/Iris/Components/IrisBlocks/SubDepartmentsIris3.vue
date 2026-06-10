<script setup lang="ts">
import Image from '@/Common/Components/Image.vue'
import LinkIris from '@/Iris/Components/LinkIris.vue'
import axios from 'axios'
import { ref, watch } from 'vue'
import LoadingText from "@/Components/Utils/LoadingText.vue";
import Button from '@/Components/Elements/Buttons/Button.vue';
import { ctrans } from "@/Composables/useTrans";

const props = defineProps<{
  fieldValue: {
    department?: {
      slug: string
    }
    families: {
      data: Array<any>
      meta?: {
        current_page: number
        from: number
        last_page: number
        links: Array<any>
        path: string
        per_page: number
        to: number
        total: number
      }
    }
    sub_department_list: Array<any>
  }
  webpageData?: any
  blockData?: object
  screenType: 'mobile' | 'tablet' | 'desktop'
}>()

const loading = ref(false)
const loadingMore = ref(false)

const selectedSubDepartment = ref<number | null>(null)

const families = ref(props.fieldValue?.families?.data ?? [])

const meta = ref(
  props.fieldValue?.families?.meta ?? {
    current_page: 1,
    from: 0,
    last_page: 1,
    links: [],
    path: '',
    per_page: 50,
    to: 0,
    total: families.value.length,
  }
)

const loadFamilies = async (
  page = 1,
  append = false,
) => {
  try {
    if (append) {
      loadingMore.value = true
    } else {
      loading.value = true
    }

    const response = await axios.get(
      route(
        'iris.json.website.category.family_under_department',
        {
          productCategory:
            props.fieldValue.department?.slug,
        }
      ),
      {
        params: {
          filter: {
            category:
              selectedSubDepartment.value,
          },
          page,
        },
      }
    )

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

watch(selectedSubDepartment, () => {
  loadFamilies(1)
})
</script>

<template>
  <section
    class="editor-class mx-auto w-full max-w-[1700px] bg-white px-4 py-4 sm:px-8 lg:px-14 2xl:max-w-[1900px] 2xl:px-14">
    <!-- Header -->
    <div class="mb-10">
      <h3 class="mb-4 text-4xl font-bold text-slate-900">
        {{ ctrans('All Family') }} :
      </h3>

      <!-- Mobile -->
      <div class="flex items-center justify-between gap-4 lg:hidden">
        <div class="text-2xl text-slate-700">
          {{ meta.total }}
          {{ ctrans('products Found') }}
        </div>

        <select v-model.number="selectedSubDepartment"
          class="h-[58px] w-[170px] rounded-[18px] border border-[#B8B8B8] bg-white px-4 text-center text-xl text-slate-800 shadow-[0_4px_0_0_rgba(0,0,0,0.15)]">
          <option :value="null">
            {{ ctrans('All') }}
          </option>

          <option v-for="department in fieldValue.sub_department_list" :key="department.code" :value="department.code">
            {{ department.name }}
          </option>
        </select>
      </div>

      <!-- Desktop -->
      <div class="hidden lg:flex lg:flex-row lg:items-center lg:gap-20">
        <div class="text-2xl text-slate-700">
          {{ meta.total }}
          {{ ctrans('products Found') }}
        </div>

        <div class="flex items-center gap-4">
          <span class="text-xl text-slate-700">
            {{ ctrans('Filter By Category') }} :
          </span>

          <select v-model.number="selectedSubDepartment"
            class="h-11 min-w-[180px] rounded-md border border-slate-400 bg-white px-4">
            <option :value="null">
              {{ ctrans('All') }}
            </option>

            <option v-for="department in fieldValue.sub_department_list" :key="department.code"
              :value="department.code">
              {{ department.name }}
            </option>
          </select>
        </div>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="py-16 text-center text-base text-slate-500">
      {{ ctrans('Loading...') }}
    </div>

    <!-- Empty State -->
    <div v-else-if="families.length === 0" class="py-16 text-center text-base text-slate-500">
      {{ ctrans('No families found') }}
    </div>

    <!-- Family Grid -->
    <div v-else class="grid grid-cols-2 gap-5 md:grid-cols-3 lg:grid-cols-5">
      <LinkIris v-for="family in families" :key="family.id" :href="family.url" class="group block" type="internal">
        <div class="overflow-hidden bg-gray-100">
          <Image :src="family.image" :alt="family.name"
            class="aspect-square w-full object-cover transition duration-300 group-hover:scale-105" />
        </div>

        <span class="mt-2 line-clamp-2 text-lg leading-snug text-slate-900 font-semibold">
          {{ family.name }}
        </span>
      </LinkIris>
    </div>

    <!-- Load More -->
    <div v-if="!loading && meta.current_page < meta.last_page " class="mt-8 flex justify-center">
      <Button @click="loadFamilies(meta.current_page + 1,true )" type="tertiary"  :disabled="loadingMore" :injectStyle="{ padding: '14px 65px', fontSize: '1.2rem' }">
        <template v-if="loadingMore">
          <LoadingText />
        </template>
        <template v-else>{{ "Load More"}}</template>
      </Button>
    </div>
  </section>
</template>