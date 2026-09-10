<script setup lang="ts">
import { RadioGroup, RadioGroupLabel, RadioGroupOption } from '@headlessui/vue'
import { trans } from 'laravel-vue-i18n'
import { get, set } from 'lodash-es'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faInfoCircle, faBroadcastTower, faSkull } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faInfoCircle, faBroadcastTower, faSkull)

defineProps<{
    form: any,
    fieldName: string,
    fieldData?: {
        information?: string
    }
}>()
</script>

<template>
    <div>
        <RadioGroup
            :modelValue="form[fieldName].state"
            @update:modelValue="(e) => (set(form, [fieldName, 'state'], e))"
        >
            <div class="flex gap-x-1.5 gap-y-1 flex-wrap"
                :class="get(form, ['errors', `state`]) ? 'errorShake' : ''"
            >
                <RadioGroupOption as="template"
                    :key="'closed'"
                    :value="'closed'"
                    v-slot="{ active, checked }">
                    <div
                        :class="[
                            'group cursor-pointer focus:outline-none flex items-center justify-center border rounded-md py-2 px-3 text-sm font-medium capitalize',
                            active ? 'ring-2 ring-red-600 ring-offset-2' : '',
                            checked ? 'bg-red-100 text-red-600 hover:bg-red-300 border-red-500' : 'ring-1 ring-inset ring-gray-300 bg-white hover:bg-gray-100',
                        ]">
                        <FontAwesomeIcon icon="fal fa-skull" class="mr-1" :class="checked ? 'text-red-500' : 'text-gray-400 group-hover:text-red-500'" fixed-width aria-hidden="true" />
                        <RadioGroupLabel as="span">{{ trans("Offline") }}</RadioGroupLabel>
                    </div>
                </RadioGroupOption>

                <RadioGroupOption as="template"
                    :key="'live'"
                    :value="'live'"
                    v-slot="{ active, checked }">
                    <div
                        :class="[
                            'group cursor-pointer focus:outline-none flex items-center justify-center border rounded-md py-2 px-3 text-sm font-medium capitalize',
                            active ? 'ring-2 ring-green-600 ring-offset-2' : '',
                            checked ? 'bg-green-100 text-green-600 hover:bg-green-300 border-green-500' : 'ring-1 ring-inset ring-gray-300 bg-white hover:bg-gray-100',
                        ]">
                        <FontAwesomeIcon icon="fal fa-broadcast-tower" class="mr-1" :class="checked ? 'text-green-500' : 'text-gray-400 group-hover:text-green-500'" fixed-width aria-hidden="true" />
                        <RadioGroupLabel as="span">{{ trans("Online") }}</RadioGroupLabel>
                    </div>
                </RadioGroupOption>
            </div>
        </RadioGroup>

        <Transition name="slide-to-left">
            <div v-if="form[fieldName].state === 'closed'" class="mt-4 w-full max-w-sm text-xs text-gray-500">
                <FontAwesomeIcon icon="fal fa-info-circle" class="text-gray-400" fixed-width aria-hidden="true" />
                {{ fieldData?.information || trans("When this page is offline the default system page will be shown.") }}
            </div>
        </Transition>

        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
