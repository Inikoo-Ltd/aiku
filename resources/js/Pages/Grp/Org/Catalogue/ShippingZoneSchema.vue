<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Sat, 22 Oct 2022 18:57:31 British Summer Time, Sheffield, UK
  -  Copyright (c) 2022, Raul A Perusquia Flores
  -->



  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3';
  import PageHeading from '@/Components/Headings/PageHeading.vue';
  import {useLocaleStore} from '@/Stores/locale';
  import {library} from '@fortawesome/fontawesome-svg-core';
  import TableShippingZones from '@/Components/Tables/Grp/Org/Catalogue/TableShippingZones.vue';
  import {
      faInventory,
      faBox,
      faClock,
      faCameraRetro,
      faPaperclip,
      faCube,
      faHandReceiving, faClipboard, faPoop, faScanner, faDollarSign
  } from '@fal';
  import { computed, defineAsyncComponent, ref } from "vue";
  import { useTabChange } from "@/Composables/tab-change";
  import ModelDetails from "@/Components/ModelDetails.vue";
  import Tabs from "@/Components/Navigation/Tabs.vue";
  import { capitalize } from "@/Composables/capitalize"
  library.add(
      faInventory,
      faBox,
      faClock,
      faCameraRetro,
      faPaperclip,
      faCube,
      faHandReceiving,
      faClipboard,
      faPoop,
      faScanner,
      faDollarSign,
  
  );
  
  const locale = useLocaleStore();
  
  const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))
  
  const props = defineProps<{
      title: string,
      pageHead: object,
      tabs: {
          current: string;
          navigation: object;
      }
      showcase?: object,
      zones?: {}
  
  }>()
  
  let currentTab = ref(props.tabs.current);
  const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab);
  
  const component = computed(() => {
  
      const components = {
          history: ModelChangelog,
          zones: TableShippingZones
      };
      return components[currentTab.value];
  
  });
  
  </script>
  
  
  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead"></PageHeading>
      <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate"/>
      <component :is="component" :data="props[currentTab]" :tab="currentTab"></component>
  </template>
  