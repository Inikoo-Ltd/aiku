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
import { faExternalLink, faSkull } from "@fal"
import Message from 'primevue/message';

library.add(faImage, faOctopusDeploy)

const layout = useLayoutStore()
provide("layout", layout)

const props = defineProps<{
    title: string
    warning?: {
        text: string
        title: string
        icon: string
        type: string
    }
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
    status?: boolean
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

const severityMap: Record<string, string> = {
  warning: "warn",
  success: "success",
  info: "info",
  error: "error"
}

const getSeverity = (type?: string) => {
  return type ? severityMap[type.toLowerCase()] || "info" : "info"
}

const showWarningMessage = ref(true);

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
            <FontAwesomeIcon 
                v-if="!status"
                v-tooltip="ctrans('Variant is disabled')"
                :icon="faSkull"
                class="text-red-500"
            />
    </template>
        <template #other>
            <a v-if="webpage_canonical_url" :href="webpage_canonical_url" target="_blank" class="text-gray-400 hover:text-gray-700 px-2 cursor-pointer" v-tooltip="trans('Open website in new tab')" aclick="openWebsite" >
                <FontAwesomeIcon :icon="faExternalLink" aria-hidden="true" size="xl" />
            </a>
        </template>
    </PageHeading>
    <Tabs :current="currentTab" :navigation="tabs.navigation" @update:tab="handleTabUpdate" />
    <div v-if="warning">
        <Message v-if="warning && showWarningMessage" :severity="getSeverity(warning.type)" xclosable="true"
            @close="showWarningMessage = false">
            <div class="flex items-start gap-3">
                <!-- Icon -->
                <FontAwesomeIcon v-if="warning.icon" :icon="warning.icon" class="w-4 h-4 flex-shrink-0 my-auto" :class="[
                    getSeverity(warning.type) === 'warn' ? 'text-yellow-800' :
                        getSeverity(warning.type) === 'success' ? 'text-green-800' :
                            getSeverity(warning.type) === 'error' ? 'text-red-800' :
                                'text-blue-500'
                ]" />

                <!-- Content -->
                <div class="flex flex-col">
                    <div class="text-lg font-semibold">
                        {{ warning?.title }}
                    </div>
                    <div v-if="warning?.text" :class="[
                        getSeverity(warning.type) === 'warn' ? 'text-yellow-600/80' :
                            getSeverity(warning.type) === 'success' ? 'text-green-500' :
                                getSeverity(warning.type) === 'error' ? 'text-red-500' :
                                    'text-blue-500'
                    ]" class="text-md">
                        <div v-html="warning?.text"/>
                    </div>
                </div>
            </div>
        </Message>
    </div>
    <component :is="component" :tab="currentTab" :data="props[currentTab]" />

</template>
