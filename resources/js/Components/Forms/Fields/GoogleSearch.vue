<script setup lang='ts'>
import PureInput from '@/Components/Pure/PureInput.vue'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { faInfoCircle } from '@fal'
import { faFacebook, faLinkedin, faGoogle, faTwitter } from '@fortawesome/free-brands-svg-icons'
import { library } from '@fortawesome/fontawesome-svg-core'
import { trans } from 'laravel-vue-i18n'


library.add(faInfoCircle, faFacebook, faLinkedin, faGoogle, faTwitter)

defineProps<{
  form?: any
  fieldName: string
  options: string[] | {}
  fieldData?: {
    domain?: string
  }
}>()




</script>

<template>
  <div class="max-w-2xl rounded-md">


    <div class="space-y-4 pt-4">


      <!-- Section: Title -->
      <div>
        <label class="text-gray-600 font-semibold cursor-pointer">{{ trans('Title')  }} / {{trans('Breadcrumbs')}}</label>
        <PureInput v-model="form[fieldName].meta_title" inputName="meta_title" :maxLength="70" placeholder="Wall paint - Bright, Shine, Protection" />
        <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].meta_title?.length || 0 }} of 70 characters used</div>
      </div>

      <!-- Section: SEO Description -->
      <div>
        <label class="text-gray-600 font-semibold cursor-pointer">{{ trans('SEO Description')}}</label>
        <PureTextarea v-model="form[fieldName].meta_description" inputName="meta_description" :rows="6" maxLength="320" placeholder="Your SEO description goes here..." />
        <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].meta_description?.length || 0 }} of 320 characters used</div>
      </div>

      <!-- Section: Url  Do not delete this yet -->
<!--      <div class="mt-4">-->
<!--        <label class="text-gray-600 font-semibold cursor-pointer">URL</label>-->
<!--        <PureInput v-model="form[fieldName].url" inputName="seoUrl" placeholder="profile">-->
<!--          <template #prefix>-->
<!--            <div class="pl-3 -mr-2 whitespace-nowrap text-gray-400">-->
<!--              {{ fieldData?.domain || 'https://example.com' }}-->
<!--            </div>-->
<!--          </template>-->
<!--        </PureInput>-->

<!--        <div class="mt-3 flex items-center">-->
<!--          <input type="checkbox" v-model="form[fieldName].is_use_canonical_url" id="canonical" class="mr-2" />-->
<!--          <label for="canonical" class="text-gray-600 cursor-pointer">Use Canonical URL</label>-->
<!--        </div>-->

<!--        <div v-if="form[fieldName].is_use_canonical_url" class="mt-3">-->
<!--          <label class="text-gray-600 font-semibold cursor-pointer">Canonical URL</label>-->
<!--          <PureInput v-model="form[fieldName].canonical_url" inputName="canonical_url">-->
<!--            <template #prefix>-->
<!--              <div class="pl-3 -mr-2 whitespace-nowrap text-gray-400">-->
<!--                {{ fieldData?.domain || 'https://example.com' }}-->
<!--              </div>-->
<!--            </template>-->
<!--          </PureInput>-->
<!--        </div>-->
<!--      </div>-->
      
      <!-- Section: Llms Text -->
      <!-- <div>
        <label class="text-gray-600 font-semibold">
          LLMS Text
          <FontAwesomeIcon v-tooltip="trans('For a Large Language Model System (LLMS), such as ChatGPT, Bard, or Claude.')" icon="fal fa-info-circle" class="cursor-pointer text-gray-400 hover:text-gray-700" fixed-width aria-hidden="true" />
        </label>
        <PureInput v-model="form[fieldName].llms_text" inputName="llms_text" :maxLength="70" placeholder="Brightening your walls, lasting shine, and reliable protection" />
        <div class="mt-1 text-gray-500 italic tabular-nums">{{ form[fieldName].llms_text?.length || 0 }} of 70 characters used</div>
      </div> -->
    </div>
  </div>
</template>