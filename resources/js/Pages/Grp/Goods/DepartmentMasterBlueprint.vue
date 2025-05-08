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
import { router, useForm } from "@inertiajs/vue3"
import { routeType } from '@/types/route'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import ConfirmDialog from 'primevue/confirmdialog';
import { getIrisComponent } from '@/Composables/getIrisComponents'
import { faEdit } from "@fal";
import ToggleSwitch from 'primevue/toggleswitch';
import { notify } from "@kyvg/vue3-notification";
import SetVisibleList from '@/Components/Departement&Family/SetVisibleList.vue';

const props = defineProps<{
    pageHead: {},
    department: {},
    update_route: routeType
    upload_image_route: routeType
    families: { data: Array<{ id: number, name: string }> },
    web_block_types: Object
    web_block_types_families: {
        data: Array
    }
}>()

const departmentForm = useForm(props.department.data)
const usedTemplate = ref(props.web_block_types.data[0])
const isModalGallery = ref(false)
const isLoading = ref(false)
const isModalFamiliesPreview = ref(false)
const showPreviewFamilies = ref(props.web_block_types_families.data[0])
const confirm = useConfirm("alert-save");
const departmentEdit = ref(false)

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


const onSaveAll = () => {
    departmentForm.patch(
        route(props.update_route.name, props.update_route.parameters),
        {
            preserveScroll: true,
            onStart: () => {
                isLoading.value = true;
            },
            onSuccess: () => {
                departmentEdit.value = false
                departmentForm.clearErrors()
            },
            onError: (errors) => {
                console.error('Save failed:', errors);
                notify({
                    title: "Failed to Save",
                    text: errors,
                    type: "error"
                })
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
        group: "alert-save",
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


const onUpload = async (files: File[], clear) => {
    if (!files.length) return;

    const formData = new FormData();
    files.forEach((file) => {
        formData.append('image', file);
    });

    for (const [key, value] of formData.entries()) {
        console.log(key, value);
    }

    router.post(
        route(props.upload_image_route.name, props.upload_image_route.parameters),
        formData,
        {
            forceFormData: true, // Ensure Inertia treats it as FormData
            onSuccess: (e) => {
                departmentForm.image = props.department.data.image
                isModalGallery.value = false;
                /*     clear(); */
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
        <div>
            <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                <div class="flex justify-between items-center border-b pb-4 mb-4">
                    <h3 class="text-xl font-semibold">Departement</h3>
                    <Button v-if="!departmentEdit" label="Edit Departement" :size="'xs'" :type="'primary'"
                        :icon="faEdit" @click="departmentEdit = true" />
                    <div v-else class="flex gap-3">
                        <Button label="Cancel" :size="'xs'" :type="'tertiary'" @click="departmentEdit = false" />
                        <Button label="Save" :size="'xs'" :type="'primary'" :icon="faSave"
                            @click="() => confirmSave()" />
                    </div>
                </div>
                <div class="flex items-center justify-between mb-6">
                    <button @click="goToPrev" aria-label="Previous">
                        <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
                    </button>
                    <div class="flex-1 mx-4">
                        <component :is="getComponentDepartment(usedTemplate.code)" :data="departmentForm" />
                    </div>
                    <button @click="goToNext" aria-label="Next">
                        <FontAwesomeIcon :icon="faChevronCircleRight"
                            class="text-xl text-gray-600 hover:text-primary" />
                    </button>
                </div>
                <!-- Form -->
                <div v-if="departmentEdit" class="border-t pt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                        <PureInput v-model="departmentForm.name" type="text" placeholder="Enter name" />
                        <p class="text-red-500 text-xs">{{ departmentForm.errors?.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <PureTextarea v-model="departmentForm.description" type="text" :rows="4"
                            placeholder="Enter name" />
                        <p class="text-red-500 text-xs">{{ departmentForm.errors?.description }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                        <Button label="Upload Image" :type="'tertiary'" :icon="faImage"
                            @click="isModalGallery = true" />
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Show in website</label>
                        <ToggleSwitch v-model="departmentForm.show_in_website" />
                        <p class="text-red-500 text-xs">{{ departmentForm.errors?.show_in_website }}</p>
                    </div>
                </div>

                <div v-else="departmentEdit" class="border-t pt-4 space-y-4 text-sm text-gray-700">
                    <div class="text-sm font-medium">
                        <span>{{ departmentForm.name || 'No label' }}</span>
                    </div>
                    <div class="text-md">
                        <span class="text-gray-400">{{ departmentForm.description || 'No description' }}</span>
                    </div>
                </div>
            </div>
        </div>

        <SetVisibleList title="Departments List" :list_data="families.data" :updateRoute="update_route" />
    </div>

    <ConfirmDialog group="alert-save">
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>

    <!-- Gallery Modal -->
    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
        <GalleryManagement :uploadRoute="{ name: '', parameters: '' }" :closePopup="() => (isModalGallery = false)"
            :submitUpload="onUpload" :maxSelected="1"
            @submitSelectedImages="(e) => { departmentForm.image_id = e[0].id, isModalGallery = false, onSaveAll() }" />
    </Modal>


    <Modal :isOpen="isModalFamiliesPreview" @onClose="() => (isModalFamiliesPreview = false)" width="w-3/4">
        <div class="flex items-center justify-between mb-6">
            <button @click="goToPrev" aria-label="Previous">
                <FontAwesomeIcon :icon="faChevronCircleLeft" class="text-xl text-gray-600 hover:text-primary" />
            </button>
            <div class="flex-1 mx-4">
                <component :is="getIrisComponent(showPreviewFamilies.code)"
                    :fieldValue="{ ...showPreviewFamilies.data, family: families.data.filter((item) => item.show_in_website) }" />
            </div>
            <button @click="goToNext" aria-label="Next">
                <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
            </button>
        </div>
    </Modal>
</template>
