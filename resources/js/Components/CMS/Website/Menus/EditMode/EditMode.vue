<script setup lang="ts">
import { ref, watch } from 'vue'
import draggable from 'vuedraggable'
import { v4 as uuid } from 'uuid'
import cloneDeep from 'lodash-es/cloneDeep'

import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'
import ConfirmPopup from 'primevue/confirmpopup'
import { useConfirm } from 'primevue/useconfirm'
import DialogEditLink from '@/Components/CMS/Website/Menus/EditMode/DialogEditLink.vue'
import IconPicker from '@/Components/Pure/IconPicker.vue'
import UploadImage from '@/Components/Pure/UploadImage.vue'
import { ctrans } from '@/Composables/useTrans'
import { routeType } from '@/types/route'

import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faChevronUp, faTimes, faPlusCircle, faBars, faTrashAlt, faGlobe } from '@fas'
import { faHeart } from '@fortawesome/free-regular-svg-icons'
import { faGripVertical, faLink, faPencil, faPlus, faExternalLink, faDraftingCompass, faTrashAlt as falTrashAlt } from '@fal'

library.add(
  faChevronRight, faSignOutAlt, faShoppingCart,
  faHeart, faSearch, faChevronDown,
  faChevronUp, faTimes, faPlusCircle,
  faBars, faTrashAlt, faGlobe
)

interface MenuLink {
  href?: string
  type?: string
  workshop?: string
  [key: string]: any
}

interface NavigationLink {
  id: string
  label: string
  icon?: any
  link?: MenuLink
}

interface SubNavigation {
  id: string
  title: string
  link?: MenuLink
  links: NavigationLink[]
}

interface Navigation {
  label: string
  type: string
  icon?: any
  link?: MenuLink
  image?: any
  image_position?: number
  subnavs?: SubNavigation[]
}

type LinkDialogTarget =
  | { kind: 'navigation' }
  | { kind: 'subnav', subnavIndex: number }
  | { kind: 'link', subnavIndex: number, linkIndex: number }

const maxSubnavs = 8
const maxLinksPerSubnav = 8

const props = defineProps<{ modelValue: Navigation, uploadImageRoute: routeType }>()
const emits = defineEmits<{
  (e: 'update:modelValue', val: Navigation): void
}>()

const confirm = useConfirm()
const localNav = ref<Navigation>(cloneDeep(props.modelValue))

watch(
  () => props.modelValue,
  (newVal) => {
    localNav.value = cloneDeep(newVal)
  }
)

const commit = (patch: Partial<Navigation>) => {
  localNav.value = { ...localNav.value, ...patch }
  emits('update:modelValue', cloneDeep(localNav.value))
}

const updateSubnavs = (updater: (subnavs: SubNavigation[]) => void) => {
  const subnavs = cloneDeep(localNav.value.subnavs ?? [])
  updater(subnavs)
  commit({ subnavs })
}

const typeOptions = [
  { label: ctrans('Single link'), value: 'single' },
  { label: ctrans('Dropdown'), value: 'multiple' },
]

const imagePositionOptions = [1, 2, 3, 4]

const changeType = (type: string) => {
  if (type === localNav.value.type) {
    return
  }
  commit({
    type,
    subnavs: type === 'multiple' ? (localNav.value.subnavs ?? []) : undefined
  })
}

const addSubNavigation = () => {
  updateSubnavs((subnavs) => {
    subnavs.push({
      id: uuid(),
      title: ctrans('New group'),
      links: [{ id: uuid(), label: ctrans('New link'), icon: null, link: {} }]
    })
  })
}

const addLink = (subnavIndex: number) => {
  updateSubnavs((subnavs) => {
    subnavs[subnavIndex]?.links.push({ id: uuid(), label: ctrans('New link'), icon: null, link: {} })
  })
}

const confirmDelete = (event: Event, message: string, onAccept: () => void) => {
  confirm.require({
    group: 'menu-edit-mode',
    target: event.currentTarget as HTMLElement,
    message,
    rejectProps: { label: ctrans('Cancel'), severity: 'secondary', outlined: true, size: 'small' },
    acceptProps: { label: ctrans('Delete'), severity: 'danger', size: 'small' },
    accept: onAccept,
  })
}

const deleteSubNavigation = (event: Event, subnavIndex: number) => {
  confirmDelete(event, ctrans('Delete this group and its links?'), () => {
    updateSubnavs((subnavs) => {
      subnavs.splice(subnavIndex, 1)
    })
  })
}

const deleteLink = (subnavIndex: number, linkIndex: number) => {
  updateSubnavs((subnavs) => {
    subnavs[subnavIndex]?.links.splice(linkIndex, 1)
  })
}

const setLinkIcon = (subnavIndex: number, linkIndex: number, icon: any) => {
  updateSubnavs((subnavs) => {
    subnavs[subnavIndex].links[linkIndex].icon = icon
  })
}

const reorderSubnavs = (subnavs: SubNavigation[]) => {
  commit({ subnavs: cloneDeep(subnavs) })
}

const reorderLinks = (subnavIndex: number, links: NavigationLink[]) => {
  updateSubnavs((subnavs) => {
    subnavs[subnavIndex].links = cloneDeep(links)
  })
}

const linkDialogTarget = ref<LinkDialogTarget | null>(null)
const linkDialogValue = ref<{ label?: string, link?: MenuLink } | null>(null)
const linkDialogTitle = ref('')

const openLinkDialog = (target: LinkDialogTarget) => {
  linkDialogTarget.value = target

  if (target.kind === 'navigation') {
    linkDialogTitle.value = ctrans('Edit navigation link')
    linkDialogValue.value = { label: localNav.value.label, link: localNav.value.link }
  } else if (target.kind === 'subnav') {
    const subnav = localNav.value.subnavs?.[target.subnavIndex]
    linkDialogTitle.value = ctrans('Edit group')
    linkDialogValue.value = { label: subnav?.title, link: subnav?.link }
  } else {
    const link = localNav.value.subnavs?.[target.subnavIndex]?.links[target.linkIndex]
    linkDialogTitle.value = ctrans('Edit link')
    linkDialogValue.value = { label: link?.label, link: link?.link }
  }
}

const closeLinkDialog = () => {
  linkDialogTarget.value = null
  linkDialogValue.value = null
}

const saveLinkDialog = (value: { label?: string, link?: MenuLink }) => {
  const target = linkDialogTarget.value
  if (!target) {
    return
  }

  if (target.kind === 'navigation') {
    commit({ label: value.label ?? '', link: value.link })
  } else if (target.kind === 'subnav') {
    updateSubnavs((subnavs) => {
      subnavs[target.subnavIndex].title = value.label ?? ''
      subnavs[target.subnavIndex].link = value.link
    })
  } else {
    updateSubnavs((subnavs) => {
      const link = subnavs[target.subnavIndex].links[target.linkIndex]
      subnavs[target.subnavIndex].links[target.linkIndex] = { ...link, label: value.label ?? '', link: value.link }
    })
  }

  closeLinkDialog()
}
</script>

<template>
  <div class="space-y-4 text-sm">
    <section class="space-y-3">
      <div>
        <label class="mb-1 block text-xs font-medium text-gray-600">{{ ctrans('Title') }}</label>
        <div class="flex items-center gap-2">
          <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-gray-300 hover:border-indigo-400">
            <IconPicker :model-value="localNav.icon" @update:model-value="icon => commit({ icon })" />
          </div>
          <input :value="localNav.label" @input="e => commit({ label: (e.target as HTMLInputElement).value })" type="text"
            :placeholder="ctrans('Navigation title')"
            class="h-9 w-full rounded-md border border-gray-300 px-3 text-sm focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500" />
        </div>
      </div>

      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">{{ ctrans('Type') }}</label>
          <div class="flex rounded-md bg-gray-100 p-0.5">
            <button v-for="option in typeOptions" :key="option.value" type="button"
              class="flex-1 rounded px-2 py-1.5 text-xs font-medium transition-colors"
              :class="localNav.type === option.value ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
              @click="changeType(option.value)">
              {{ option.label }}
            </button>
          </div>
        </div>

        <div>
          <label class="mb-1 block text-xs font-medium text-gray-600">{{ ctrans('Link') }}</label>
          <div class="flex h-[34px] items-center gap-1 rounded-md border border-gray-300 pl-2 pr-1">
            <FontAwesomeIcon :icon="faLink" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
            <button type="button" class="min-w-0 flex-1 truncate text-left text-xs"
              :class="localNav.link?.href ? 'text-indigo-600 hover:underline' : 'text-gray-400'"
              @click="openLinkDialog({ kind: 'navigation' })">
              {{ localNav.link?.href || ctrans('Add link') }}
            </button>
            <a v-if="localNav.link?.type === 'internal' && localNav.link?.workshop" :href="localNav.link.workshop" target="_blank" rel="noopener"
              class="rounded p-1 text-gray-400 hover:text-gray-700" v-tooltip="ctrans('Open in workshop')">
              <FontAwesomeIcon :icon="faDraftingCompass" class="text-xs" fixed-width />
            </a>
            <a v-if="localNav.link?.href" :href="localNav.link.href" target="_blank" rel="noopener"
              class="rounded p-1 text-gray-400 hover:text-gray-700" v-tooltip="ctrans('Open link')">
              <FontAwesomeIcon :icon="faExternalLink" class="text-xs" fixed-width />
            </a>
          </div>
        </div>
      </div>
    </section>

    <template v-if="localNav.type === 'multiple'">
      <section class="rounded-md border border-gray-200 p-3">
        <div class="mb-2 flex items-center justify-between">
          <span class="text-xs font-medium text-gray-600">{{ ctrans('Dropdown image') }}</span>
          <div v-if="localNav.image" class="flex items-center gap-1 text-xs text-gray-500">
            {{ ctrans('Column') }}
            <div class="flex rounded-md bg-gray-100 p-0.5">
              <button v-for="position in imagePositionOptions" :key="position" type="button"
                class="h-6 w-6 rounded text-xs font-medium"
                :class="(localNav.image_position ?? 4) === position ? 'bg-white text-gray-900 shadow-sm' : 'text-gray-500 hover:text-gray-800'"
                @click="commit({ image_position: position })">
                {{ position }}
              </button>
            </div>
          </div>
        </div>
        <UploadImage :model-value="localNav.image" :uploadRoutes="uploadImageRoute" option-value="value"
          option-label="label" @update:model-value="image => commit({ image })" />
      </section>

      <section>
        <div class="mb-2 flex items-center justify-between">
          <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">
            {{ ctrans('Groups') }}
            <span class="ml-1 rounded bg-gray-200 px-1.5 py-0.5 text-[10px] text-gray-600 tabular-nums">
              {{ localNav.subnavs?.length ?? 0 }}/{{ maxSubnavs }}
            </span>
          </span>
          <Button :label="ctrans('Add group')" :icon="faPlus" type="tertiary" size="xxs"
            :disabled="(localNav.subnavs?.length ?? 0) >= maxSubnavs" @click="addSubNavigation" />
        </div>

        <draggable :modelValue="localNav.subnavs ?? []" @update:modelValue="reorderSubnavs" class="space-y-2"
          ghost-class="ghost" itemKey="id" handle=".subnav-drag-handle" :animation="150">
          <template #item="{ element: subnav, index: subnavIndex }">
            <article class="rounded-md border border-gray-200 bg-white">
              <header class="group flex h-9 items-center gap-1 border-b border-gray-100 pl-1 pr-1.5">
                <span class="subnav-drag-handle flex h-full w-6 cursor-grab items-center justify-center text-gray-400 hover:text-gray-700">
                  <FontAwesomeIcon :icon="faGripVertical" class="text-xs" fixed-width aria-hidden="true" />
                </span>
                <button type="button" class="min-w-0 flex-1 truncate text-left text-sm font-semibold"
                  :class="subnav.title ? 'text-gray-800' : 'text-gray-400'"
                  @click="openLinkDialog({ kind: 'subnav', subnavIndex })">
                  {{ subnav.title || ctrans('Untitled group') }}
                </button>
                <FontAwesomeIcon v-if="subnav.link?.href" :icon="faLink" class="text-[10px] text-gray-400"
                  v-tooltip="subnav.link.href" fixed-width />
                <button type="button" class="h-6 w-6 rounded text-gray-400 hover:bg-gray-100 hover:text-gray-700"
                  v-tooltip="ctrans('Edit group')" @click="openLinkDialog({ kind: 'subnav', subnavIndex })">
                  <FontAwesomeIcon :icon="faPencil" class="text-xs" fixed-width />
                </button>
                <button type="button" class="h-6 w-6 rounded text-red-400 hover:bg-red-50 hover:text-red-600"
                  v-tooltip="ctrans('Delete group')" @click="(event) => deleteSubNavigation(event, subnavIndex)">
                  <FontAwesomeIcon :icon="falTrashAlt" class="text-xs" fixed-width />
                </button>
              </header>

              <draggable :modelValue="subnav.links" @update:modelValue="(links) => reorderLinks(subnavIndex, links)"
                ghost-class="ghost" itemKey="id" handle=".link-drag-handle" :animation="150" class="divide-y divide-gray-100">
                <template #item="{ element: link, index: linkIndex }">
                  <div class="group flex h-8 items-center gap-1 pl-1 pr-1.5">
                    <span class="link-drag-handle flex h-full w-6 cursor-grab items-center justify-center text-gray-300 hover:text-gray-600">
                      <FontAwesomeIcon :icon="faGripVertical" class="text-[10px]" fixed-width aria-hidden="true" />
                    </span>
                    <div class="flex h-6 w-6 shrink-0 items-center justify-center text-xs">
                      <IconPicker :model-value="link.icon" @update:model-value="icon => setLinkIcon(subnavIndex, linkIndex, icon)" />
                    </div>
                    <button type="button" class="min-w-0 flex-1 truncate text-left text-xs text-gray-700 hover:text-indigo-600"
                      @click="openLinkDialog({ kind: 'link', subnavIndex, linkIndex })">
                      {{ link.label || ctrans('Untitled link') }}
                    </button>
                    <FontAwesomeIcon :icon="faLink" class="text-[10px]" fixed-width
                      :class="link.link?.href ? 'text-indigo-400' : 'text-gray-300'"
                      v-tooltip="link.link?.href || ctrans('No link yet')" />
                    <button type="button"
                      class="h-6 w-6 rounded text-gray-400 opacity-0 hover:bg-red-50 hover:text-red-600 group-hover:opacity-100"
                      v-tooltip="ctrans('Remove link')" @click="deleteLink(subnavIndex, linkIndex)">
                      <FontAwesomeIcon :icon="faTimes" class="text-xs" fixed-width />
                    </button>
                  </div>
                </template>
              </draggable>

              <button v-if="subnav.links.length < maxLinksPerSubnav" type="button"
                class="flex w-full items-center gap-1.5 border-t border-gray-100 px-3 py-1.5 text-xs text-gray-500 hover:bg-gray-50 hover:text-indigo-600"
                @click="addLink(subnavIndex)">
                <FontAwesomeIcon :icon="faPlus" class="text-[10px]" fixed-width aria-hidden="true" />
                {{ ctrans('Add link') }}
              </button>
            </article>
          </template>
        </draggable>

        <div v-if="!localNav.subnavs?.length"
          class="rounded-md border border-dashed border-gray-300 py-6 text-center text-xs text-gray-500">
          {{ ctrans('No groups yet. Add a group to build the dropdown.') }}
        </div>
      </section>
    </template>

    <Dialog :visible="!!linkDialogTarget" @update:visible="(visible) => { if (!visible) closeLinkDialog() }"
      modal :header="linkDialogTitle" :style="{ width: '28rem' }" :contentStyle="{ overflowY: 'visible' }">
      <DialogEditLink v-if="linkDialogValue" :modelValue="linkDialogValue" @on-save="saveLinkDialog" @on-cancel="closeLinkDialog" />
    </Dialog>

    <ConfirmPopup group="menu-edit-mode" />
  </div>
</template>

<style scoped lang="scss">
.ghost {
  opacity: 0.5;
  background-color: #e2e8f0;
  border: 1px dashed #4F46E5;
}
</style>
