<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import { faBox, faBullhorn, faCameraRetro, faCube, faFolder, faMoneyBillWave, faProjectDiagram, faRoad, faShoppingCart, faStream, faUsers } from '@fal'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { useTabChange } from "@/Composables/tab-change"
  import { computed, defineAsyncComponent, inject, ref } from "vue"
  import type { Component } from 'vue'
  import Tabs from "@/Components/Navigation/Tabs.vue"
  import DummyTabComponent from "@/Components/DummyTabComponent.vue"
  import UnderConstruction from "@/Pages/Grp/Disclosure/UnderConstruction.vue"
  import { layoutStructure } from "@/Composables/useLayoutStructure"
  
  import { capitalize } from "@/Composables/capitalize"
  import { PageHeadingTypes } from '@/types/PageHeading'
  
  library.add(
      faFolder,
      faCube,
      faStream,
      faMoneyBillWave,
      faShoppingCart,
      faUsers,
      faBullhorn,
      faProjectDiagram,
      faBox,
      faCameraRetro,
      faRoad
  )
  
  const TableHistories = defineAsyncComponent(() => import('@/Components/Tables/Grp/Helpers/TableHistories.vue'))
  
  const props = defineProps<{
      title: string
      pageHead: PageHeadingTypes
      tabs: {
          current: string
          navigation: {}
      }
      showcase?: {}
      history?: {}
      rental: {}
  }>()
  
  
  const currentTab = ref(props.tabs.current)
  const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
  
  const layout = inject("layout", layoutStructure)
  const isLocal = computed(() => layout.app?.environment === "local")

  const component = computed(() => {
      const components: {[key: string]: Component} = {
          showcase: isLocal.value ? DummyTabComponent : UnderConstruction,
          history: TableHistories,
      }
  
      return components[currentTab.value]
  })
  
  </script>
  
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead" />
      <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
      <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
  </template>
  