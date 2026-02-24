<script setup lang="ts">
import { Head, Link } from "@inertiajs/vue3"
import { computed, provide, ref } from "vue"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import { capitalize } from "@/Composables/capitalize"
import { useLayoutStore } from "@/Stores/layout"
import Tabs from "@/Components/Navigation/Tabs.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faImage } from "@far"
import { useTabChange } from "@/Composables/tab-change"
import VariantShowcase from "@/Components/Showcases/Grp/VariantShowcase.vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faOctopusDeploy } from "@fortawesome/free-brands-svg-icons"
import TableProducts from "@/Components/Tables/Grp/Org/Catalogue/TableProducts.vue"
import { faExternalLink } from "@fal"

library.add(faImage, faOctopusDeploy)

const layout = useLayoutStore()
provide("layout", layout)

const props = defineProps<{
    title: string
    pageHead: any
    tabs: {
        current: string
        navigation: {}
    }
    showcase?: {}
    masterRoute?: {
        name: string
        parameters: []
    }
    products?: any
    webpage_canonical_url?: string
}>()

let currentTab = ref(props.tabs.current)
console.log(currentTab.value);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)


const component = computed(() => {
    const components: Record<string, any> ={
        showcase: VariantShowcase,
        products: TableProducts,
    }
    return components[currentTab.value]
})

</script>

<template>

    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #afterTitle>
             <Link v-if="masterRoute" :href="route(masterRoute.name, masterRoute.parameters)"  v-tooltip="trans('Go to Master')">
                <FontAwesomeIcon
                    icon="fab fa-octopus-deploy"
                    color="#4B0082"
                />
            </Link>
    </template>
        <template #other>
            <a v-if="webpage_canonical_url" :href="webpage_canonical_url" target="_blank" class="text-gray-400 hover:text-gray-700 px-2 cursor-pointer" v-tooltip="trans('Open website in new tab')" aclick="openWebsite" >
                <FontAwesomeIcon :icon="faExternalLink" aria-hidden="true" size="xl" />
            </a>
        </template>
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" />

</template>
