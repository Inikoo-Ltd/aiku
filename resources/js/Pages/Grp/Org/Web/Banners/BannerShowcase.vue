<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Mon, 02 Oct 2023 03:23:49 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">
  import { onMounted, ref } from 'vue'
  // import Input from '@/Components/Forms/Fields/Input.vue'
  import { trans } from "laravel-vue-i18n"
  import BannerPreview from '@/Components/Banners/BannerPreview.vue'
  import EmptyState from '@/Components/Utils/EmptyState.vue'
  import { cloneDeep } from 'lodash-es'
  import Button from '@/Components/Elements/Buttons/Button.vue'
  
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import { faSign, faGlobe, faCopy, faCheck } from '@fal'
  import { faLink } from '@far'
  import { library } from '@fortawesome/fontawesome-svg-core'
  import { useCopyText } from '@/Composables/useCopyText'
  
  library.add(faSign, faGlobe, faCopy, faCheck, faLink)
  
  const props = defineProps<{
      data: {
          id: number
          ulid: string
          state: string
          delivery_url: string
          export_url: string
      }
      tab?: string
  }>()
  
  onMounted(() => {
      props.data.compiled_layout.components = cloneDeep(props.data?.compiled_layout?.components)?.filter((item: {visibility: boolean}) => item.visibility === true)
  })
  
  // Method: Copy ulid
  const isOnCopy = ref(false)
  const onCopyUlid = async (text: string) => {
      isOnCopy.value = true
      useCopyText(text)
      setTimeout(() => {
          isOnCopy.value = false
      }, 2000)
  }

  </script>
  
  
  <template>
      <div class="py-3 mx-auto px-5 space-y-4">
          <!-- The banner -->
          <div v-if="data.compiled_layout?.components?.length && data.state != 'switch_off'" class="mx-auto w-fit rounded-md overflow-hidden border border-gray-300 shadow">
              <BannerPreview :data="data" />
          </div>
  
          <EmptyState v-else :data="{
              title: data.state != 'switch_off' ? trans('You do not have slides to show') : trans('You turn off the banner'),
              description: data.state != 'switch_off' ? trans('Create new slides in the workshop to get started' ): trans('need re-publish the banner at workshop'),
            
          }" />
  
          <!-- Box: Url (copy button) -->
          <!-- <div v-if="data.state !== 'unpublished'" class="" :class="[!data.compiled_layout?.components?.length ?  'flex justify-center' : '' ]">
              <div class="bg-white border border-gray-300 flex items-center justify-between mx-auto gap-x-3 rounded-md md:w-fit ">
                  <a :href="data.delivery_url" target="_blank" class="pl-4 md:pl-5 inline-block py-2 text-xxs md:text-base text-gray-400">{{ data.delivery_url }}</a>
                  <Button :style="'secondary'" class="" size="xl" @click="useCopyText(data.delivery_url)" title="Copy url to clipboard">
                      <FontAwesomeIcon icon='far fa-link' class='text-gray-500' aria-hidden='true' />
                  </Button>
              </div>
              <div class="w-full text-center text-gray-500 mt-2 text-sm italic">
                  {{ data.ulid }}
                  <FontAwesomeIcon v-if="!isOnCopy" @click="() => onCopyUlid(data.ulid)" icon='fal fa-copy' class='cursor-pointer hover:text-gray-600' fixed-width aria-hidden='true' />
                  <FontAwesomeIcon v-else icon='fal fa-check' class='text-green-500' fixed-width aria-hidden='true' />
              </div>
          </div> -->
      </div>
  
  </template>
  
  