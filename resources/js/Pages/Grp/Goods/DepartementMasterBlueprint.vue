<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { faChevronCircleLeft, faChevronCircleRight, faImage, faEye, faEyeSlash } from '@far'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import DepartementRender from '@/Components/CMS/Webpage/Department1/DepartementRender.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInput from '@/Components/Pure/PureInput.vue'
import { useConfirm } from "primevue/useconfirm";

const props = defineProps<{
    pageHead: {},
    department: {},
    families: { data: Array<{ id: number, name: string }> },
    web_block_types: Array<string>
}>()

const department = ref(props.department.data)
const usedTemplate = ref(props.web_block_types.data[0])
const isModalGallery = ref(false)
const selectedItems = ref([])
console.log(props)
const confirm = useConfirm();
const handleImageUpload = (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (file) {
        console.log('Uploaded:', file.name)
    }
}

const familiesOption = ref(
  props.families.data.map((item) => ({
    ...item,  // Spread the original item properties
    show: true,  // Add the show property to each item
  }))
)

const goToPrev = () => {
    console.log('Previous clicked')
}

const goToNext = () => {
    console.log('Next clicked')
}

const components: Record<string, Component> = {
    'department-1': DepartementRender,
}

const getComponent = (componentName: string) => {
    return components[componentName]
}

const confirmDelete = (event: MouseEvent, item: { id: number; name: string; show: boolean }) => {
  confirm.require({
    target: event.currentTarget as HTMLElement,
    message: `Are you sure you want to hide "${item.name}" from view?`,
    rejectProps: {
      label: 'Cancel',
      severity: 'secondary',
      outlined: true,
    },
    acceptProps: {
      label: 'Yes, hide it',
      severity: 'danger',
    },
    accept: () => {
      item.show = false; // Toggle visibility
    },
  });
};

</script>


<template>
    <PageHeading :data="pageHead">
      <template #button-save>
        <Button type="save" />
      </template>
    </PageHeading>
  
    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 px-4 pb-8 m-5">
      <!-- Sidebar -->
      <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
        <!-- Navigation & Preview -->
        <div class="flex items-center justify-between mb-6">
          <button @click="goToPrev" aria-label="Previous">
            <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
          </button>
          <div class="flex-1 mx-4">
            <component :is="getComponent(usedTemplate.code)" :data="department" />
          </div>
          <button @click="goToNext" aria-label="Next">
            <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
          </button>
        </div>
  
        <!-- Form -->
        <div class="border-t pt-4 space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
            <PureInput v-model="department.name" type="text" placeholder="Enter name" />
          </div>
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
            <Button label="Upload Image" :type="'tertiary'" :icon="faImage" @click="isModalGallery = true" />
          </div>
        </div>
      </div>
  
      <!-- Families List -->
      <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
        <h3 class="text-xl font-semibold mb-4 border-b">Families List</h3>
        <ul class="divide-y divide-gray-100">
            <li
  v-for="(item, index) in familiesOption"
  :key="item.slug"
  class="flex items-center justify-between py-4 hover:bg-gray-50 px-2 rounded-lg transition"
>
  <div class="flex items-center gap-4">
    <div class="w-12 h-12 bg-gray-100 flex items-center justify-center rounded-lg overflow-hidden">
      <img
        v-if="item.image"
        :src="item.image"
        alt="Item Image"
        class="w-full h-full object-cover"
      />
      <FontAwesomeIcon v-else :icon="faImage" class="text-gray-400 text-xl" />
    </div>
    <div>
      <div class="font-medium text-gray-800">{{ item.name }}</div>
      <div class="text-sm text-gray-500">{{ item.code }}</div>
    </div>
  </div>

  <!-- Action -->
  <div
    class="text-gray-500 hover:text-primary cursor-pointer transition"
    @click="(e) => confirmDelete(e, item)"
    title="Toggle visibility"
  >
    <FontAwesomeIcon :icon="item.show ? faEye : faEyeSlash" />
  </div>
</li>

        </ul>
      </div>
    </div>
  
    <!-- Gallery Modal -->
    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
      <GalleryManagement
        :uploadRoute="{ name: '', parameters: '' }"
        :closePopup="() => (isModalGallery = false)"
      />
    </Modal>
  </template>
  
