<!--
  -  Author: Jonathan Lopez <raul@inikoo.com>
  -  Created: Wed, 12 Oct 2022 16:50:56 Central European Summer Time, Benalmádena, Malaga,Spain
  -  Copyright (c) 2022, Jonathan Lopez
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import TableMasterDepartments from "@/Components/Tables/Grp/Goods/TableMasterDepartments.vue"
  import { capitalize } from "@/Composables/capitalize"
  import { faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown,faHome } from '@fal'
  import { library } from "@fortawesome/fontawesome-svg-core"
  import { PageHeadingTypes } from '@/types/PageHeading'
  import { computed, ref } from 'vue'
  import { useTabChange } from '@/Composables/tab-change'
  import Tabs from "@/Components/Navigation/Tabs.vue"

  library.add( faShapes, faSortAmountDownAlt, faBrowser, faSortAmountDown ,faHome)

  const props = defineProps<{
      pageHead: PageHeadingTypes
      title: string
      tabs: {
          current: string
          navigation: {}
      }
      index?: {}
      sales?: {}
  }>()

  const currentTab = ref<string>(props.tabs.current)
  const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
  const currentData = computed(() => (props as any)[currentTab.value])

  const component = computed(() => {
      const components: any = {
          index: TableMasterDepartments,
          sales: TableMasterDepartments,
      }

      return components[currentTab.value]
  })
  </script>

  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead" />
      <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
      <component :is="component" :key="currentTab" :tab="currentTab" :data="currentData"></component>
  </template>
