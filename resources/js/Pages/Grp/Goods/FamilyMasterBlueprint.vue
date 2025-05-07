<script setup lang="ts">
import { ref, inject } from "vue";
import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import Dialog from 'primevue/dialog'
import Modal from '@/Components/Utils/Modal.vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronLeft, faEdit, faPlus, faSave } from '@far'
import { layoutStructure } from "@/Composables/useLayoutStructure"
import GalleryManagement from '@/Components/Utils/GalleryManagement/GalleryManagement.vue'
import { faChevronRight, faEye, faEyeSlash, faImage, faExclamationTriangle } from '@fal'
import Family1Render from '@/Components/CMS/Webpage/Family1/Family1Render.vue'
import { router, useForm } from "@inertiajs/vue3"
import { routeType } from "@/types/route";
import ConfirmDialog from 'primevue/confirmdialog';
import ToggleSwitch from 'primevue/toggleswitch';
import { useConfirm } from "primevue/useconfirm";
import { notify } from "@kyvg/vue3-notification";

const props = defineProps<{
    pageHead: {}
    assets: any
    web_block_types: Array<string>
    web_block_types_families: {
        data: Array
    }
    update_route : routeType
    upload_image_route : routeType
    family: {
        data: {
            code: string
            name: string
            description: string
            images: Array<string>
            show_in_website : boolean
        }
    }
}>()
console.log(props)
const confirm = useConfirm();
const locale = inject('locale', layoutStructure)
const visible = ref(false)
const isModalGallery = ref(false)
const usedTemplate = ref(props.web_block_types.data[0])
const familyEdit = ref(false)
const isLoading = ref(false)
const faqs = ref([
    {
        question: 'Shipping & Returns',
        answer: 'We offer free shipping on orders over $100 and a 30-day return policy for all purchases.'
    },
    {
        question: 'Product Materials',
        answer: 'Made with durable canvas and full-grain leather to ensure long-lasting use and a sleek look.'
    },
    {
        question: 'Warranty Information',
        answer: 'All products come with a 1-year limited warranty covering manufacturing defects.'
    },
    {
        question: 'Care Instructions',
        answer: 'Wipe clean with a damp cloth. Do not machine wash or tumble dry.'
    }
])
const translation = ref("english")
const familyForm = useForm(props.family.data)

/* const ProductsOption = ref(
    props.assets.data.map((item) => ({
        ...item,  
        show: true,
        loading : false
    }))
) */


const newFaq = ref({
    question: '',
    answer: ''
})

const goToPrev = () => {
    console.log('Previous clicked')
}

const goToNext = () => {
    console.log('Next clicked')
}

const componentsFamily: Record<string> = {
    'family-1': Family1Render,
}

const getComponentfamily = (componentName: string) => {
    return componentsFamily[componentName]
}

const addFaq = () => {
    if (newFaq.value.question && newFaq.value.answer) {
        faqs.value.push({ ...newFaq.value })
        newFaq.value.question = ''
        newFaq.value.answer = ''
        visible.value = false
    }
}


const onSaveAll = () => {
  familyForm.patch(
    route(props.update_route.name, props.update_route.parameters),
    {
      preserveScroll: true,
      onStart: () => {
        isLoading.value = true;
      },
      onSuccess: () => {
        familyEdit.value = false
        familyForm.clearErrors()
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
    console.log("masukkk")
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
        familyForm.image = props.family.data.image
        isModalGallery.value = false;
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
    </PageHeading>
    <div class="p-6">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div >
                <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200">
                    <div class="flex justify-between items-center border-b pb-4 mb-4">
                    <h3 class="text-xl font-semibold">Family</h3>
                    <Button v-if="!familyEdit" label="Edit Family" :size="'xs'" :type="'primary'" :icon="faEdit"
                        @click="familyEdit = true" />
                    <div v-else class="flex gap-3">
                        <Button label="Cancel" :size="'xs'" :type="'tertiary'"
                            @click="familyEdit = false" />
                        <Button label="Save" :size="'xs'" :type="'primary'" :icon="faSave" @click="() => confirmSave()" />
                    </div>
                </div>
                <!-- Navigation & Preview -->
                <div class="flex items-center justify-between mb-6">
                    <button @click="goToPrev" aria-label="Previous">
                        <FontAwesomeIcon :icon="faChevronLeft" class="text-xl text-gray-600 hover:text-primary" />
                    </button>
                    <div class="flex-1 mx-4">
                        <component :is="getComponentfamily(usedTemplate.code)" :data="familyForm" />
                    </div>
                    <button @click="goToNext" aria-label="Next">
                        <FontAwesomeIcon :icon="faChevronRight" class="text-xl text-gray-600 hover:text-primary" />
                    </button>
                </div>

                <!-- Form -->
                <div v-if="familyEdit" class="border-t pt-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Label</label>
                        <PureInput v-model="familyForm.name" type="text" placeholder="Enter name" />
                        <p class="text-red-500 text-xs">{{familyForm.errors?.name}}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                        <PureTextarea v-model="familyForm.description" type="text" :rows="4" placeholder="Enter name" />
                        <p class="text-red-500 text-xs">{{familyForm.errors?.description}}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Image</label>
                        <Button label="Upload Image" :type="'tertiary'" :icon="faImage" @click="isModalGallery = true" />
                    </div>
                    <!-- <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Show in website</label>
                        <ToggleSwitch v-model="familyForm.show_in_website" />
                        <p class="text-red-500 text-xs">{{familyForm.errors?.show_in_website}}</p>
                    </div> -->
                </div>

                <div v-else="familyEdit" class="border-t pt-4 space-y-4 text-sm text-gray-700">
                    <div class="text-sm font-medium">
                        <span>{{ familyForm.name || 'No label' }}</span>
                    </div>
                    <div class="text-md">
                        <span class="text-gray-400">{{ familyForm.description || 'No description' }}</span>
                    </div>
                </div>
                </div>
            </div>

            


            <div class="bg-white p-6 rounded-2xl shadow-md border border-gray-200 " >
            <div class="flex justify-between items-center border-b pb-4 mb-4">
                <h3 class="text-xl font-semibold">Products List</h3>
                <!-- <Button label="Preview" :size="'xs'" :type="'tertiary'" :icon="faEye" @click="isModalFamiliesPreview = true"/> -->
            </div>

            <ul class="divide-y divide-gray-100 max-h-[calc(100vh-30vh)] min-h-12 overflow-auto">
                <li v-for="(item, index) in assets.data" :key="item.slug"
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
                    <div class="text-gray-500 hover:text-primary cursor-pointer transition" title="Toggle visibility">
                        <FontAwesomeIcon :icon="item.show_in_website ? faEye : faEyeSlash" />
                    </div>
                </li>
            </ul>
        </div>

        <div class="bg-white  p-6 h-fit rounded-2xl shadow-md border border-gray-200">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-lg font-semibold text-gray-800">FAQ</h2>

                    <div class="w-40">
                        <label class="sr-only">Select Language</label>
                        <select v-model="translation"
                            class="w-full border border-gray-300 rounded-md px-3 py-2 text-sm text-gray-700 focus:ring-2 focus:ring-blue-500 focus:outline-none">
                            <option v-for="item of locale.languageOptions" :value="item.code">{{ item.name }}</option>
                        </select>
                    </div>
                </div>

                <!-- Divider -->
                <hr class="border-t border-gray-200 mb-4" />

                <div class="space-y-4">
                    <Disclosure v-for="(faq, index) in faqs" :key="index" v-slot="{ open }">
                        <DisclosureButton
                            class="flex justify-between w-full px-4 py-2 text-sm font-medium text-left text-blue-900 bg-blue-100 rounded-lg hover:bg-blue-200 focus:outline-none focus-visible:ring focus-visible:ring-blue-500 focus-visible:ring-opacity-75">
                            {{ faq.question }}
                            <span>{{ open ? '−' : '+' }}</span>
                        </DisclosureButton>
                        <DisclosurePanel class="px-4 pt-4 pb-2 text-sm text-gray-600">
                            {{ faq.answer }}
                        </DisclosurePanel>
                    </Disclosure>

                    <Button full label="Create" :icon="faPlus" :type="'dashed'" @click="visible = true" />
                </div>
            </div>
        </div>
    </div>

    <!-- Dialog to Add FAQ -->
    <Dialog v-model:visible="visible" header="Add FAQ" :style="{ width: '40rem' }" modal>

        <!-- Question Input -->
        <div class="items-center gap-4 mb-4">
            <label for="question" class="font-semibold w-24 mb-4">Question</label>
            <PureInput v-model="newFaq.question" id="question" placeholder="Enter question" />
        </div>

        <!-- Answer Input -->
        <div class="items-center gap-4 mb-8">
            <label for="answer" class="font-semibold w-24 mb-4">Answer</label>
            <PureTextarea v-model="newFaq.answer" id="answer" placeholder="Enter answer" rows="4" />
        </div>

        <!-- Buttons -->
        <div class="flex justify-end gap-2">
            <Button :type="'tertiary'" label="Cancel" @click="visible = false" />
            <Button type="save" @click="addFaq" />
        </div>
    </Dialog>


    <Modal :isOpen="isModalGallery" @onClose="() => (isModalGallery = false)" width="w-3/4">
        <GalleryManagement :uploadRoute="{ name: '', parameters: '' }" :closePopup="() => (isModalGallery = false)"
            :submitUpload="onUpload" :maxSelected="1"
            @submitSelectedImages="(e)=>{familyForm.image_id = e[0].id, isModalGallery = false, onSaveAll()}" />
    </Modal>

    <ConfirmDialog>
        <template #icon>
            <FontAwesomeIcon :icon="faExclamationTriangle" class="text-yellow-500" />
        </template>
    </ConfirmDialog>
</template>
