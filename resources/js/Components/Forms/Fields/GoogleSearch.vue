<script setup lang='ts'>
import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import FileUpload from 'primevue/fileupload'

import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faInfoCircle } from '@fal'
import { faFacebook, faLinkedin, faGoogle, faTwitter } from '@fortawesome/free-brands-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'
import { ref } from 'vue'
import Image from '@/Components/Image.vue'
library.add(faInfoCircle, faFacebook, faLinkedin, faGoogle, faTwitter)

const props = defineProps<{
    form?: any
    fieldName: string
    options: string[] | {}
    fieldData?: {
        domain?: string
    }
}>()


const onFileSelect = (event: Event) => {
    const file = event.files[0];
    const reader = new FileReader();
    // console.log('cccc', file)

    reader.onload = async (e) => {
        props.form[props.fieldName].image.original = e.target.result;
    };

    reader.readAsDataURL(file);
}

console.log(props.form[props.fieldName])
// console.log('======', props.form[props.fieldName])

// const dummyValue = reactive({
//     seourl: 'https://www.nenecourtemporium.co.uk/collections/frenchic-wall-paint',
//     seo_title: 'Frenchic Wall paint - NEXT WORKING DAY Delivery',
//     seo_description: 'Frenchic Wall Paint - Transform your space with the eco-friendly, luxurious choice for discerning decorators. Our high-quality, low-VOC paint provides a durable, elegannt finish, perfect for any room'
// })

const selectedPreview = ref('facebook')
</script>

<template>
    <div class="max-w-2xl rounded-md">
        
        <div class="space-y-2">
            <!-- <div class="text-center">
                Preview on platform
            </div> -->
            <div class="w-fit flex gap-2">
                <div @click="() => selectedPreview = 'facebook'" class="p-2 flex items-center justify-center rounded cursor-pointer"
                    :class="selectedPreview === 'facebook' ? 'bg-indigo-100 border border-indigo-500' : 'hover:bg-gray-100 border border-gray-300'"
                >
                    <FontAwesomeIcon icon='fab fa-facebook' class='text-xl' fixed-width aria-hidden='true' />
                </div>

                <div @click="() => selectedPreview = 'linkedin'" class="p-2 flex items-center justify-center rounded cursor-pointer"
                    :class="selectedPreview === 'linkedin' ? 'bg-indigo-100 border border-indigo-500' : 'hover:bg-gray-100 border border-gray-300'"
                >
                    <FontAwesomeIcon icon='fab fa-linkedin' class='text-xl' fixed-width aria-hidden='true' />
                </div>

                <div @click="() => selectedPreview = 'twitter'" class="p-2 flex items-center justify-center rounded cursor-pointer"
                    :class="selectedPreview === 'twitter' ? 'bg-indigo-100 border border-indigo-500' : 'hover:bg-gray-100 border border-gray-300'"
                >
                    <FontAwesomeIcon icon='fab fa-twitter' class='text-xl' fixed-width aria-hidden='true' />
                </div>

                <div @click="() => selectedPreview = 'google'" class="p-2 flex items-center justify-center rounded cursor-pointer"
                    :class="selectedPreview === 'google' ? 'bg-indigo-100 border border-indigo-500' : 'hover:bg-gray-100 border border-gray-300'"
                >
                    <FontAwesomeIcon icon='fab fa-google' class='text-xl' fixed-width aria-hidden='true' />
                </div>
            </div>
        </div>

        <!-- Preview -->
        <div class="mt-4 mb-8 min-h-72 w-fit overflow-hidden flex justify-center">
            <!-- Preview: Facebook -->
            <div v-if="selectedPreview === 'facebook'" class="h-fit border border-gray-300 bg-gray-100 w-[70%]">
                <div class="bg-white aspect-[1.91/1] w-full">
                    <Image :src="form[fieldName].image" imageCover />
                </div>
                
                <div class="px-4 pt-2 pb-3">
                    <div class="text-gray-500 mt-1 font-light text-xs uppercase">{{ fieldData?.domain || 'https://example.com' }}</div>
                    <div class="text-gray-900 font-semibold text-lg leading-5">{{ form[fieldName].seo_title }}</div>
                </div>
            </div>

            <!-- Preview: Google -->
            <div v-if="selectedPreview === 'google'" class="h-fit mx-auto mb-4 bg-white rounded-lg border border-gray-200 p-4">
                <div class="text-blue-600 hover:text-blue-700 font-medium text-2xl text-ellipsis overflow-hidden">
                    {{ form[fieldName].seo_title || 'Wall paint - Bight, Shine, Protection'}}
                </div>
                <div class="text-green-700">
                    {{ fieldData?.domain || 'https://example.com' }}{{ form[fieldName].url || '' }}
                </div>
                <div class="text-gray-600 break-words">
                    {{ form[fieldName].seo_description || 'Lorem ipsum dolor, sit amet consectetur adipisicing elit. Illum inventore vitae quas accusamus, numquam quo aut. Vero modi nihil provident!' }}
                </div>
            </div>

            <!-- Preview: LinkedIn -->
            <div v-if="selectedPreview === 'linkedin'" class="h-fit border border-gray-300 bg-gray-100 w-[70%]">
                <div class="bg-white aspect-[1.91/1] w-full">
                    <Image :src="form[fieldName].image" imageCover />
                </div>

                <div class="px-4 pt-2 pb-3">
                    <div class="text-gray-900 font-semibold text-lg leading-6 text-ellipsis overflow-hidden">{{ form[fieldName].seo_title }}</div>
                    <div class="text-gray-500 mt-1 font-light text-xs tracking-wider">{{ fieldData?.domain || 'https://example.com' }}</div>
                </div>
            </div>

            <!-- Preview: X -->
            <div v-if="selectedPreview === 'twitter'" class="h-fit relative border border-gray-300 rounded-2xl overflow-hidden w-[70%]">
                <div class="bg-white aspect-[1.91/1] w-full">
                    <Image :src="form[fieldName].image" imageCover />
                </div>

                <div class="absolute left-5 bottom-2 w-[93%]">
                    <p class="bg-gray-900/40 text-white w-fit max-w-full px-1 rounded text-sm truncate">{{ form[fieldName].seo_title }}</p>
                </div>
            </div>
        </div>

        <!-- Section: Field -->
        <div class="space-y-4 pt-4 border-t border-dashed border-gray-300">
            <div class="">
                <label for="seo_title" class="text-gray-600 font-semibold cursor-pointer">Image</label>
                <div class="aspect-[1.91/1] max-h-56 max-w-96 mx-auto group relative rounded-md overflow-hidden border border-dashed border-gray-300">
                    <Image :src="form[fieldName].image" imageCover />
                    <div class="opacity-0 group-hover:opacity-100 absolute inset-0 hover:bg-gray-900/50 flex items-center justify-center">
                        <FileUpload mode="basic" @select="onFileSelect" customUpload auto accept="image/*" severity="secondary" class="p-button-outlined" />
                    </div>
                </div>
            </div>

            <div>
                <label for="seo_title" class="text-gray-600 font-semibold cursor-pointer">Title</label>
                <PureInput v-model="form[fieldName].seo_title" inputName="seo_title" :maxLength="70" placeholder="Wall paint - Bight, Shine, Protection" />
                <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].seo_title?.length || 0 }} of 70 characters used</div>
            </div>

            <div>
                <label for="seo_description" class="text-gray-600 font-semibold cursor-pointer">SEO Description</label>
                <PureTextarea v-model="form[fieldName].seo_description" inputName="seo_description" :rows="6" maxLength="320" placeholder="Lorem ipsum dolor, sit amet consectetur adipisicing elit. Illum inventore vitae quas accusamus, numquam quo aut. Vero modi nihil provident!" />
                <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].seo_description?.length || 0 }} of 320 characters used</div>
            </div>

           <div class="mt-4">
            <label for="url" class="text-gray-600 font-semibold cursor-pointer">URL</label>
            <PureInput v-model="form[fieldName].url" inputName="seoUrl" placeholder="profile">
                <template #prefix>
                    <div class="pl-3 -mr-2 whitespace-nowrap text-gray-400">
                        {{ fieldData?.domain || 'https://example.com' }}
                    </div>
                </template>
            </PureInput>
            
            <div class="mt-3 flex items-center">
                <input type="checkbox" v-model="form[fieldName].is_use_canonical_url" id="canonical" class="mr-2">
                <label for="canonical" class="text-gray-600 cursor-pointer">Use Canonical URL</label>
            </div>
            
            <div v-if="form[fieldName].is_use_canonical_url" class="mt-3">
                <label for="canonical_url" class="text-gray-600 font-semibold cursor-pointer">Canonical URL</label>
                <PureInput v-model="form[fieldName].canonical_url" inputName="canonical_url" placeholder="" >
                    <template #prefix>
                    <div class="pl-3 -mr-2 whitespace-nowrap text-gray-400">
                        {{ fieldData?.domain || 'https://example.com' }}
                    </div>
                </template>
                </PureInput>
            </div>
        </div>
        </div>
    </div>
</template>