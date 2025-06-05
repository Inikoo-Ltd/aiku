<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow } from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, ref, toRaw } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import LayoutWorkshop from "@/Components/CMS/Website/Layout/LayoutWorkshop.vue"
import ProductBlockWorkshop from "@/Components/CMS/Website/ProductBlock/ProductBlockWorkshop.vue"
import ProductsBlockWorkshop from '@/Components/CMS/Website/ProductsBlock/ProductsBlockWorkshop.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import SubDepartementWorkshop from '@/Components/CMS/Website/SubDepartementBlockWorkshop/SubDepartementWorkshop.vue'
import FamiliesBlockWorkshop from '@/Components/CMS/Website/FamiliesBlockWorkshop/FamiliesBlockWorkshop.vue'

library.add(faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

const props = defineProps<{
    title: string,
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    }
    category?: {}
    product?: {}
    website_layout: {}
    families?: {}
    products?: {}
    settings: {}
    department: {}
    sub_department: {}
}>()


let currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const loadingPublish = ref(false)

const component = computed(() => {
    const components = {
        website_layout: LayoutWorkshop,
        sub_department: SubDepartementWorkshop,
        families: FamiliesBlockWorkshop,
        products: ProductsBlockWorkshop,
        product: ProductBlockWorkshop,
    }
    return components[currentTab.value]
})


const onPublish = (action: {
  method: 'post' | 'put' | 'patch' | 'delete',
  route: {
    name: string,
    parameters?: Record<string, any>
  }
}) => {
  console.log("Publishing action:", action)

  const currentTab = props.tabs?.current
  if (!currentTab || !props[currentTab]) {
    console.warn("No valid tab selected.")
    return
  }

  const payload = props[currentTab].layout

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
        console.log("Publishing successful.")
      },
      onError: (errors) => {
        console.error("Publishing failed with errors:", errors)
      },
      onFinish: () => {
        loadingPublish.value = false
        console.log("Publishing complete.")
      },
    }
  )
}

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
        <component :is="component" :data="props[currentTab]" />
    </KeepAlive>
</template>
