<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow } from '@fal'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { computed, ref, toRaw } from "vue"
import { useTabChange } from "@/Composables/tab-change"
import Tabs from "@/Components/Navigation/Tabs.vue"
import LayoutWorkshop from "@/Components/CMS/Website/Layout/LayoutWorkshop.vue"
import WorkshopProduct from "@/Components/CMS/Website/Product/ProductWorkshop.vue"
import { capitalize } from "@/Composables/capitalize"
import CategoryWorkshop from '@/Components/CMS/Website/Family/CategoryWorkshop.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(faArrowAltToTop, faArrowAltToBottom, faTh, faBrowser, faCube, faPalette, faCheeseburger, faDraftingCompass, faWindow)

const props = defineProps<{
    title: string,
    pageHead: {}
    tabs: {
        current: string
        navigation: {}
    }
    color_scheme?: {}
    header?: {}
    menu?: {}
    footer?: {}
    category?: {}
    product?: {}
    website_layout: {}
    family?: {}
    settings: {}
}>()

console.log(props)

let currentTab = ref(props.tabs?.current)
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)
const template = ref(toRaw(props.settings.length == 0 ? {} : props.settings?.catalogue_template))
const loadingPublish = ref(false)

const component = computed(() => {
    const components = {
        website_layout: LayoutWorkshop,
        family: CategoryWorkshop,
        product: WorkshopProduct
    }
    return components[currentTab.value]
})


const onPublish = (routeData) => {
     router.patch(
         route(routeData.name, routeData.parameters),
          {catalogue_template : template.value},
         {
             preserveScroll: true,
             onStart: () => { loadingPublish.value = true },
             onSuccess: () => { console.log('done') },
             onError: errors => { console.log(errors) },
             onFinish: () => { loadingPublish.value = false },
         })
}

</script>


<template>
    <PageHeading :data="pageHead">
        <template #button-publish="{ action }">
            <Button v-bind="action" @click="()=>onPublish(action.route)" :loading="loadingPublish" />
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs['navigation']" @update:tab="handleTabUpdate" />
    <component :is="component" :data="props[currentTab]" v-model="template" />
  <!--   {{ template }} -->
</template>
