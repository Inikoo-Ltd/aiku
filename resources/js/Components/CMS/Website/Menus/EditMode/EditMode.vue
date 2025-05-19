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
import { faChevronRight, faSignOutAlt, faShoppingCart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt, faLink, faExclamation ,faGlobe} from '@fas';
import { faExternalLink, faHeart } from '@far';
import PureInput from '@/Components/Pure/PureInput.vue';
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue';
import { v4 as uuidv4 } from "uuid"
import EmptyState from '@/Components/Utils/EmptyState.vue';
import DialogEditName from '@/Components/CMS/Website/Menus/EditMode/DialogEditName.vue';
import { faCompassDrafting } from '@fortawesome/free-solid-svg-icons';
import { faExclamationTriangle, faTimesCircle } from '@fal';
import ConfirmPopup from 'primevue/confirmpopup';

library.add(faChevronRight, faSignOutAlt, faShoppingCart, faHeart, faSearch, faChevronDown, faTimes, faPlusCircle, faBars, faTrashAlt,faGlobe);

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
    { label: "New Link", link: "", id: uuidv4(), icon:null }
  )
  emits('update:modelValue',props.modelValue)
}


const changeType = (type: string, data: Object) => {
  if (type == 'multiple') data['subnavs'] = []
}

const addCard = () => {
  if(!props.modelValue.subnavs) props.modelValue.subnavs = []
  props.modelValue.subnavs.push(
    {
      title: "New Navigation",
      id: uuidv4(),
      links: [
      { label: "New Link", link: "", id: uuidv4(), icon:null }
      ],
    },
  )
}

const deleteNavCard = (index : number) => {
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




const confirmDelete = (event,data,index) => {
    confirm.require({
        target: event.currentTarget,
        message: 'Are you sure you want to delete ?',
        rejectProps: {
            label: 'No',
            severity: 'secondary',
            outlined: true
        },
        acceptProps: {
            label: 'Yes'
        },
        accept: () => {
          data.links.splice(index,1)
        },
    });
};

watch(()=>props.modelValue,(newValue)=>{
emits('update:modelValue',newValue)
},{deep:true})

</script>

<template>
  <div class="bg-slate-50 min-h-screen p-6">
    <div class="grid grid-flow-row-dense grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
      <!-- Sidebar  navigasi -->
      <div
        v-if="modelValue"
        class="bg-white rounded-lg shadow-md p-6 transition-transform duration-300 hover:scale-105"
      >
        <h2 class="font-bold text-gray-800 text-lg mb-4">Navigation Title</h2>
        <div class="flex items-center gap-3">
          <IconPicker v-model="modelValue.icon" />
          <input
            v-model="modelValue.label"
            class="w-full border border-gray-300 rounded-md px-3 py-2 focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
            placeholder="Enter modelValue Title"
          />
        </div>
      </div>


      <!-- Multiselect navigasi -->
      <div
        v-if="modelValue"
        class="bg-white rounded-lg shadow-md p-6 transition-transform duration-300 hover:scale-105"
      >
        <h3 class="font-bold text-gray-800 text-md mb-3">Type</h3>
        <PureMultiselect
          :required="true"
          v-model="modelValue.type"
          label="label"
          value-prop="value"
          :options="[
            { label: 'Single', value: 'single' },
            { label: 'Multiple', value: 'multiple' }
          ]"
          @change="(e) => changeType(e, modelValue)"
        />
      </div>

      <!-- Link  Single -->
      <div
        v-if="modelValue"
        class="bg-white rounded-lg shadow-md p-6 transition-transform duration-300 hover:scale-105"
      >
        <h3 class="font-bold text-gray-800 text-md mb-3">Link</h3>
        <div v-if="!modelValue?.link?.href">
          <Button
            label="Set Url"
            :icon="faLink"
            class="p-button-sm p-button-text"
            @click="editNavigation"
          />
        </div>
        <div v-else class="flex items-center justify-between bg-gray-100 p-3 rounded-md">
          <span
            class="text-blue-500 hover:underline truncate"
           
            target="_blank"
            @click="(e)=>editNavigation(e)"
          >
            {{ modelValue?.link?.href || 'https://' }}
          </span>
          <div class="flex items-center gap-2">
            <a
              v-if="modelValue?.link?.type === 'internal'"
              :href="modelValue.link.workshop"
              target="_blank"
              class="p-1 text-gray-500 hover:text-blue-500"
            >
              <FontAwesomeIcon :icon="faCompassDrafting" />
            </a>
            <a
              v-if="modelValue?.link?.href"
              :href="modelValue?.link?.href"
              target="_blank"
              class="p-1 text-gray-500 hover:text-blue-500"
            >
              <FontAwesomeIcon :icon="faExternalLink" />
            </a>
          </div>
        </div>
      </div>

      <!-- Tambahkan Card -->
      <div v-if="modelValue && modelValue.type === 'multiple'"
        class="bg-white rounded-lg shadow-md p-6 transition-transform duration-300 hover:scale-105"
      >
        <h3 class="font-bold text-gray-800 text-md mb-3">Action</h3>
        <Button
          label="Add Card"
          type="create"
          @click="addCard"
        />
      </div>
    </div>

    <!-- Draggable subnavs -->
    <draggable
      v-if="modelValue && modelValue.type === 'multiple'"
      :list="modelValue.subnavs"
      class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mt-6"
      ghost-class="ghost"
      itemKey="id"
    >
      <template #item="{ element, index }">
        <div class="bg-white rounded-lg shadow-lg p-6">
          <div class="flex justify-between items-center">
            <h4
              class="font-bold text-gray-800 text-md cursor-pointer"
              @click="() => onNameClick(element, index)"
            >
              {{ element.title }}
            </h4>
            <div class="flex items-center gap-2">
              <FontAwesomeIcon
                v-if="element.links.length < 8"
                icon="fas fa-plus-circle"
                class="cursor-pointer text-blue-500"
                @click="() => addLink(element)"
              />
              <FontAwesomeIcon
                icon="fas fa-trash-alt"
                class="cursor-pointer text-red-500"
                @click="() => deleteNavCard(index)"
              />
            </div>
          </div>
          <draggable :list="element.links" ghost-class="ghost" group="link" itemKey="id" :animation="200"
            class="flex flex-col gap-y-2 p-3 relative">
            <template #item="{ element: link, index: linkIndex }">

              <div class="flex items-center gap-2 p-2 bg-gray-50 rounded hover:bg-gray-100 transition">
                <!-- Ikon bar -->
                <font-awesome-icon icon="fas fa-bars" class="text-[13px] text-gray-400 pr-2"></font-awesome-icon>

                <IconPicker v-model="link.icon" />

                <!-- Konten utama -->
                <div class="flex justify-between items-center w-full">
                  <!-- Tautan -->
                  <div class="text-gray-500 hover:text-gray-600 hover:underline cursor-pointer text-xs"
                    @click="() => onLinkClick(link, index, linkIndex)">
                    {{ link.label }}
                  </div>

                  <!-- Ikon tambahan -->
                  <div class="flex items-center gap-3 cursor-pointer">
                    <a v-if="link?.link?.type == 'internal'" :href="link.link.workshop" target="_blank">
                      <font-awesome-icon :icon="faCompassDrafting"
                        class="text-gray-400 hover:text-gray-600 transition"></font-awesome-icon>
                    </a>
                    <a v-if="link?.link?.href" :href="link?.link?.href" target="_blank">
                      <font-awesome-icon :icon="faExternalLink"
                        class="text-gray-400 hover:text-gray-600 transition"></font-awesome-icon>
                    </a>

                    <span v-tooltip="'Delete'" @click="(e)=>confirmDelete(e,element,linkIndex)">
                      <font-awesome-icon :icon="faTimesCircle"
                        class="text-red-400 hover:text-red-600 transition"></font-awesome-icon>
                    </span>
                  </div>
                </div>
              </div>
            </template>
          </draggable>
        </div>
      </template>
    </draggable>


    <div v-if="modelValue?.subnavs?.length == 0 && modelValue.type == 'multiple'">
      <EmptyState :data="{ title: 'you dont have Any modelValue', description: '' }" />
    </div>


    <Dialog v-model:visible="visibleNameDialog" modal header="Edit Name" :style="{ width: '25rem' }">
      <DialogEditName :data_form="nameValue" @on-save="onChangeName" />
    </Dialog>

    <Dialog v-model:visible="visibleDialog" modal header="Edit Link" :style="{ width: '25rem' }">
      <DialogEditLink :modelValue="linkValue" @on-save="onChangeLink" />
    </Dialog>

    <Dialog v-model:visible="visibleNavigation" modal header="Edit Link" :style="{ width: '25rem' }">
      <DialogEditLink :modelValue="toRaw({ ...modelValue })" @on-save="onChangeNavigationLink" />
    </Dialog>

    <ConfirmPopup>
      <template #icon>
        <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
      </template>
    </ConfirmPopup>


  </div>
</template>


<style scss scoped>
.p-confirmpopup {
  box-shadow: none;
}

</style>
