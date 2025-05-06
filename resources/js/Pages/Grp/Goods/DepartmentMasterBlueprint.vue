<script setup lang="ts">
import { ref } from "vue";
import type { Component } from "vue";
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import axios from "axios";
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { faChevronCircleLeft, faChevronCircleRight, faImage, faEye, faEyeSlash, faExclamationTriangle, faSave } from '@far'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import DepartmentRender from '@/Components/CMS/Webpage/Department1/DepartmentRender.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInput from '@/Components/Pure/PureInput.vue'
import { useConfirm } from "primevue/useconfirm";
import { router } from "@inertiajs/vue3"
import { routeType } from '@/types/route'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import ConfirmDialog from 'primevue/confirmdialog';
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { faEdit } from "@fal";

const props = defineProps<{
    pageHead: {},
    department: {},
    update_route : routeType
    upload_image_route : routeType
    families: { data: Array<{ id: number, name: string }> },
    web_block_types: Array<string>
    web_block_types_families : {
        data : Array
    }
}>()

const departmentData = ref(props.department.data)
const usedTemplate = ref(props.web_block_types.data[0])
const isModalGallery = ref(false)
const isLoading = ref(false)
const isModalFamiliesPreview = ref(false)
const showPreviewFamilies = ref(props.web_block_types_families.data[0])
const confirm = useConfirm();
const departementEdit = ref(false)


const familiesOption = ref(
    props.families.data.map((item) => ({
        ...item,  // Spread the original item properties
        show: true,  // Add the show property to each item
        loading : false
    }))
)

const goToPrev = () => {
    console.log('Previous clicked')
}

const goToNext = () => {
    console.log('Next clicked')
}

const componentsDepartment: Record<string, Component> = {
    'department-1': DepartmentRender,
}

const getComponentDepartment = (componentName: string) => {
    return componentsDepartment[componentName]
}

const familyShow = (item) => {
    item.show = !item.show;
    /* router.patch(
    route(props.update_route.name, {...props.update_route.parameters, id : item.id}),
    {
        show : false
    },
    {
      preserveScroll: true,
      onStart: () => {
        item.loading = true;
      },
      onSuccess: () => {},
      onError: (errors) => {
        console.error('Save failed:', errors);
      },
      onFinish: () => {
        item.loading = false;
      },
    }
  ); */
}


const confirmDelete = (_event: MouseEvent, item: { id: number; name: string; show: boolean }) => {
  confirm.require({
    message: item.show
      ? `Are you sure you want to hide "${item.name}" from view Families?`
      : `Are you sure you want to show "${item.name}" in view Families?`,
    header: 'Confirm Action',
    icon: 'pi pi-exclamation-triangle',
    rejectProps: {
      label: 'Cancel',
      severity: 'secondary',
      outlined: true,
    },
    acceptProps: {
      label: item.show ? 'Yes, hide it' : 'Yes, show it',
      severity: 'danger',
    },
    accept: () => {
        familyShow(item)
    },
  });
};


const onSaveAll = () => {
  router.patch(
    route(props.update_route.name, props.update_route.parameters),
    {
        name: departmentData.value.name,
        description: departmentData.value.description,
        image_id : departmentData.value.image_id,
     /*  families: familiesOption.value.map(item => ({
        slug: item.slug,
        show: item.show,
      })), */
    },
    {
      preserveScroll: true,
      onStart: () => {
        isLoading.value = true;
      },
      onSuccess: () => {
        departementEdit.value = false
        departmentData.value = props.department.data
        // Success handler (optional)
      },
      onError: (errors) => {
        // Handle validation or server errors
        console.error('Save failed:', errors);
      },
      onFinish: () => {
        isLoading.value = false;
      },
    }
  );
};


const confirmSave = () => {
    confirm.require({
        message: 'Save changes? This will affect all webpages.',
        header: 'Confirm Save',
        icon: 'pi pi-exclamation-triangle',
        rejectProps: {
            label: 'Cancel',
            severity: 'secondary',
            outlined: true,
        },
        acceptProps: {
            label: 'Save',
            severity: 'primary',
        },
        accept: () => {
            onSaveAll();
        },
    });
};

import { router } from '@inertiajs/vue3';

const onUpload = async (files: File[], clear: () => void) => {
  if (!files.length) return;

  const formData = new FormData();
  files.forEach((file) => {
    formData.append('image', file);
  });

  // Optional: Debug log FormData contents
  console.log('FormData contents:');
  for (const [key, value] of formData.entries()) {
    console.log(key, value);
  }

  router.post(
    route(props.upload_image_route.name, props.upload_image_route.parameters),
    formData,
    {
      forceFormData: true, // Ensure Inertia treats it as FormData
      onSuccess: () => {
        console.log('Upload successful');
        departmentData.value = props.department.data
        isModalGallery.value = false;
        clear(); // Reset input after success
      },
      onError: (errors) => {
        console.error('Image upload failed:', errors);
      },
    }
  );
};



</script>


<template>
    <PageHeading :data="pageHead">
       <!--  <template #button-save="{ action }">
            <Button type="save" @click="() => confirmSave(action.route)" />
        </template> -->
    </PageHeading>

    <div class="grid grid-cols-1 lg:grid-cols-[30%_1fr] gap-6 px-4 pb-8 m-5 ">
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-semibold">Departement</h3>
                <Button v-if="!departementEdit" label="Edit Departement" :size="'xs'" :type="'primary'" :icon="faEdit" @click="departementEdit = true"/>
                <div v-else class="flex gap-3">
                    <Button label="Cancel" :size="'xs'" :type="'tertiary'" :icon="faEdit" @click="departementEdit = false"/>
                    <Button label="Save" :size="'xs'" :type="'primary'" :icon="faSave"  @click="() => confirmSave()" />
                </div>
            </div>
            <!-- Navigation & Preview -->
            <div class="flex items-center justify-between mb-6">
                <button @click="goToPrev" aria-label="Previous">
                    <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
                </button>
                <div class="flex-1 mx-4">
                    <component :is="getComponentDepartment(usedTemplate.code)" :data="departmentData" />
                </div>
                <button @click="goToNext" aria-label="Next">
                    <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
                </button>
            </div>

            <!-- Form -->
            <div v-if="departementEdit" class="border-t pt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                    <PureInput v-model="departmentData.name" type="text" placeholder="Enter name" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <PureTextarea v-model="departmentData.description" type="text" :rows="4" placeholder="Enter name" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                    <Button label="Upload Image" :type="'tertiary'" :icon="faImage" @click="isModalGallery = true" />
                </div>
            </div>

            <div v-else="departementEdit" class="border-t pt-4 space-y-4 text-sm text-gray-700">
                <div class="text-sm font-medium">
                    <span>{{ departmentData.name || 'No label' }}</span>
                </div>
                <div class="text-md">
                    <span class="text-gray-400">{{ departmentData.description || 'No description' }}</span>
                </div>
            </div>
        </div>

        <!-- Families List -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 " >
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-semibold">Families List</h3>
                <Button label="Preview" :size="'xs'" :type="'tertiary'" :icon="faEye" @click="isModalFamiliesPreview = true"/>
            </div>

            <ul class="divide-y divide-gray-100 max-h-[calc(100vh-30vh)] min-h-12 overflow-auto">
                <li v-for="(item, index) in familiesOption" :key="item.slug"
                    class="flex items-center justify-between py-4 hover:bg-gray-50 px-2 rounded-lg transition">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-gray-100 flex items-center justify-center rounded-lg overflow-hidden">
                            <img v-if="item.image" :src="item.image" alt="Item Image"
                                class="w-full h-full object-cover" />
                            <FontAwesomeIcon v-else :icon="faImage" class="text-gray-400 text-xl" />
                        </div>
                        <div>
                            <div class="font-medium text-gray-800">{{ item.name }}</div>
                            <div class="text-sm text-gray-500">{{ item.code }}</div>
                        </div>
                    </div>

                    <!-- Action -->
                    <div class="text-gray-500 hover:text-primary cursor-pointer transition"
                        @click="(e) => confirmDelete(e, item)" title="Toggle visibility">
                        <FontAwesomeIcon :icon="item.show ? faEye : faEyeSlash" />
                    </div>
                </li>

            </ul>
        </div>
    </div>

    <ConfirmDialog>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>

    <!-- Gallery Modal -->
    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
        <GalleryManagement 
            :uploadRoute="{ name: '', parameters: '' }" 
            :closePopup="() => (isModalGallery = false)" 
            :submitUpload="onUpload" 
            :maxSelected="1"
            @submitSelectedImages="(e)=>{departmentData.image_id = e[0].id, isModalGallery = false, onSaveAll()}" 
        />
    </Modal>


    <Modal :isOpen="isModalFamiliesPreview" @onClose="() => (isModalFamiliesPreview = false)" width="w-3/4">
        <div class="flex items-center justify-between mb-6">
            <button @click="goToPrev" aria-label="Previous">
                <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
            </button>
            <div class="flex-1 mx-4">
                <component  :is="getIrisComponent(showPreviewFamilies.code)" :fieldValue="{...showPreviewFamilies.data, family: familiesOption.filter((item) => item.show)}" />
            </div>
            <button @click="goToNext" aria-label="Next">
                <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
            </button>
        </div>
    </Modal>
</template>
