<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->


<script setup lang="ts">
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import PureInputWithAddOn from '@/Components/Pure/PureInputWithAddOn.vue'
import { faDollarSign } from '@far'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faExclamationCircle, faCheckCircle } from '@fas'
import { faSpinnerThird } from '@fad'
library.add(faExclamationCircle, faCheckCircle, faSpinnerThird, faDollarSign)

defineProps<{
    form: any,
    fieldName: string,
    options?: any,
    fieldData?: {
        placeholder?: string
        leftAddOn?: {
            icon?: string | string[],
            label?: string,
        }
        rightAddOn?: {
            icon?: string | string[],
            label?: string,
        }
        readonly?: boolean;
    }
}>()
</script>

<template>
    <div>

      <PureInputWithAddOn
            v-model="form[fieldName]"
            @update:modelValue="() => form.errors[fieldName] = null"
            :inputName="fieldName"
            v-bind="fieldData"
            :isError="form.errors[fieldName]"
        >
            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                <FontAwesomeIcon
                    v-if="form.errors[fieldName]"
                    icon="fas fa-exclamation-circle"
                    class="h-5 w-5 text-red-500"
                    aria-hidden="true"
                />
                <FontAwesomeIcon
                    v-if="form.recentlySuccessful"
                    icon="fas fa-check-circle"
                    class="h-5 w-5 text-green-500"
                    aria-hidden="true"
                />
            </div>
        </PureInputWithAddOn>

        <p v-if="form.errors[fieldName]"
            class="mt-2 text-sm text-red-600"
            :id="`${fieldName}-error`"
        >
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>