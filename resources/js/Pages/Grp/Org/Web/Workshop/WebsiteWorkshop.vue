<script setup lang="ts">
import { router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow } from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, ref, provide } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import LayoutWorkshop from "@/Components/CMS/Website/Layout/LayoutWorkshop.vue"
import ProductBlockWorkshop from "@/Components/CMS/Website/ProductBlock/ProductBlockWorkshop.vue"
import ProductsBlockWorkshop from '@/Components/CMS/Website/ProductsBlock/ProductsBlockWorkshop.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SubDepartmentWorkshop from '@/Components/CMS/Website/SubDepartmentBlockWorkshop/SubDepartmentWorkshop.vue'
import FamiliesBlockWorkshop from '@/Components/CMS/Website/FamiliesBlockWorkshop/FamiliesBlockWorkshop.vue'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import Dialog from 'primevue/dialog'
import ProgressSpinner from 'primevue/progressspinner'
import { trans } from 'laravel-vue-i18n'


library.add(faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

const props = defineProps<{
  title: string,
  pageHead: {}
  tabs: {
    current: string
    navigation: {}
  }
  currency: {}
  category?: {}
  product?: {}
  website_layout: {}
  families?: {}
  products?: {}
  settings: {}
  department: {}
  sub_department: {}
  collection: {}
  publishRoute: {
    website_layout: routeType,
    sub_department: routeType,
    families: routeType,
    products: routeType,
    product: routeType,
  }
}>()


let currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug: string) => useTabChange(tabSlug, currentTab)
const loadingPublish = ref(false)

const component = computed(() => {
  const components = {
    website_layout: LayoutWorkshop,
    sub_department: SubDepartmentWorkshop,
    families: FamiliesBlockWorkshop,
    products: ProductsBlockWorkshop,
    product: ProductBlockWorkshop,
  }
  return components[currentTab.value]
})


const onPublish = () => {
  const action = props.publishRoute[currentTab.value]
  if (!currentTab || !props[currentTab.value]) {
    console.error("No valid tab selected.")
    return
  }

  const payload = props[currentTab.value].layout

  router[action.method](
    route(action.name, action.parameters),
    { layout: payload },
    {
      preserveScroll: true,
      onStart: () => {
        loadingPublish.value = true
        console.log("Publishing started…")
      },
      onSuccess: () => {
        notify({
          type: 'success',
          title: 'Success',
          text: 'Website section published successfully.'
        })
      },
      onError: (errors) => {
        console.error("Publishing failed with errors:", errors)
        notify({
          type: 'error',
          title: 'Error',
          text: 'Failed to publish website section.'
        })
      },
      onFinish: () => {
        loadingPublish.value = false
        console.log("Publishing complete.")
      },
    }
  )
}

provide('reload', router.reload())


</script>


<template>
  <PageHeading :data="pageHead">
    <template #button-publish="{ action }">
      <Button v-if="currentTab != 'website_layout'" v-bind="action" @click="() => onPublish(action.route)"
        :loading="loadingPublish" />
      <div v-else></div>
    </template>
  </PageHeading>
  <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
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
    </div>
  </Dialog>

</template>
