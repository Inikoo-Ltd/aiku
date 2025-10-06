<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 03:12:13 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

  <script setup lang="ts">

  import { routeType } from '@/types/route'
  import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
  import { faSave as fadSave, } from '@fad'
  import { faSave as falSave, faInfoCircle } from '@fal'
  import { faAsterisk, faQuestion } from '@fas'
  import { library } from '@fortawesome/fontawesome-svg-core'
  library.add(fadSave, faQuestion, falSave, faInfoCircle, faAsterisk)
import { getComponent } from '@/Composables/Listing/FieldFormList'  // Fieldform list
  
  const props = defineProps<{
      field: any
      form : any,
      fieldData: {
          type: string
          label: string
          verification?: {
              route: routeType
          }
          value: any
          mode?: string
          required?: boolean
          options?: {}[]
          full: boolean
          noTitle?: boolean
          noSaveButton?: boolean  // Button: save
      }
  }>()
  

  
  </script>
  
  <template>
      <form class="divide-y divide-gray-200 w-full" :class="props.fieldData.full ? '' : 'max-w-2xl'">
          <dl class="pb-4 sm:pb-5">
              <!-- Title -->
              <dt v-if="!fieldData.noTitle" class="text-sm font-medium text-gray-400 py-2">
                  <div class="inline-flex items-start leading-none"><FontAwesomeIcon v-if="fieldData.required" :icon="['fas', 'asterisk']" class="font-light text-[12px] text-red-400 mr-1"/>{{ fieldData.label }}</div>
              </dt>
  
              <dd :class="props.fieldData.full ? 'sm:col-span-3' : fieldData.noTitle ? 'sm:col-span-3' : 'sm:col-span-2'" class="flex items-start text-sm text-gray-700 sm:mt-0">
                  <div class="relative w-full">
                      <component :is="getComponent(fieldData.type)" :form="form" :fieldName="field"
                          :options="fieldData.options" :fieldData="fieldData">
                      </component>
  
                      <!-- Verification: Label -->
                      <div v-if="labelVerification" class="mt-1" :class="classVerification">
                          <FontAwesomeIcon icon='fal fa-info-circle' class='opacity-80' aria-hidden='true' />
                          <span class="ml-1 font-medium">{{ labelVerification }}</span>
                      </div>
                  </div>
              </dd>
          </dl>
      </form>
  </template>
  