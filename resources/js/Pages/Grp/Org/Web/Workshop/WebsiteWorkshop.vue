<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed, ref, provide, inject, onMounted, onUnmounted } from 'vue'
import { useTabChange } from '@/Composables/tab-change'

import {
  faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube,
  faPalette, faCheeseburger, faDraftingCompass, faWindow,
  faPageBreak, faSpinnerThird
} from '@fal'

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from '@/Components/Navigation/Tabs.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

import LayoutWorkshop from '@/Components/CMS/Website/Layout/LayoutWorkshop.vue'
import ProductBlockWorkshop from '@/Components/CMS/Website/ProductBlock/ProductBlockWorkshop.vue'
import ProductsBlockWorkshop from '@/Components/CMS/Website/ProductsBlock/ProductsBlockWorkshop.vue'
import SubDepartmentWorkshop from '@/Components/CMS/Website/SubDepartmentBlockWorkshop/SubDepartmentWorkshop.vue'
import FamiliesBlockWorkshop from '@/Components/CMS/Website/FamiliesBlockWorkshop/FamiliesBlockWorkshop.vue'
import FamiliesOverviewBlockWorkshop from '@/Components/CMS/Website/FamiliesOverviewBlockWorkshop/FamiliesOverviewWorkshop.vue'
import FamiliesDescriptionBlockWorkshop from '@/Components/CMS/Website/FamilyDescriptionBlockWorkshop/FamilyDescriptionBlockWorkshop.vue'
import TableHistories from '@/Components/Tables/Grp/Helpers/TableHistories.vue'

import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'

library.add(faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow, faPageBreak, faSpinnerThird)

const TAB_COMPONENT_MAP = {
  website_layout: LayoutWorkshop,
  sub_department: SubDepartmentWorkshop,
  families: FamiliesBlockWorkshop,
  families_overview: FamiliesOverviewBlockWorkshop,
  families_description: FamiliesDescriptionBlockWorkshop,
  products: ProductsBlockWorkshop,
  product: ProductBlockWorkshop,
  history: TableHistories
}

const props = defineProps<{
  title: string
  pageHead: Record<string, any>
  tabs: { current: string; navigation: Record<string, any> }
  currency: Record<string, any>
  category?: Record<string, any>
  product?: Record<string, any>
  website_layout: Record<string, any>
  families?: Record<string, any>
  families_overview?: Record<string, any>
  products?: Record<string, any>
  settings: Record<string, any>
  department: Record<string, any>
  sub_department: Record<string, any>
  families_description: Record<string, any>
  collection: Record<string, any>
  publishRoute: Record<string, routeType>
  website_slug: string
  layout_theme: Array<any>
  history: {}
}>()

const currentTab = ref(props.tabs.current)
const loadingPublish = ref(false)
const modalPublish = ref(false)
const progress = ref(0)
let socketChannel: any = null

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => TAB_COMPONENT_MAP[currentTab.value])

provide('reload', () => router.reload())

const onPublish = () => {
  const action = props.publishRoute[currentTab.value]
  const payload = props[currentTab.value]?.layout

  if (!action || !payload) return

  router[action.method](
    route(action.name, action.parameters),
    { layout: payload },
    {
      preserveScroll: true,
      onStart: () => {
        modalPublish.value = true
        loadingPublish.value = true
        progress.value = 0
      },
      onFinish: () => {
        loadingPublish.value = false
      },
      onSuccess: () => {
        notify({ type: 'success',  title: trans('Success'), text: trans('Website section published successfully') })
        initSocketListener()
      },
      onError: () => {
        notify({ type: 'error', title: trans('Error'), text: trans('Failed to publish website section') })
      }
    }
  )
}

const initSocketListener = () => {
  if (!window.Echo || !props.website_slug) return

  const socketEvent = `updateWebblocks.${props.website_slug}`
  socketChannel = window.Echo.private(socketEvent).listen('.progress', (eventData: any) => {
    if (typeof eventData.percent === 'number') {
      progress.value = eventData.percent
      loadingPublish.value = eventData.percent < 100
      if (eventData.percent >= 100) stopSocketListener()
    }
  })
}

const stopSocketListener = () => {
  progress.value = 0
  socketChannel = null
}

onMounted(() => initSocketListener())
onUnmounted(() => stopSocketListener())

</script>

<template>
  <PageHeading :data="pageHead">
    <template #button-publish="{ action }">
      <Button v-if="currentTab !== 'history'" v-bind="action" @click="onPublish">
        <template #loading v-if="loadingPublish">
          <FontAwesomeIcon :icon="faSpinnerThird" class="animate-spin" fixed-width aria-hidden="true" /> {{ progress }}%
        </template>
      </Button>
      <div v-else></div>
    </template>
  </PageHeading>
  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
  <KeepAlive>
    <component 
      :is="component" 
      :tab="currentTab" 
      :data="props[currentTab]" 
      :currency="props.currency" 
      :layout_theme
      @update:layout="(data) => {props[currentTab].layout = data} " 
    />
  </KeepAlive>
</template>
