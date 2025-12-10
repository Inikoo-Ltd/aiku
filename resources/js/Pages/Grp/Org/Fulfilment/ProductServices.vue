<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 23 May 2024 09:45:43 British Summer Time, Sheffield, UK
  - Copyright (c) 2024, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import {Head} from '@inertiajs/vue3';
  import PageHeading from '@/Components/Headings/PageHeading.vue';
  import TableServices from "@/Components/Tables/Grp/Org/Fulfilment/TableServices.vue";
  import {capitalize} from "@/Composables/capitalize"
  import {computed, ref} from "vue";
  import {useTabChange} from "@/Composables/tab-change";
  import TableHistories from "@/Components/Tables/Grp/Helpers/TableHistories.vue";
  import Tabs from "@/Components/Navigation/Tabs.vue";
  import { PageHeadingTypes } from "@/types/PageHeading";
  import type { Navigation } from "@/types/Tabs";
  
  const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    tabs: {
      current: string
      navigation: Navigation
    }
    services?: {}
    history?: {}
  }>()
  
  let currentTab = ref(props.tabs.current)
  const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
  
  const component = computed(() => {
  
    const components = {
      services: TableServices,
      history: TableHistories
    }
    return components[currentTab.value]
  
  });
  
  </script>
  
  <template>
    <Head :title="capitalize(title)"/>
    <PageHeading :data="pageHead"></PageHeading>
    Productservices
  </template>
  
  