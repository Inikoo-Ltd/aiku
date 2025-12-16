<script setup lang="ts">
import { ref } from 'vue'
import draggable from 'vuedraggable'
import { v4 as uuid } from 'uuid'
import cloneDeep from 'lodash-es/cloneDeep'

// Components
import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'
import DialogEditLink from '@/Components/CMS/Website/Menus/EditMode/DialogEditLink.vue'
import IconPicker from '@/Components/Pure/IconPicker.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import ConfirmPopup from 'primevue/confirmpopup'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'

// FontAwesome
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import {
  faChevronRight, faSignOutAlt, faShoppingCart, faSearch,
  faChevronDown, faChevronUp, faTimes,
  faPlusCircle, faBars, faTrashAlt, faGlobe
} from '@fas'
import { faHeart } from '@fortawesome/free-regular-svg-icons'
import { faExclamationTriangle, faTimesCircle } from '@fal'

library.add(
  faChevronRight, faSignOutAlt, faShoppingCart,
  faHeart, faSearch, faChevronDown,
  faChevronUp, faTimes, faPlusCircle,
  faBars, faTrashAlt, faGlobe
)

// ================= TYPES =================
interface NavigationLink {
  id: string
  label: string
  icon?: any
  link?: { href?: string; type?: string; workshop?: string }
}

interface SubNavigation {
  id: string
  title: string
  links: NavigationLink[]
}

interface Navigation {
  label: string
  type: string
  icon?: any
  link?: any
  subnavs?: SubNavigation[]
}

// ================= PROPS =================
const props = defineProps<{ modelValue: Navigation }>()
const emits = defineEmits<{
  (e: 'update:modelValue', val: Navigation): void
}>()

// ================= LOCAL STATE =================
const localNav = ref<Navigation>(cloneDeep(props.modelValue))

const commit = (patch: Partial<Navigation>) => {
  localNav.value = { ...localNav.value, ...patch }
  emits('update:modelValue', cloneDeep(localNav.value))
}

// ================= UI STATE =================
const visibleNameDialog = ref(false)
const visibleDialog = ref(false)
const visibleNavigation = ref(false)

const nameValue = ref<any>(null)
const linkValue = ref<NavigationLink | null>(null)

const parentIdx = ref(-1)
const linkIdx = ref(-1)

// ================= ACTIONS =================
const changeType = (type: string) => {
  commit({
    type,
    subnavs: type === 'multiple' ? [] : undefined
  })
}

const addSubNavigation = () => {
  const subnavs = cloneDeep(localNav.value.subnavs ?? [])
  subnavs.push({
    id: uuid(),
    title: 'New Navigation',
    links: [{ id: uuid(), label: 'New Link', icon: null, link: {} }]
  })
  commit({ subnavs })
}

const deleteSubNavigation = (index: number) => {
  const subnavs = cloneDeep(localNav.value.subnavs ?? [])
  subnavs.splice(index, 1)
  commit({ subnavs })
}

const addLink = (subnavIndex: number) => {
  const subnavs = cloneDeep(localNav.value.subnavs ?? [])
  subnavs[subnavIndex].links.push({
    id: uuid(),
    label: 'New Link',
    icon: null,
    link: {}
  })
  commit({ subnavs })
}

const saveSubnavTitle = (data) => {
  console.log(data)
  const subnavs = cloneDeep(localNav.value.subnavs ?? [])
  subnavs[parentIdx.value].title = data.label
  subnavs[parentIdx.value].link = data.link
  commit({ subnavs })
  resetDialog()
}

const saveLink = (data: NavigationLink) => {
  const subnavs = cloneDeep(localNav.value.subnavs ?? [])
  subnavs[parentIdx.value].links[linkIdx.value] = {
    ...subnavs[parentIdx.value].links[linkIdx.value],
    ...data
  }
  commit({ subnavs })
  resetDialog()
}

const saveNavigationLink = (data: any) => {
  commit({
    label: data.label,
    link: data.link
  })
  visibleNavigation.value = false
}

const openNameDialog = (subnav: SubNavigation, index: number) => {
  parentIdx.value = index
  nameValue.value = { label: subnav.title, link: subnav.link }
  visibleNameDialog.value = true
}

const openLinkDialog = (link: NavigationLink, pIdx: number, lIdx: number) => {
  parentIdx.value = pIdx
  linkIdx.value = lIdx
  linkValue.value = cloneDeep(link)
  visibleDialog.value = true
}

const resetDialog = () => {
  visibleDialog.value = false
  visibleNameDialog.value = false
  visibleNavigation.value = false
  nameValue.value = null
  linkValue.value = null
  parentIdx.value = -1
  linkIdx.value = -1
}
</script>



<template>
<div class="bg-slate-50 p-6 max-w-4xl mx-auto">
  <!-- Navigation Title + Icon -->
  <section class="bg-white rounded-lg shadow-md p-6 mb-6 space-y-6">
    <div>
      <h2 class="font-medium text-gray-800 text-lg mb-4">Navigation Title</h2>
      <div class="flex items-center gap-3">
        <button type="button" class="border border-gray-300 rounded px-3 py-2 cursor-pointer hover:border-blue-500 transition focus:outline-none focus:ring-2 focus:ring-blue-500">
          <IconPicker v-model="props.modelValue.icon" />
        </button>
        <input v-model="props.modelValue.label" type="text" placeholder="Enter Navigation Title"
          class="flex-grow border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
      </div>
    </div>

    <!-- Type Selector -->
    <div>
      <h3 class="font-medium text-gray-800 text-md mb-3">Type</h3>
      <PureMultiselect 
        :required="true" 
        v-model="props.modelValue.type" 
        label="label" value-prop="value" 
        :options="[{ label: 'Single', value: 'single' }, { label: 'Multiple', value: 'multiple' }]" 
        @change="(e) => changeType(e)" 
      />
    </div>

    <!-- Navigation Link -->
    <div>
      <h3 class="font-medium text-gray-800 text-md mb-3">Link</h3>
      <div v-if="!props.modelValue?.link?.href" @click="visibleNavigation=true" tabindex="0" role="button" class="flex items-center justify-between bg-gray-100 p-3 rounded-md cursor-pointer">
        <span class="text-gray-500 truncate">Not set up yet</span>
      </div>
      <div v-else class="flex items-center justify-between bg-gray-100 p-3 rounded-md">
        <span class="text-blue-500 hover:underline truncate cursor-pointer" @click="visibleNavigation=true">{{ props.modelValue.link.href }}</span>
        <div class="flex items-center gap-2">
          <a v-if="props.modelValue.link.type === 'internal'" :href="props.modelValue.link.workshop" target="_blank" rel="noopener">
            <FontAwesomeIcon :icon="faCompassDrafting" />
          </a>
          <a :href="props.modelValue.link.href" target="_blank" rel="noopener">
            <FontAwesomeIcon :icon="faExternalLink" />
          </a>
        </div>
      </div>

      <!-- Subnavigation -->
      <div v-if="props.modelValue.type === 'multiple'">
        <div class="font-medium text-gray-800 text-lg mb-4 mt-4">Subnavigation</div>
        <draggable :list="props.modelValue.subnavs" class="flex flex-col gap-4" ghost-class="ghost" itemKey="id" handle=".drag-handle">
          <template #item="{ element, index }">
            <Disclosure>
              <template #default="{ open }">
                <article class="bg-white rounded-lg shadow-lg" :class="open ? 'ring-1 ring-blue-500' : ''">
                  <DisclosureButton class="flex justify-between items-center w-full p-4 cursor-pointer">
                    <div class="flex items-center gap-3">
                      <FontAwesomeIcon icon="fas fa-bars" class="drag-handle cursor-move text-gray-400"/>
                      <div class="text-md" @click.stop="() => openNameDialog(element, index)" tabindex="0" role="button">
                        <span v-if="element.title" class="font-medium text-gray-800">{{ element.title }}</span>
                        <span v-else class="font-medium text-gray-400">Has no title</span>
                      </div>
                    </div>
                    <div class="flex items-center gap-3">
                      <FontAwesomeIcon v-if="element.links.length < 8" icon="fas fa-plus-circle" class="cursor-pointer text-blue-500" @click.stop="() => addLink(element)"/>
                      <FontAwesomeIcon icon="fas fa-trash-alt" class="cursor-pointer text-red-500" @click.stop="() => deleteSubNavigation(index)"/>
                      <FontAwesomeIcon :icon="open ? 'fas fa-chevron-up' : 'fas fa-chevron-down'" class="text-gray-400"/>
                    </div>
                  </DisclosureButton>

                  <DisclosurePanel class="p-3 border-t border-gray-200">
                    <draggable :list="element.links" ghost-class="ghost" group="link" itemKey="id" handle=".link-drag-handle" :animation="200" class="flex flex-col gap-y-2">
                      <template #item="{ element: link, index: linkIndex }">
                        <div class="flex items-center gap-2 p-2 bg-gray-50 rounded hover:bg-gray-100 transition">
                          <FontAwesomeIcon icon="fas fa-bars" class="link-drag-handle cursor-move text-gray-400 pr-2"/>
                          <IconPicker v-model="link.icon"/>
                          <div class="flex justify-between items-center w-full">
                            <div class="text-gray-500 hover:text-gray-600 hover:underline cursor-pointer text-xs" @click.stop="() => openLinkDialog(link, index, linkIndex)" tabindex="0" role="button">{{ link.label }}</div>
                            <div class="flex items-center gap-3">
                              <a v-if="link?.link?.type == 'internal'" :href="link.link.workshop" target="_blank" rel="noopener">
                                <FontAwesomeIcon :icon="faCompassDrafting" class="text-gray-400 hover:text-gray-600 transition"/>
                              </a>
                              <a v-if="link?.link?.href" :href="link.link.href" target="_blank" rel="noopener">
                                <FontAwesomeIcon :icon="faExternalLink" class="text-gray-400 hover:text-gray-600 transition"/>
                              </a>
                              <span @click.stop="() => element.links.splice(linkIndex, 1)" class="text-red-400 hover:text-red-600 cursor-pointer">
                                <FontAwesomeIcon :icon="faTimesCircle"/>
                              </span>
                            </div>
                          </div>
                        </div>
                      </template>
                    </draggable>
                  </DisclosurePanel>
                </article>
              </template>
            </Disclosure>
          </template>
        </draggable>

        <!-- Add Subnavigation Button -->
        <div class="flex justify-end mt-2">
          <Button label="Add Subnavigation" type="create" :disabled="props.modelValue.subnavs?.length >= 8" @click="addSubNavigation"/>
        </div>
      </div>
    </div>
  </section>

  <!-- Dialogs -->
  <Dialog v-model:visible="visibleNameDialog" modal header="Edit Name" :style="{ width: '25rem' }" :contentStyle="{ overflowY: 'visible'}">
    <DialogEditLink v-model="nameValue" @on-save="saveSubnavTitle"/>
  </Dialog>

  <Dialog v-model:visible="visibleDialog" modal header="Edit Link" :style="{ width: '25rem' }" :contentStyle="{ overflowY: 'visible'}">
    <DialogEditLink v-model="linkValue" @on-save="saveLink"/>
  </Dialog>

  <Dialog v-model:visible="visibleNavigation" modal header="Edit Navigation Link" :style="{ width: '25rem' }" :contentStyle="{ overflowY: 'visible'}">
    <DialogEditLink :modelValue="props.modelValue" @on-save="saveNavigationLink"/>
  </Dialog>

  <ConfirmPopup>
    <template #icon>
      <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
    </template>
  </ConfirmPopup>
</div>
</template>

<style scoped lang="scss">
.ghost {
  opacity: 0.5;
  background-color: #e2e8f0;
  border: 2px dashed #4F46E5;
}
</style>
