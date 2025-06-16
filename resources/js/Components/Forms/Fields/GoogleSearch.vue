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

const onFileSelect = (event: any) => {
    const file = event.files?.[0];
    if (!file) return;

    const reader = new FileReader();

    reader.onload = (e) => {
        props.form[props.fieldName].image = {
            original: e.target?.result,
        }
    }

    reader.readAsDataURL(file);
}


const selectedPreview = ref('facebook')
</script>

<template>
  <div class="max-w-2xl rounded-md">
 <!--    <div class="space-y-2">
      <div class="w-fit flex gap-2">
        <div
          v-for="platform in ['facebook', 'linkedin', 'twitter', 'google']"
          :key="platform"
          @click="selectedPreview = platform"
          class="p-2 flex items-center justify-center rounded cursor-pointer"
          :class="selectedPreview === platform ? 'bg-indigo-100 border border-indigo-500' : 'hover:bg-gray-100 border border-gray-300'"
        >
          <FontAwesomeIcon :icon="['fab', platform]" class="text-xl" fixed-width aria-hidden="true" />
        </div>
      </div>
    </div>

    <div class="mt-4 mb-8 min-h-72 w-full overflow-auto flex justify-center">
      <div v-if="selectedPreview === 'facebook'" class="h-fit border border-gray-300 bg-gray-100 w-[70%]">
        <div class="bg-white aspect-[1.91/1] w-full">
          <Image :src="form[fieldName].image" imageCover />
        </div>
        <div class="px-4 pt-2 pb-3">
          <div class="text-gray-500 mt-1 font-light text-xs uppercase">{{ fieldData?.domain || 'https://example.com' }}</div>
          <div class="text-gray-900 font-semibold text-lg leading-5">{{ form[fieldName].meta_title }}</div>
        </div>
      </div>

      <div v-if="selectedPreview === 'google'" class="h-fit mx-auto mb-4 bg-white rounded-lg border border-gray-200 p-4 w-[70%]">
        <div class="text-blue-600 hover:text-blue-700 font-medium text-2xl text-ellipsis overflow-hidden">
          {{ form[fieldName].meta_title || 'Wall paint - Bright, Shine, Protection' }}
        </div>
        <div class="text-green-700">
          {{ fieldData?.domain || 'https://example.com' }}{{ form[fieldName].url || '' }}
        </div>
        <div class="text-gray-600 break-words">
          {{ form[fieldName].meta_description || 'Your SEO description goes here...' }}
        </div>
      </div>

      <div v-if="selectedPreview === 'linkedin'" class="h-fit border border-gray-300 bg-gray-100 w-[70%]">
        <div class="bg-white aspect-[1.91/1] w-full">
          <Image :src="form[fieldName].image" imageCover />
        </div>
        <div class="px-4 pt-2 pb-3">
          <div class="text-gray-900 font-semibold text-lg leading-6 text-ellipsis overflow-hidden">{{ form[fieldName].meta_title }}</div>
          <div class="text-gray-500 mt-1 font-light text-xs tracking-wider">{{ fieldData?.domain || 'https://example.com' }}</div>
        </div>
      </div>

      <div v-if="selectedPreview === 'twitter'" class="h-fit relative border border-gray-300 rounded-2xl overflow-hidden w-[70%]">
        <div class="bg-white aspect-[1.91/1] w-full">
          <Image :src="form[fieldName].image" imageCover />
        </div>
        <div class="absolute left-5 bottom-2 w-[93%]">
          <p class="bg-gray-900/40 text-white w-fit max-w-full px-1 rounded text-sm truncate">{{ form[fieldName].meta_title }}</p>
        </div>
      </div>
    </div> -->

    <div class="space-y-4 pt-4">
      <!-- <div>
        <label class="text-gray-600 font-semibold cursor-pointer">Image</label>
        <div class="aspect-[1.91/1] max-h-56 max-w-96 mx-auto group relative rounded-md overflow-hidden border border-dashed border-gray-300">
          <Image :src="form[fieldName].image" imageCover />
          <div class="opacity-0 group-hover:opacity-100 absolute inset-0 hover:bg-gray-900/50 flex items-center justify-center">
            <FileUpload mode="basic" @select="onFileSelect" customUpload auto accept="image/*" severity="secondary" class="p-button-outlined" />
          </div>
        </div>
      </div> -->

      <div>
        <label class="text-gray-600 font-semibold cursor-pointer">Title</label>
        <PureInput v-model="form[fieldName].meta_title" inputName="meta_title" :maxLength="70" placeholder="Wall paint - Bright, Shine, Protection" />
        <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].meta_title?.length || 0 }} of 70 characters used</div>
      </div>

      <div>
        <label class="text-gray-600 font-semibold cursor-pointer">SEO Description</label>
        <PureTextarea v-model="form[fieldName].meta_description" inputName="meta_description" :rows="6" maxLength="320" placeholder="Your SEO description goes here..." />
        <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].meta_description?.length || 0 }} of 320 characters used</div>
      </div>

      <div class="mt-4">
        <label class="text-gray-600 font-semibold cursor-pointer">URL</label>
        <PureInput v-model="form[fieldName].url" inputName="seoUrl" placeholder="profile">
          <template #prefix>
            <div class="pl-3 -mr-2 whitespace-nowrap text-gray-400">
              {{ fieldData?.domain || 'https://example.com' }}
            </div>
          </template>
        </PureInput>

        <div class="mt-3 flex items-center">
          <input type="checkbox" v-model="form[fieldName].is_use_canonical_url" id="canonical" class="mr-2" />
          <label for="canonical" class="text-gray-600 cursor-pointer">Use Canonical URL</label>
        </div>

        <div v-if="form[fieldName].is_use_canonical_url" class="mt-3">
          <label class="text-gray-600 font-semibold cursor-pointer">Canonical URL</label>
          <PureInput v-model="form[fieldName].canonical_url" inputName="canonical_url">
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