<script setup lang="ts">
import { ref, inject, defineProps, defineEmits, onMounted, onUnmounted, toRaw } from 'vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import draggable from 'vuedraggable'
import { useConfirm } from 'primevue/useconfirm'
import { TabGroup, TabList, Tab, TabPanels, TabPanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import LoadingIcon from '@/Components/Utils/LoadingIcon.vue'
import Modal from '@/Components/Utils/Modal.vue'

import VisibleCheckmark from '@/Components/CMS/Fields/VisibleCheckmark.vue'
import SideEditor from '@/Components/Workshop/SideEditor/SideEditor.vue'
import SiteSettings from '@/Components/Workshop/SiteSettings.vue'
import ConfirmPopup from 'primevue/confirmpopup'
import { useLayoutStore } from '@/Stores/layout'
import { getBlueprint, getBluprintPermissions, getEditPermissions, getDeletePermissions, getHiddenPermissions } from '@/Composables/getBlueprintWorkshop'
import { Root, Daum } from '@/types/webBlockTypes'
import { Root as RootWebpage } from '@/types/webpageTypes'
import { Collapse } from 'vue-collapsed'
import { trans } from 'laravel-vue-i18n'
import WeblockList from '@/Components/CMS/Webpage/WeblockList.vue'

import { faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faEye, faEyeSlash, faPlus, faEyeDropper, faTrashAlt, faCopy, faPaste } from '@fal'
import { faCogs, faExclamationTriangle, faLayerGroup } from '@fas'

library.add(faBrowser, faDraftingCompass, faRectangleWide, faStars, faBars, faText, faEye, faEyeSlash)

const layout = useLayoutStore()
const modelModalBlocklist = defineModel()

const props = defineProps<{
  webpage: RootWebpage
  webBlockTypes: Root
}>()

const emits = defineEmits<{
  (e: 'add', value: { block: Daum, type: string }): void
  (e: 'delete', value: Daum): void
  (e: 'update', value: Daum): void
  (e: 'order', value: object): void
  (e: 'setVisible', value: object): void
  (e: 'onSaveSiteSettings', value: object): void
  (e: 'openBlockList', value: boolean): void
  (e: 'onDuplicateBlock', value: Number): void
}>()

const confirm = useConfirm()
const selectedTab = ref(1)
const addType = ref('current')
const openedBlockSideEditor = inject('openedBlockSideEditor', ref(null))
const openedChildSideEditor = inject('openedChildSideEditor', ref(null))
const isAddBlockLoading = inject('isAddBlockLoading', ref(null))
const isLoadingDeleteBlock = inject('isLoadingDeleteBlock', ref(null))
const isLoadingblock = inject('isLoadingblock', ref(null))
const filterBlock = inject('filterBlock')
const changeTab = (index: number) => (selectedTab.value = index)
const sendNewBlock = (block: Daum) => emits('add', { block, type: addType.value })
const sendBlockUpdate = (block: Daum) => emits('update', block)
const sendOrderBlock = (block: object) => emits('order', block)
const sendDeleteBlock = (block: Daum) => emits('delete', block)

const tabs = [
  { label: 'Settings', icon: faCogs, tooltip: 'Page Setting' },
  { label: 'Block', icon: faLayerGroup, tooltip: 'Blocks' }
]

const filterOptions = [
  { label: 'All', value: 'all' },
  { label: 'Logged out', value: 'logged-out' },
  { label: 'Logged in', value: 'logged-in' }
]

const onChangeOrderBlock = () => {
  const payload = {}
  props.webpage.layout.web_blocks.forEach((item, index) => {
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

const setShowBlock = (e: Event, value: Daum) => {
  e.stopPropagation()
  e.preventDefault()
  emits('setVisible', value)
  closeContextMenu()
}

const confirmDelete = (event: Event, data: Daum) => {
  confirm.require({
    target: event.currentTarget,
    message: trans("Remove this block? This action can't be undone."),
    rejectProps: { label: trans('Cancel'), severity: 'secondary', outlined: true },
    acceptProps: { label: trans('Yes, delete'), severity: 'danger' },
    accept: () => {
      sendDeleteBlock(data)
      closeContextMenu()
    },
  })
}

const showWebpage = (block: Daum) => {
  if (!block?.visibility) return true
  if (filterBlock.value === 'all') return true
  if (filterBlock.value === 'logged-out') return block.visibility.out
  if (filterBlock.value === 'logged-in') return block.visibility.in
  return true
}

const contextMenu = ref({
  visible: false,
  top: 0,
  left: 0,
  block: null as Daum | null,
})
const copiedBlock = ref<Daum | null>(null)

const openContextMenu = (event: MouseEvent, block: Daum | null = null) => {
  event.preventDefault()
  event.stopPropagation()
  contextMenu.value = {
    visible: true,
    top: event.clientY,
    left: event.clientX,
    block,
  }
}

const closeContextMenu = () => {
  contextMenu.value.visible = false
  contextMenu.value.block = null
}

const copyBlock = () => {
  if (contextMenu.value.block) {
    copiedBlock.value = structuredClone(toRaw(contextMenu.value.block))
  }
  closeContextMenu()
}
const pasteBlock = () => {
  if (!copiedBlock.value) return
  emits('onDuplicateBlock', copiedBlock.value.web_block.id)
  closeContextMenu()
}


onMounted(() => {
  window.addEventListener('click', closeContextMenu)
})
onUnmounted(() => {
  window.removeEventListener('click', closeContextMenu)
})

</script>


<template>
  <div>
    <TabGroup :selectedIndex="selectedTab" @change="changeTab">
      <TabList class="flex border-b border-gray-300">
        <Tab v-for="(tab, index) in tabs" :key="index"
          class="flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-600 hover:bg-gray-100 rounded-t-md focus:outline-none"
          :class="selectedTab === index ? 'bg-white text-theme border-b-2 border-theme' : ''">
          <FontAwesomeIcon :icon="tab.icon" fixed-width v-tooltip="tab.tooltip" />
          {{ tab.label }}
        </Tab>
      </TabList>

      <TabPanels>
        <TabPanel class="w-[400px] p-2">
          <div class="max-h-[calc(100vh-220px)] overflow-y-auto">
            <SiteSettings :webpage="webpage" :webBlockTypes="webBlockTypes"
              @onSaveSiteSettings="v => emits('onSaveSiteSettings', v)" />
          </div>
        </TabPanel>

        <!-- Blocks Tab -->
        <TabPanel class="w-[400px] p-2">
          <div class="h-[calc(100vh-220px)] overflow-y-auto relative" @contextmenu="openContextMenu($event, null)">
            <!-- Header Controls -->
            <div class="flex justify-between items-center mb-2">
              <Button type="dashed" @click="openModalBlockList" :icon="faPlus" class="text-sm text-theme border-theme"
                :size="'xs'" label="Block" />
              <select
                class="text-sm border border-gray-300 rounded-md px-2 py-1 bg-white text-gray-700 focus:outline-none"
                :value="filterBlock" @change="e => filterBlock = e.target.value">
                <option disabled value="">Filter</option>
                <option v-for="option in filterOptions" :key="option.value" :value="option.value">
                  {{ option.label }}
                </option>
              </select>
            </div>

            <!-- Blocks List -->
            <template v-if="webpage?.layout?.web_blocks.length">
              <draggable :list="webpage.layout.web_blocks" handle=".handle" @change="onChangeOrderBlock"
                ghost-class="ghost" group="column" itemKey="id" class="space-y-1">
                <template #item="{ element, index }">
                  <div v-if="showWebpage(element)" class="bg-white border border-gray-200 rounded"
                    @contextmenu="e => openContextMenu(e, element)">
                    <div class="flex justify-between items-center px-3 py-2 cursor-pointer hover:bg-gray-100"
                      :class="openedBlockSideEditor === index ? 'bg-theme text-white' : ''">
                      <div class="flex items-center gap-2 w-full"
                        @click="() => getEditPermissions(element.web_block.layout.data) && (openedBlockSideEditor = openedBlockSideEditor === index ? null : index)">
                        <FontAwesomeIcon icon="fal fa-bars" class="handle text-sm text-gray-400 cursor-grab" />
                        <span class="text-sm font-medium capitalize truncate">
                          {{ element.name || element.type }}
                        </span>
                        <LoadingIcon v-if="isLoadingblock === element.id" />
                      </div>

                      <div class="flex items-center gap-1">
                        <button v-if="getHiddenPermissions(element.web_block.layout.data)"
                          @click.stop.prevent="setShowBlock($event, element)"
                          class="p-1 text-theme hover:text-opacity-80 text-xs">
                          <FontAwesomeIcon :icon="element.show ? 'fal fa-eye' : 'fal fa-eye-slash'" fixed-width />
                        </button>

                        <button v-if="getDeletePermissions(element.web_block.layout.data)"
                          @click="e => isLoadingDeleteBlock !== element.id && confirmDelete(e, element)"
                          class="p-1 text-theme hover:text-opacity-80 text-xs">
                          <LoadingIcon v-if="isLoadingDeleteBlock === element.id" />
                          <FontAwesomeIcon v-else icon="fal fa-trash-alt" class="text-red-500" fixed-width />
                        </button>
                      </div>
                    </div>

                    <Collapse v-if="element?.web_block?.layout && getBluprintPermissions(element.type)"
                      :when="openedBlockSideEditor === index">
                      <div class="p-2 space-y-2">
                        <VisibleCheckmark v-model="element.visibility" @update:modelValue="sendBlockUpdate(element)" />
                        <SideEditor v-model="element.web_block.layout.data.fieldValue"
                          :panelOpen="openedChildSideEditor" :blueprint="getBlueprint(element.type)" :block="element"
                          @update:modelValue="() => sendBlockUpdate(element)"
                          :uploadImageRoute="{ ...webpage.images_upload_route, parameters: { modelHasWebBlocks: element.id } }" />
                      </div>
                    </Collapse>
                  </div>
                </template>
              </draggable>

            </template>
            <div v-else class="flex flex-col items-center text-center py-6 text-gray-500">
              <FontAwesomeIcon :icon="['fal', 'browser']" class="text-4xl mb-2" />
              <span class="text-sm font-medium">You don't have any blocks</span>
            </div>
            <div v-if="isAddBlockLoading" class="mt-2 skeleton min-h-10 w-full rounded bg-red-500" />
          </div>
        </TabPanel>
      </TabPanels>
    </TabGroup>

    <Modal :isOpen="modelModalBlocklist" @onClose="openModalBlockList">
      <WeblockList :onPickBlock="onPickBlock" :webBlockTypes="webBlockTypes" scope="all" />
    </Modal>

    <ConfirmPopup>
      <template #icon>
        <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
      </template>
    </ConfirmPopup>
  </div>


  <div v-if="contextMenu.visible" :style="{ top: `${contextMenu.top}px`, left: `${contextMenu.left}px` }"
    class="fixed z-50 bg-white border border-gray-200 shadow-md rounded text-sm min-w-[140px] overflow-hidden">
    <ul>
      <template v-if="contextMenu.block">
        <li @click="e => setShowBlock(e, contextMenu.block!)"
          class="flex items-center gap-2 px-3 py-1 hover:bg-gray-100 cursor-pointer">
          <font-awesome-icon :icon="contextMenu.block?.show ? faEyeSlash : faEye" />
          {{ contextMenu.block?.show ? 'Hide' : 'Unhide' }}
        </li>
        <li @click="e => confirmDelete(e, contextMenu.block!)"
          class="flex items-center gap-2 px-3 py-1 hover:bg-gray-100 text-red-600 cursor-pointer">
          <font-awesome-icon :icon="faTrashAlt" />
          Delete
        </li>
        <li @click="copyBlock" class="flex items-center gap-2 px-3 py-2 hover:bg-gray-100 cursor-pointer">
          <font-awesome-icon :icon="faCopy" />
          Copy
        </li>
      </template>
      <template v-else>
        <li @click="pasteBlock" class="flex items-center gap-2 px-3 py-1 hover:bg-gray-100 cursor-pointer"
          :class="{ 'text-gray-400 pointer-events-none': !copiedBlock }">
          <font-awesome-icon :icon="faPaste" />
          Paste
        </li>
      </template>
    </ul>
  </div>

</template>

<style scoped>
.text-theme {
  color: v-bind('layout?.app?.theme[4]') !important;
}

.bg-theme {
  background-color: v-bind('layout?.app?.theme[4]') !important;
}

.border-theme {
  border-color: v-bind('layout?.app?.theme[4]') !important;
}

.ghost {
  opacity: 0.5;
  background-color: #e2e8f0;
  border: 2px dashed v-bind('layout?.app?.theme[4]');
}
</style>
