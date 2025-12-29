<script setup lang="ts">
import { ref, computed } from "vue"
import { routeType } from "@/types/route"
import { library } from "@fortawesome/fontawesome-svg-core"
import { TabGroup, Tab, TabPanels, TabPanel, TabList } from '@headlessui/vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import SetMenuListWorkshopForSidebar from "@/Components/CMS/Fields/SetMenuListWorkshopForSidebar.vue"
import axios from "axios"
import {
    faChevronRight,
    faSignOutAlt,
    faShoppingCart,
    faSearch,
    faChevronDown,
    faTimes,
    faPlusCircle,
    faBars,
    faThLarge,
    faList,
    faPaintBrushAlt, faEllipsisHAlt
} from "@fas"
import { faHeart, faLowVision } from "@far"
import { notify } from "@kyvg/vue3-notification"
import SideEditor from "@/Components/Workshop/SideEditor/SideEditor.vue"
import { get, set } from "lodash"
import { trans } from "laravel-vue-i18n"

library.add(faChevronRight, faEllipsisHAlt, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faLowVision)

const props = defineProps<{
    data: {
        data: {
            component: string,
            fieldValue: Object
        }
    }
    autosaveRoute: routeType
    uploadImageRoute: routeType
    webBlockTypes: {
        data: Array<any>
    }
}>()

const blueprint = [
    {
        name: "Layout",
        key: ["container", "properties"],
        replaceForm: [
            {
                key: ["background"],
                label: "Background",
                type: "background",
            },
            {
                key: ["text"],
                label: "Text",
                type: "textProperty",
            },
            {
                key: ["border", "color"],
                label: "Lines",
                information: trans('Lines that used to separates the menu'),
                type: "color",
            },
            {
                key: ["border", "width"],
                label: "Lines thickness",
                information: trans('Reasonal number is 0px to 12px'),
                props_data: {
                    unit_option: [
                        { label: 'px', value: 'px' },
                    ],
                    defaultValue: {
                        value: 1,
                        unit: 'px',
                    }
                },
                type: "numberCss",
            },
        ],
    },
    {
        name: "Logo size",
        key: ["logo_dimension"],
        replaceForm: [
            {
                key: ["dimension"],
                label: "Dimension",
                type: "dimension",
            },
        ],
    },
]

const emits = defineEmits<{
    (e: 'sendToIframe', value: Object): void
}>()

const selectedTab = ref(props.data ? 0 : 0)

const tabs = [
    { label: 'Appearance', icon: faPaintBrushAlt, tooltip: 'Appearance' },
    // { label: 'List elements', icon: faList, tooltip: 'List elements' },
    // { label: 'Additional Items', icon: faEllipsisHAlt, tooltip: 'Additional Items' },
]

function changeTab(index: number) {
    selectedTab.value = index
}

const computedTabs = computed(() => {
    return props.data
        ? tabs
        : [tabs[1]]
})


let controller: AbortController | null = null
const autoSave = async (value) => {

    console.log(value)
    // Cancel the previous request if still pending
    if (controller) {
        controller.abort()
    }

    // Create a new controller for this request
    controller = new AbortController()

    try {
        const response = await axios.patch(
            route(props.autosaveRoute.name, props.autosaveRoute.parameters),
            { layout: value },
            { signal: controller.signal }
        )
        emits('sendToIframe', { key: "reload", value: {} })
    } catch (error: any) {
        if (axios.isCancel(error) || error.name === "CanceledError" || error.message === "canceled") {
            console.log("Autosave request cancelled")
            return
        }

        notify({
            title: "Something went wrong.",
            text: error.message,
            type: "error",
        })
    }
}


</script>

<template>
    <TabGroup :selectedIndex="selectedTab" @change="changeTab">
        <TabList class="flex border-b border-gray-300">
            <Tab v-for="(tab, index) in computedTabs" :key="index"
                class="flex items-center gap-2 px-4 py-2 font-medium text-gray-600 rounded-t-lg hover:bg-gray-100 focus:outline-none"
                :class="{ 'bg-white text-indigo-600 border-b-2 border-indigo-600': selectedTab === index }">
                <FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
            </Tab>
        </TabList>
        
        <TabPanels>
            <!-- <TabPanel>
                    <WebBlockListDnd :webBlockTypes="webBlockTypes" @pick-block="onPickBlock"
                        :selectedWeblock="data.code" />
                </TabPanel> -->

            <!-- Panel: Appearance -->
            <TabPanel v-if="data">
                <SideEditor
                    av-model="data.data.fieldValue"
                    :modelValue="get(data, ['data', 'fieldValue'], {})"
                    :blueprint="blueprint"
                    @update:modelValue="(e) => { set(data, ['data', 'fieldValue'], e), 'autoSave(data)' }"
                    :uploadImageRoute
                />
                <pre>{{ data }}</pre>
            </TabPanel>

            <!-- Panel: Menu List -->
            <TabPanel v-if="data">
                <SetMenuListWorkshopForSidebar
                    :data="data"
                    :autosaveRoute="autosaveRoute"
                    @auto-save="() => autoSave(data)"
                    :uploadImageRoute
                />
            </TabPanel>
        </TabPanels>
    </TabGroup>
</template>

<style scoped lang="scss"></style>