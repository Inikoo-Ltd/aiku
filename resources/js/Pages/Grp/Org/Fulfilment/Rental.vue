<!--
  - Author: Jonathan Lopez Sanchez <jonathan@ancientwisdom.biz>
  - Created: Wed, 22 Feb 2023 10:36:47 Central European Standard Time, Malaga, Spain
  - Copyright (c) 2023, Inikoo LTD
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import {  } from '@fal'
  import PageHeading from '@/Components/Headings/PageHeading.vue'
  import { useTabChange } from "@/Composables/tab-change"
  import { computed, defineAsyncComponent, ref } from "vue"
  import type { Component } from 'vue'
  import Tabs from "@/Components/Navigation/Tabs.vue"
  
  import { capitalize } from "@/Composables/capitalize"
  import { PageHeadingTypes } from '@/types/PageHeading'
import RentalShowcase from '@/Components/Fulfilment/RentalShowcase.vue'
  
  library.add(
  )
  
  const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))
  
  const props = defineProps<{
      title: string
      pageHead: PageHeadingTypes
      tabs: {
          current: string
          navigation: {}
      }
      showcase?: {}
      rental: {}
  }>()
  
  
  const currentTab = ref(props.tabs.current)
  const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
  
  const component = computed(() => {
      const components: {[key: string]: Component} = {
          showcase: RentalShowcase,
          history: ModelChangelog,
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
  