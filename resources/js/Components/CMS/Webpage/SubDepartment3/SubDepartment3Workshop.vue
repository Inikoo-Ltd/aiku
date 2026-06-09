<script setup lang="ts">
import { ref, computed } from "vue";
import { routeType } from "@/types/route";
import LoadingText from "@/Components/Utils/LoadingText.vue";
import Button from "@/Components/Elements/Buttons/Button.vue";
import { ctrans } from "@/Composables/useTrans";
import Image from "@/Common/Components/Image.vue";

interface Department {
  id: number
  name: string
  code: string
}

interface Family {
  id: number
  name: string
  image: any
  url: string
}

const props = defineProps<{
  modelValue: {
    collections: any[]
    sub_departments?: Department[]
    sub_department_list?: Department[]
    families?: {
      data: Family[]
      meta?: {
        current_page: number
        from: number
        last_page: number
        links: any[]
        path: string
        per_page: number
        to: number
        total: number
      }
    }
    container: {
      properties: object
    }
  }
  webpageData?: any
  blockData?: object
  screenType: "mobile" | "tablet" | "desktop"
  routeEditSubDepartment?: routeType
}>()

const loading = ref(false)
const loadingMore = ref(false)

const selectedSubDepartment = ref<string | null>(null)

const families = ref(props.modelValue.families?.data ?? [])

const subDepartmentList = computed(() =>
  props.modelValue.sub_department_list ??
  props.modelValue.sub_departments ??
  []
)

const meta = ref(
  props.modelValue.families?.meta ?? {
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


/* const getFamilies = () => {
     try {
        if (append) {
          loadingMore.value = true
        } else {
          loading.value = true
        }
    
        const response = await axios.get(
          route(
            'grp.org.shops.show.catalogue.sub_departments.show.families.index',
            {
              shop: route().params[shop],
              subDepartment: selectedSubDepartment
            }
          ),
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
} */

console.log('sdfsdf',subDepartmentList.value)

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

          <option v-for="department in subDepartmentList" :key="department.code" :value="department.code">
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

            <option v-for="department in subDepartmentList" :key="department.code"
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
    <div  class="mt-8 flex justify-center">
      <Button
         type="tertiary"  :injectStyle="{ padding: '14px 65px', fontSize: '1.2rem' }" :label="ctrans('Load More')">
        </Button>
    </div>
  </section>
</template>
