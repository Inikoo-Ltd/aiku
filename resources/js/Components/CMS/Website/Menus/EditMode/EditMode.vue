<script setup lang="ts">
import { ref, toRaw, watch } from 'vue'
import draggable from "vuedraggable";
import Button from '@/Components/Elements/Buttons/Button.vue';
import Dialog from 'primevue/dialog';
import DialogEditLink from '@/Components/CMS/Website/Menus/EditMode/DialogEditLink.vue';
import { useConfirm } from "primevue/useconfirm";
import IconPicker from '@/Components/Pure/IconPicker.vue';

import { library } from '@fortawesome/fontawesome-svg-core';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faChevronUp, faTimes, faPlusCircle, faBars, faTrashAlt, faLink, faExclamation, faGlobe } from '@fas';
import { faExternalLink, faHeart } from '@far';
import PureInput from '@/Components/Pure/PureInput.vue';
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue';
import { v4 as uuidv4 } from "uuid"
import EmptyState from '@/Components/Utils/EmptyState.vue';
import DialogEditName from '@/Components/CMS/Website/Menus/EditMode/DialogEditName.vue';
import { faCompassDrafting } from '@fortawesome/free-solid-svg-icons';
import { faExclamationTriangle, faTimesCircle } from '@fal';
import ConfirmPopup from 'primevue/confirmpopup';
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'



library.add(faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt, faGlobe, faChevronUp);

interface navigation {
  label: String
  id: Number | String
  type: String
}

const props = defineProps<{
  modelValue: string | number | null | any
}>()

const confirm = useConfirm();
const visibleNameDialog = ref(false)
const visibleDialog = ref(false)
const visibleNavigation = ref(false)
const nameValue = ref<navigation | null>()
const linkValue = ref<navigation | null>()
const parentIdx = ref<Number>(0)
const linkIdx = ref<Number>(0)

const emits = defineEmits<{
  (e: 'update:modelValue', value: string | number): void
}>()

const addLink = (data: Object) => {
  data.links.push(
    { label: "New Link", link: "", id: uuidv4(), icon: null }
  )
  emits('update:modelValue', props.modelValue)
}


const changeType = (type: string, data: Object) => {
  if (type == 'multiple') data['subnavs'] = []
}

const addCard = () => {
  if (!props.modelValue.subnavs) props.modelValue.subnavs = []
  props.modelValue.subnavs.push(
    {
      title: "New Navigation",
      id: uuidv4(),
      links: [
        { label: "New Link", link: "", id: uuidv4(), icon: null }
      ],
    },
  )
}

const deleteNavCard = (index: number) => {
  props.modelValue.subnavs.splice(index, 1)
}

const onNameClick = (data = null, index = -1) => {
  visibleNameDialog.value = true
  nameValue.value = toRaw({ ...data, index: index })
}

const onChangeName = (data) => {
  props.modelValue.subnavs[data.index] = data
  visibleNameDialog.value = false
  nameValue.value = null
}

const onLinkClick = (data = null, parentIndex = -1, index = -1) => {
  visibleDialog.value = true
  parentIdx.value = parentIndex
  linkIdx.value = index
  linkValue.value = toRaw({ ...data })
}

const onChangeLink = (data) => {
  props.modelValue.subnavs[parentIdx.value].links[linkIdx.value] = {
    ...props.modelValue.subnavs[parentIdx.value].links[linkIdx.value],
    label: data.label,
    link: data.link,
  }
  linkValue.value = null
  parentIdx.value = -1
  linkIdx.value = -1
  visibleDialog.value = false
}

const editNavigation = () => {
  visibleNavigation.value = true
}

const onChangeNavigationLink = (data) => {
  const updatedData = {
    ...props.modelValue,
    label: data.label,
    link: data.link,
  };

  // Emit the updated data to the parent
  emits('update:modelValue', updatedData);

  // Close the dialog
  visibleNavigation.value = false;
};




watch(() => props.modelValue, (newValue) => {
  emits('update:modelValue', newValue)
}, { deep: true })

</script>

<template>
  <div class="bg-slate-50 p-6  max-w-4xl mx-auto">
    <!-- Card Utama: Form Navigasi Vertikal -->
    <section class="bg-white rounded-lg shadow-md p-6 mb-6 space-y-6">

      <!-- Navigation Title + Icon -->
      <header>
        <h2 class="font-medium text-gray-800 text-lg mb-4">Navigation Title</h2>
        <div class="flex items-center gap-3">
          <!-- IconPicker dalam tombol dengan border -->
          <button type="button"
            class="border border-gray-300 rounded px-3 py-2 cursor-pointer hover:border-blue-500 transition focus:outline-none focus:ring-2 focus:ring-blue-500"
            v-tooltip="'Icon'" aria-label="Select Icon">
            <IconPicker v-model="modelValue.icon" />
          </button>

          <!-- Input Title -->
          <input v-model="modelValue.label" type="text"
            class="flex-grow border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Enter modelValue Title" aria-label="Navigation Title" />
        </div>
      </header>

      <!-- Type Select -->
      <section>
        <h3 class="font-medium text-gray-800 text-md mb-3">Type</h3>
        <PureMultiselect :required="true" v-model="modelValue.type" label="label" value-prop="value" :options="[
          { label: 'Single', value: 'single' },
          { label: 'Multiple', value: 'multiple' }
        ]" @change="(e) => changeType(e, modelValue)" aria-label="Select navigation type" />
      </section>

      <!-- Link Section -->
      <section>
        <h3 class="font-medium text-gray-800 text-md mb-3">Link</h3>

        <!-- Tombol Set URL -->
        <div>
          <!-- If link href is not set -->
          <div v-if="!modelValue?.link?.href" @click="editNavigation"
            class="flex items-center justify-between bg-gray-100 p-3 rounded-md cursor-pointer" role="button"
            tabindex="0" aria-label="Set up navigation link" @keyup.enter="editNavigation">
            <span class="text-gray-500 hover:underline truncate">
              Not set up yet
            </span>
          </div>

          <!-- If link href is set -->
          <div v-else class="flex items-center justify-between bg-gray-100 p-3 rounded-md">
            <span class="text-blue-500 hover:underline truncate cursor-pointer" role="link" tabindex="0"
              @click="editNavigation" @keyup.enter="editNavigation" aria-label="Edit navigation link">
              {{ modelValue.link.href || 'https://' }}
            </span>

            <div class="flex items-center gap-2">
              <a v-if="modelValue.link.type === 'internal'" :href="modelValue.link.workshop" target="_blank"
                rel="noopener" class="p-1 text-gray-500 hover:text-blue-500" aria-label="Open internal workshop link">
                <FontAwesomeIcon :icon="faCompassDrafting" />
              </a>
              <a v-if="modelValue.link.href" :href="modelValue.link.href" target="_blank" rel="noopener"
                class="p-1 text-gray-500 hover:text-blue-500" aria-label="Open external link">
                <FontAwesomeIcon :icon="faExternalLink" />
              </a>
            </div>
          </div>
        </div>
      </section>

      <div class="border flex w-full mb-2"></div>
      <div v-if="modelValue && modelValue.type === 'multiple'" class="font-medium text-gray-800 text-lg mb-4">
        Subnavigation</div>
      <draggable v-if="modelValue && modelValue.type === 'multiple'" :list="modelValue.subnavs"
        class="flex flex-col gap-4" ghost-class="ghost" itemKey="id" handle=".drag-handle">
        <template #item="{ element, index }">
          <Disclosure>
            <template #default="{ open }">
              <article class="bg-white rounded-lg shadow-lg" :class="open ? 'ring-1 ring-blue-500' : ''">
                <DisclosureButton class="flex justify-between items-center w-full p-4 cursor-pointer">
                  <div class="flex items-center gap-3">
                    <!-- Drag Handle -->
                    <FontAwesomeIcon icon="fas fa-bars" class="drag-handle cursor-move text-gray-400"
                      aria-label="Drag handle" tabindex="0" />

                    <div class="text-md" @click.stop="() => onNameClick(element, index)" tabindex="0"
                      @keyup.enter="() => onNameClick(element, index)" role="button"
                      aria-label="Edit subnavigation title">
                      <span v-if="element.title" class="font-medium text-gray-800">{{ element.title }}</span>
                      <span v-else class="font-medium text-gray-400">Has no title</span>
                    </div>
                  </div>

                  <div class="flex items-center gap-3">
                    <FontAwesomeIcon v-if="element.links.length < 8" icon="fas fa-plus-circle"
                      class="cursor-pointer text-blue-500" @click.stop="() => addLink(element)" aria-label="Add link"
                      tabindex="0" @keyup.enter="() => addLink(element)" />
                    <FontAwesomeIcon icon="fas fa-trash-alt" class="cursor-pointer text-red-500"
                      @click.stop="() => deleteNavCard(index)" aria-label="Delete subnavigation" tabindex="0"
                      @keyup.enter="() => deleteNavCard(index)" />
                    <FontAwesomeIcon :icon="open ? 'fas fa-chevron-up' : 'fas fa-chevron-down'"
                      class="text-gray-400 hover:text-gray-600 transition" aria-label="Toggle subnavigation panel"
                      tabindex="0" @keyup.enter="$event.target.click()" />
                  </div>
                </DisclosureButton>

                <DisclosurePanel class="p-3 border-t border-gray-200">
                  <draggable :list="element.links" ghost-class="ghost" group="link" itemKey="id" :animation="200"
                    class="flex flex-col gap-y-2" handle=".link-drag-handle">
                    <template #item="{ element: link, index: linkIndex }">
                      <div class="flex items-center gap-2 p-2 bg-gray-50 rounded hover:bg-gray-100 transition">
                        <FontAwesomeIcon icon="fas fa-bars" class="link-drag-handle cursor-move text-gray-400 pr-2"
                          aria-label="Drag link handle" tabindex="0" />
                        <IconPicker v-model="link.icon" />

                        <div class="flex justify-between items-center w-full">
                          <div class="text-gray-500 hover:text-gray-600 hover:underline cursor-pointer text-xs"
                            @click.stop="() => onLinkClick(link, index, linkIndex)" tabindex="0"
                            @keyup.enter="() => onLinkClick(link, index, linkIndex)" role="button"
                            aria-label="Edit link label">
                            {{ link.label }}
                          </div>

                          <div class="flex items-center gap-3 cursor-pointer">
                            <a v-if="link?.link?.type == 'internal'" :href="link.link.workshop" target="_blank"
                              rel="noopener" aria-label="Open internal workshop link">
                              <FontAwesomeIcon :icon="faCompassDrafting"
                                class="text-gray-400 hover:text-gray-600 transition" />
                            </a>
                            <a v-if="link?.link?.href" :href="link?.link?.href" target="_blank" rel="noopener"
                              aria-label="Open external link">
                              <FontAwesomeIcon :icon="faExternalLink"
                                class="text-gray-400 hover:text-gray-600 transition" />
                            </a>
                            <span v-tooltip="'Delete'" @click.stop="() => element.links.splice(linkIndex, 1)"
                              class="text-red-400 hover:text-red-600 transition cursor-pointer" tabindex="0"
                              @keyup.enter="() => element.links.splice(linkIndex, 1)" role="button"
                              aria-label="Delete link">
                              <FontAwesomeIcon :icon="faTimesCircle" />
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
      <div v-if="modelValue?.type === 'multiple'" class="flex justify-end">
        <Button label="Add Subnavigation" type="create" v-tooltip="modelValue?.subnavs?.length >= 8 ? 'max 8 subnavigation' : 'add sub navigation'"
          :disabled="Array.isArray(modelValue.subnavs) && modelValue.subnavs.length >= 8" @click="addCard" full />
      </div>

    </section>

    <!-- Dialogs -->
    <Dialog v-model:visible="visibleNameDialog" modal header="Edit Name" :style="{ width: '25rem' }"  :contentStyle="{ overflowY: 'visible'}">
      <DialogEditName :data_form="nameValue" @on-save="onChangeName" />
    </Dialog>

    <Dialog v-model:visible="visibleDialog" modal header="Edit Link" :style="{ width: '25rem' }"  :contentStyle="{ overflowY: 'visible'}">
      <DialogEditLink :modelValue="linkValue" @on-save="onChangeLink" />
    </Dialog>

    <Dialog v-model:visible="visibleNavigation" modal header="Edit Link" :style="{ width: '25rem' }"  :contentStyle="{ overflowY: 'visible'}">
      <DialogEditLink :modelValue="toRaw({ ...modelValue })" @on-save="onChangeNavigationLink" />
    </Dialog>

    <ConfirmPopup>
      <template #icon>
        <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
      </template>
    </ConfirmPopup>
  </div>
</template>


<style scoped lang="scss" >
.ghost {
    opacity: 0.5;
    background-color: #e2e8f0;
    border: 2px dashed #4F46E5;
}

</style>
