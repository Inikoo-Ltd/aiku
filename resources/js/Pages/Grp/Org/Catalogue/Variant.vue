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
}>()

let currentTab = ref(props.tabs.current)
console.log(currentTab.value);
const handleTabUpdate = (tabSlug) => useTabChange(tabSlug, currentTab)


const component = computed(() => {
    const components: Record<string, any> ={
        showcase: VariantShowcase,
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
    </PageHeading>

    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <component :is="component" :tab="currentTab" :data="props[currentTab]" />

</template>
