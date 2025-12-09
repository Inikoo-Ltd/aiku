<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import {
  faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube,
  faPalette, faCheeseburger, faDraftingCompass, faWindow
} from '@fal'
import { computed, ref, provide, inject, nextTick, onMounted, onUnmounted } from "vue"
import { useTabChange } from "@/Composables/tab-change"

import PageHeading from '@/Components/Headings/PageHeading.vue'
import Tabs from "@/Components/Navigation/Tabs.vue"
import LayoutWorkshop from "@/Components/CMS/Website/Layout/LayoutWorkshop.vue"
import ProductBlockWorkshop from "@/Components/CMS/Website/ProductBlock/ProductBlockWorkshop.vue"
import ProductsBlockWorkshop from '@/Components/CMS/Website/ProductsBlock/ProductsBlockWorkshop.vue'
import SubDepartmentWorkshop from '@/Components/CMS/Website/SubDepartmentBlockWorkshop/SubDepartmentWorkshop.vue'
import FamiliesBlockWorkshop from '@/Components/CMS/Website/FamiliesBlockWorkshop/FamiliesBlockWorkshop.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import { trans } from 'laravel-vue-i18n'
import { notify } from '@kyvg/vue3-notification'
import { routeType } from '@/types/route'

library.add(faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

const props = defineProps<{
  title: string
  pageHead: Record<string, any>
  tabs: { current: string; navigation: Record<string, any> }
  currency: Record<string, any>
  category?: Record<string, any>
  product?: Record<string, any>
  website_layout: Record<string, any>
  families?: Record<string, any>
  products?: Record<string, any>
  settings: Record<string, any>
  department: Record<string, any>
  sub_department: Record<string, any>
  collection: Record<string, any>
  publishRoute: Record<string, routeType>
  website_slug: string
}>()

const layout = inject('layout')
const currentTab = ref(props.tabs.current)
const loadingPublish = ref(false)
const progress = ref(0)

const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)

const component = computed(() => {
  const mapping = {
    website_layout: LayoutWorkshop,
    sub_department: SubDepartmentWorkshop,
    families: FamiliesBlockWorkshop,
    products: ProductsBlockWorkshop,
    product: ProductBlockWorkshop
  }
  return mapping[currentTab.value]
})

const onPublish = () => {
  const action = props.publishRoute[currentTab.value]
  const payload = props[currentTab.value]?.layout

  if (!action || !payload) {
    console.error("No valid tab selected or data missing.")
    return
  }
  /*  stopSocketListener() */
  router[action.method](
    route(action.name, action.parameters),
    { layout: payload },
    {
      preserveScroll: true,
      onStart: () => loadingPublish.value = true,
      onSuccess: () => {
        notify({ type: 'success', title: 'Success', text: 'Website section published successfully.' })
        initSocketListener()
      },
      onError: (errors) => notify({ type: 'error', title: 'Error', text: 'Failed to publish website section.' }),
      onFinish: () => { }
    }
  )
}

// Provide reload function for children
provide('reload', () => router.reload())

const channel = ref(null)
const initSocketListener = () => {
  console.log("Initializing Webblocks Update Socket Listener");
  if (!window.Echo) {
    console.error("Echo not found!");
    return;
  }
  if (!props.website_slug) {
    console.error("Website slug missing!");
    return;
  }

  const socketEvent = `updateWebblocks.${props.website_slug}`;
  const socketAction = ".progress";

  channel.value = window.Echo.private(socketEvent).listen(socketAction, (eventData: any) => {
    console.log("Progress Event:", eventData);
    if (typeof eventData.percent === "number") {
      progress.value = eventData.percent
      if (eventData.percent == 100) {
        loadingPublish.value = false
      }
    }
    if (eventData.percent >= 100) stopSocketListener();
  });
}



const stopSocketListener = () => {
  progress.value = 0 // Moved it here.
  /*   if (channel.value) {
      channel.value.stopListening()
      channel.value = null
    } */
}

onMounted(() => {
  initSocketListener()
})

onUnmounted(() => {
  stopSocketListener()
})

</script>

<template>
  <PageHeading :data="pageHead">
    <template #button-publish="{ action }">
      <Button v-if="currentTab !== 'website_layout'" v-bind="action" @click="onPublish" :loading="loadingPublish" />
      <div v-else></div>
    </template>
  </PageHeading>

  {{ props.website_slug }}

  <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />

  <KeepAlive>
    <component :is="component" :data="props[currentTab]" :currency="props.currency" />
  </KeepAlive>

  <Dialog v-model:visible="loadingPublish" modal :closable="false" :draggable="false" class="w-[90%] md:w-[400px]"
    header="Please Wait...">
    <div class="flex flex-col items-center text-center py-4">
      <ProgressSpinner style="width:50px;height:50px" />
      <p class="mt-4 text-gray-700">
        {{ trans('We are updating all webpages in your website.') }}<br />
        {{ trans('Do not close this page.') }}
      </p>
      <p class="mt-2 text-gray-800 font-semibold">{{ progress }}%</p>
      <div class="w-full bg-gray-200 h-2 rounded mt-2">
        <div class="bg-blue-600 h-2 rounded" :style="{ width: progress + '%' }"></div>
      </div>
    </div>
  </Dialog>

</template>
