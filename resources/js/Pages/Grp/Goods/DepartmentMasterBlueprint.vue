<script setup lang="ts">
import { ref } from 'vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { faChevronCircleLeft, faChevronCircleRight, faImage, faEye, faEyeSlash, faExclamationTriangle } from '@far'
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import DepartementRender from '@/Components/CMS/Webpage/Department1/DepartementRender.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInput from '@/Components/Pure/PureInput.vue'
import { useConfirm } from "primevue/useconfirm";
import ConfirmPopup from 'primevue/confirmpopup';
import { router } from "@inertiajs/vue3"
import { routeType } from '@/types/route'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import ConfirmDialog from 'primevue/confirmdialog';
import { getIrisComponent } from '@/Composables/getIrisComponents'
import ListItem from '@tiptap/extension-list-item'

const props = defineProps<{
    pageHead: {},
    department: {},
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
console.log(props)
const confirm = useConfirm();


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

const componentsDepartement: Record<string, Component> = {
    'department-1': DepartementRender,
}

const getComponentDepartement = (componentName: string) => {
    return componentsDepartement[componentName]
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
      item.show = !item.show;
    },
  });
};


const onSaveAll = (routes: routeType) => {
    router.patch(
        route(routes.name, routes.parameters),
        { data: departmentData.value, families: familiesOption.value },
        {
            preserveScroll: true,
            onStart: () => { isLoading.value = true },
            onSuccess: () => { },
            onError: errors => { },
            onFinish: () => { isLoading.value = false },
        }
    )
}

const confirmSave = (routes: routeType) => {
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
            onSaveAll(routes);
        },
    });
};

const onUpload = async (files: File[], clear: Function) => {
    const file = files[0]
    if (!file) return

    const toBase64 = (file: File): Promise<string> =>
        new Promise((resolve, reject) => {
            const reader = new FileReader()
            reader.readAsDataURL(file)
            reader.onload = () => resolve(reader.result as string)
            reader.onerror = reject
        })
    try {
        const base64 = await toBase64(file)
        departmentData.value.image = base64
        isModalGallery.value = false
    } catch (err) {
        console.error('Failed to convert to base64:', err)
    }
}



</script>


<template>
    <PageHeading :data="pageHead">
        <template #button-save="{ action }">
            <Button type="save" @click="() => confirmSave(action.route)" />
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
                    <component :is="getComponentDepartement(usedTemplate.code)" :data="departmentData" />
                </div>
                <button @click="goToNext" aria-label="Next">
                    <FontAwesomeIcon :icon="faChevronCircleRight" class="text-xl text-gray-600 hover:text-primary" />
                </button>
            </div>

            <!-- Form -->
            <div class="border-t pt-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <PureInput v-model="departmentData.name" type="text" placeholder="Enter name" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Name</label>
                    <PureTextarea v-model="departmentData.description" type="text" :rows="4" placeholder="Enter name" />
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                    <Button label="Upload Image" :type="'tertiary'" :icon="faImage" @click="isModalGallery = true" />
                </div>
            </div>
        </div>

        <!-- Families List -->
        <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-semibold">Families List</h3>
                <Button label="Preview" :size="'xs'" :type="'tertiary'" :icon="faEye" @click="isModalFamiliesPreview = true"/>
            </div>

            <ul class="divide-y divide-gray-100">
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


   <!--  <ConfirmPopup>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmPopup> -->

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
            @submitSelectedImages="(e)=>{departmentData.image = e[0], isModalGallery = false}" 
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
