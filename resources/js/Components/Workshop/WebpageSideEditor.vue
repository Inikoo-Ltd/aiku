<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 07 Jun 2023 02:45:27 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { library } from '@fortawesome/fontawesome-svg-core'
import { inject, ref, defineExpose, computed} from 'vue'
import { faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faEye, faEyeSlash } from '@fal'
import draggable from "vuedraggable"
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from "@/Components/Utils/Modal.vue"
import BlockList from '@/Components/CMS/Webpage/BlockList.vue'
import VisibleCheckmark from '@/Components/CMS/Fields/VisibleCheckmark.vue';
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import { getBlueprint, getBluprintPermissions, getEditPermissions, getDeletePermissions, getHiddenPermissions } from '@/Composables/getBlueprintWorkshop'
import ConfirmPopup from 'primevue/confirmpopup';
import { useConfirm } from "primevue/useconfirm";
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import SiteSettings from '@/Components/Workshop/SiteSettings.vue'

import { Root, Daum } from '@/types/webBlockTypes'
import { Root as RootWebpage } from '@/types/webpageTypes'
import { Collapse } from 'vue-collapsed'
import { trans } from 'laravel-vue-i18n'
import { faCogs, faExclamation, faExclamationTriangle, faLayerGroup } from '@fas'
import { routeType } from '@/types/route'
import PureMultiselect from '../Pure/PureMultiselect.vue'


library.add(faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faEye, faEyeSlash)
const modelModalBlocklist = defineModel()

const props = defineProps<{
    webpage: RootWebpage
    webBlockTypes: Root
    isLoadingblock?: number | null | String
    isAddBlockLoading?: number | null | String
    isLoadingDeleteBlock?: number | null | String
    filterBlock : string
}>()


const selectedTab = ref(1)

function changeTab(index: Number) {
    selectedTab.value = index
}

const emits = defineEmits<{
    (e: 'add', value: Daum): void
    (e: 'delete', value: Daum): void
    (e: 'update', value: Daum): void
    (e: 'order', value: Object): void
    (e: 'setVisible', value: Object): void
    (e: 'onSaveSiteSettings', value: Object): void
    (e: 'update:filterBlock', value: string): void
}>()

const confirm = useConfirm();
const addType = ref('current')

const sendNewBlock = async (block: Daum) => {
    emits('add', { block, type: addType.value })
}

const sendBlockUpdate = async (block: Daum) => {
    emits('update', block)
}

const sendOrderBlock = async (block: Object) => {
    emits('order', block)
}

const sendDeleteBlock = async (block: Daum) => {
    emits('delete', block)
}


// const debouncedSendUpdate = debounce((block) => sendBlockUpdate(block), 1000, { leading: false, trailing: true })
// const onUpdatedBlock = (block) => {
//      debouncedSendUpdate(block)
// }
/* const onSaveWorkshop = inject('onSaveWorkshop', () => { console.log('onSaveWorkshop not provided') }) */

const onChangeOrderBlock = (e, d) => {
    let payload = {}
    props.webpage.layout.web_blocks.map((item, index) => {
        payload[item.web_block.id] = { position: index }
    })
    sendOrderBlock(payload)
}

const onPickBlock = async (block: Daum) => {
    await sendNewBlock(block)
    modelModalBlocklist.value = false
}

const openModalBlockList = () => {
    addType.value = 'current'
    modelModalBlocklist.value = !modelModalBlocklist.value
    emits('openBlockList', !modelModalBlocklist.value)
}

const setShowBlock = (e: Event, value: Object) => {
    e.stopPropagation()
    e.preventDefault()
    emits('setVisible', value)
    /* value.show = !value.show 
    onUpdatedBlock(value) */
}

const confirmDelete = (event: Event, data: Daum) => {
    confirm.require({
        target: event.currentTarget,
        message: trans("Remove this block? This action can't be undone."),
        rejectProps: {
            label: trans('Cancel'),
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: trans('Yes, delete'),
            severity: 'danger',
        },
        accept: () => {
            sendDeleteBlock(data);
        },
    });
    /*    event.stopPropagation()
       event.preventDefault() */
};

const tabs = [
    { label: 'Settings', icon: faCogs, tooltip: 'Page Setting' },
    { label: 'Block', icon: faLayerGroup, tooltip: 'Blocks' }
]

defineExpose({
    addType
})



const openedBlockSideEditor = inject('openedBlockSideEditor', ref(null))
const openedChildSideEditor = inject('openedChildSideEditor', ref(null))

const showWebpage = (activityItem: Daum) => {
    if (!activityItem?.visibility) return true

    switch (props.filterBlock) {
        case 'all':
            return true
        case 'logged-out':
            return activityItem.visibility.out
        case 'logged-in':
            return activityItem.visibility.in
        default:
            return true
    }
}



</script>

<template>
    <TabGroup :selectedIndex="selectedTab" @change="changeTab">
        <TabList class="flex border-b border-gray-300">
            <Tab v-for="(tab, index) in tabs" :key="index"
                class="flex items-center gap-2 px-4 py-2 font-medium text-gray-600 rounded-t-lg hover:bg-gray-100 focus:outline-none"
                :class="{ 'bg-white text-indigo-600 border-b-2 border-indigo-600': selectedTab === index }">
                <FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
            </Tab>
        </TabList>
        <TabPanels>
            <TabPanel class="w-[400px] p-1">
                <div class="max-h-[calc(100vh-220px)] transition-all overflow-y-auto flex flex-col">
                    <SiteSettings :webpage="webpage" :webBlockTypes="webBlockTypes"
                        @onSaveSiteSettings="(value) => emits('onSaveSiteSettings', value)" />
                </div>

            </TabPanel>
            <TabPanel class="w-[400px] p-1">
                <div class="max-h-[calc(100vh-220px)] transition-all overflow-y-auto flex flex-col">
                    <div class="flex justify-between">
                        <Button class="mt-3" type="dashed" @click="openModalBlockList">
                            <div class="text-gray-500">
                                <FontAwesomeIcon icon='fal fa-plus' class='' fixed-width aria-hidden='true' />
                                {{ trans('Add block') }}
                            </div>
                        </Button>
                        <div>
                            <select
                                class="mt-3 bg-transparent border rounded-md border-gray-400 text-gray-700 hover:bg-black/10 inline-flex items-center gap-x-2 font-medium focus:outline-none"
                                :value="filterBlock" @change="e => emits('update:filterBlock', e.target.value)"
                                required>
                                <option disabled value="">Filter</option>
                                <option value="all">All</option>
                                <option value="logged-out">Logged out</option>
                                <option value="logged-in">Logged in</option>
                            </select>
                        </div>
                    </div>

                    <template v-if="webpage?.layout?.web_blocks.length > 0 || isAddBlockLoading">
                        <draggable :list="props.webpage.layout.web_blocks" handle=".handle" @change="onChangeOrderBlock"
                            ghost-class="ghost" group="column" itemKey="id" class="mt-2 space-y-1 shadow">
                            <template #item="{ element, index }">
                              
                                <div class="bg-slate-50 border border-gray-300" v-if="showWebpage(element)">
                                    
                                    <div class="group flex justify-between items-center gap-x-2 relative w-full cursor-pointer"
                                        :class="openedBlockSideEditor === index ? 'bg-indigo-500 text-white' : 'hover:bg-gray-100'">
                                        <div class="h-10 flex items-center gap-x-2 py-2 px-3 w-full"
                                            @click="() => !getEditPermissions(element.web_block.layout.data) ? null : openedBlockSideEditor === index ? openedBlockSideEditor = null : openedBlockSideEditor = index">
                                            <div class="flex items-center justify-center">
                                                <FontAwesomeIcon icon="fal fa-bars"
                                                    class="handle text-sm cursor-grab pr-3 mr-2" />
                                            </div>
                                            <h3 class="lg:text-sm text-xs capitalize font-medium select-none">
                                                {{ element.name || element.type }}
                                            </h3>
                                            <LoadingIcon v-if="isLoadingblock === element.id" class="" />
                                        </div>

                                        <div class="h-full text-base cursor-pointer">
                                            <div 
                                                class="flex h-full items-center">
                                                <div v-if="getHiddenPermissions(element.web_block.layout.data)" @click="(e) => setShowBlock(e, element)" class="py-1 px-2"
                                                    :class="openedBlockSideEditor === index ? 'text-gray-300 hover:text-white' : 'text-gray-400 hover:text-gray-600'">
                                                    <FontAwesomeIcon v-if="!element.show"
                                                        v-tooltip="trans('Show this block')" icon="fal fa-eye-slash"
                                                        class="text-base" fixed-width aria-hidden="true" />
                                                    <FontAwesomeIcon v-else v-tooltip="trans('Hide this block')"
                                                        icon="fal fa-eye" class="text-base" fixed-width
                                                        aria-hidden="true" />
                                                </div>

                                                <div v-if="getDeletePermissions(element.web_block.layout.data)" @click="(e) => {
                                                    isLoadingDeleteBlock === element.id
                                                        ? false
                                                        : confirmDelete(e, element)
                                                }" v-tooltip="trans('Delete this block')"
                                                    class="h-10 border-l border-gray-300 text-red-400 bg-gray-50 hover:bg-red-100 hover:text-red-600 py-2.5 flex items-center justify-center px-2">
                                                    <LoadingIcon v-if="isLoadingDeleteBlock === element.id"
                                                        class="text-gray-400" />
                                                    <FontAwesomeIcon v-else icon="fal fa-trash-alt" class="" fixed-width
                                                        aria-hidden="true" />
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Section: Properties panel -->
                                    <Collapse v-if="element?.web_block?.layout && getBluprintPermissions(element.type)" as="section"
                                        :when="openedBlockSideEditor === index">
                                        <div class="p-2">
                                            <div class="px-2">
                                                <VisibleCheckmark v-model="element.visibility"
                                                    @update:modelValue="sendBlockUpdate(element)" />
                                            </div>


                                            <SideEditor v-model="element.web_block.layout.data.fieldValue"
                                                :panelOpen="openedChildSideEditor"
                                                :blueprint="getBlueprint(element.type)" :block="element"
                                                @update:modelValue="(e) => (sendBlockUpdate(element))"
                                                :uploadImageRoute="{ ...webpage.images_upload_route, parameters: { modelHasWebBlocks: element.id } }" />
                                        </div>
                                    </Collapse>
                                </div>

                            </template>
                        </draggable>

                        <div v-if="isAddBlockLoading" class="mt-2 skeleton min-h-10 w-full rounded" />
                    </template>

                    <div v-else class="flex flex-col justify-center items-center mt-4 rounded-lg p-4 text-center h-fit">
                        <font-awesome-icon :icon="['fal', 'browser']" class="mx-auto h-12 w-12 text-gray-400" />
                        <span class="mt-2 block text-sm font-semibold text-gray-600">You don't have any blocks</span>
                    </div>
                </div>

            </TabPanel>
        </TabPanels>
    </TabGroup>



    <Modal :isOpen="modelModalBlocklist" @onClose="openModalBlockList">
        <BlockList :onPickBlock="onPickBlock" :webBlockTypes="webBlockTypes" scope="all" />
    </Modal>

    <ConfirmPopup>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmPopup>
</template>


<style scss scoped>
.ghost {
    opacity: 0.5;
    background-color: #e2e8f0;
    /* Warna abu-abu muda */
    border: 2px dashed #4F46E5;
}
</style>