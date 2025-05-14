<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 20 Jun 2023 20:46:53 Malaysia Time, Pantai Lembeng, Bali, Id
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { Head } from '@inertiajs/vue3'
  import PageHeading from "@/Components/Headings/PageHeading.vue"
  import { capitalize } from "@/Composables/capitalize"
  import { useTabChange } from "@/Composables/tab-change"
  import { computed, defineAsyncComponent, ref } from "vue"
  import type { Component } from 'vue'
  import Tabs from "@/Components/Navigation/Tabs.vue"

  import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
  import CustomerClientShowcase from "@/Components/Showcases/Grp/CustomerClientShowcase.vue"
  import TableWebUsers from "@/Components/Tables/Grp/Org/CRM/TableWebUsers.vue"
  import { PageHeading as PageHeadingTS } from '@/types/PageHeading'
  import ModelDetails from "@/Components/ModelDetails.vue"
  import TableOrders from "@/Components/Tables/Grp/Org/Ordering/TableOrders.vue"
  import TableDispatchedEmails from "@/Components/Tables/TableDispatchedEmails.vue"
  const ModelChangelog = defineAsyncComponent(() => import('@/Components/ModelChangelog.vue'))


  import { library } from "@fortawesome/fontawesome-svg-core"
  import { faCodeCommit, faGlobe, faGraduationCap, faMoneyBill, faPaperclip, faPaperPlane, faStickyNote, faTags, faCube, faCodeBranch, faShoppingCart } from '@fal'
import ShipperShowcase from '@/Components/Showcases/Grp/ShipperShowcase.vue'
  library.add( faStickyNote, faGlobe, faMoneyBill, faGraduationCap, faTags, faCodeCommit, faPaperclip, faPaperPlane, faCube, faCodeBranch, faShoppingCart )


  const props = defineProps<{
      title: string
      pageHead: PageHeadingTS
      tabs: {
          current: string
          navigation: {}
      }
      showcase?: {}
  }>()

  let currentTab = ref(props.tabs.current)
  const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

  const component = computed(() => {
      const components: Component = {
          showcase: ShipperShowcase,
      }

      return components[currentTab.value]
  })

  </script>

  <template>
      <Head :title="capitalize(title)" />
      <PageHeading :data="pageHead" />

      <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
      <component :is="component" :data="props[currentTab as keyof typeof props]" :tab="currentTab" />
  </template>
