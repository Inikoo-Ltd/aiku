<script setup lang="ts">

import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { RadioGroup, RadioGroupLabel, RadioGroupOption, RadioGroupDescription } from '@headlessui/vue'
import { trans } from 'laravel-vue-i18n'
import { get, set } from 'lodash-es'
import { ref } from 'vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faAsterisk } from "@fas"
import { faBroadcastTower } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import Icon from '@/Components/Icon.vue'
library.add(faAsterisk, faBroadcastTower)

// const props = defineProps(['form', 'fieldName', 'fieldData'])
const props = defineProps<{
    form: any,
    fieldName: string,
    fieldData?: {
        valueProp?: string,
        init_options?: {}[]
        default_storefront?: {}
        options?: Array<{ value: string, label: string }>
    }
}>()

// const compareObjects = (objA, objB) => {
//     // Get the keys of objA and objB
//     const keysA = Object.keys(objA);
//     const keysB = Object.keys(objB);

//     // Check if the number of keys is the same
//     if (keysA.length !== keysB.length) {
//         return false;
//     }

//     // Check if the values for each key are equal
//     for (let key of keysA) {
//         if (objA[key] !== objB[key]) {
//             return false;
//         }
//     }

//     return true;
// }

const optionxx = [
    { value: 'closed', label: 'offline' },
    { value: 'live', label: 'online' },
]

const xxx = ref('')
</script>

<template>
    <div>
        <RadioGroup
            :modelValue="form[fieldName].state"
            @update:modelValue="(e) => (set(form, [fieldName, 'state'], e))"
        >
            <!-- <RadioGroupLabel class="sr-only">Choose the radio</RadioGroupLabel> -->
            <div class="flex gap-x-1.5 gap-y-1 flex-wrap"
                :class="get(form, ['errors', `state`]) ? 'errorShake' : ''"
            >
                <!-- Offline -->
                <RadioGroupOption as="template"
                    :key="'closed'"
                    :value="'closed'"
                    v-slot="{ active, checked }">
                    <div
                        :class="[
                            'group cursor-pointer focus:outline-none flex items-center justify-center border rounded-md py-2 px-3 text-sm font-medium capitalize',
                            active ? 'ring-2 ring-red-600 ring-offset-2' : '',
                            checked ? 'bg-red-100 text-red-600 hover:bg-red-300 border-red-500' : 'ring-1 ring-inset ring-gray-300 bg-white  hover:bg-gray-100',
                        ]">
                        <FontAwesomeIcon icon="fal fa-skull" class="mr-1" :class="checked ? 'text-red-500' : 'text-gray-400 group-hover:text-red-500'" fixed-width aria-hidden="true" />
                        <RadioGroupLabel as="span">{{ trans("Offline") }}</RadioGroupLabel>
                    </div>
                </RadioGroupOption>

                <!-- Online -->
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
            <div v-if="form[fieldName].state === 'closed'" class="mt-4 w-full max-w-sm">
                <div class="text-xs xfont-semibold text-gray-500">
                    <FontAwesomeIcon icon="fas fa-asterisk" class="h-2 text-xs text-red-500 mt-0.5 align-top" fixed-width aria-hidden="true" />
                    {{ trans("Select webpage to redirected") }}
                    <FontAwesomeIcon
                        v-tooltip="trans('Redirect place when user access the closed webpage')"
                        icon="fal fa-info-circle" class="text-gray-400 hover:text-gray-700 cursor-pointer" fixed-width aria-hidden="true" />
                    :
                </div>
                
                <PureMultiselectInfiniteScroll
                    :class="get(form, ['errors', `redirect_webpage_id`]) ? 'errorShake' : ''"
                    :modelValue="form[fieldName].redirect_webpage_id"
                    @update:modelValue="(e) => (set(form, [fieldName, 'redirect_webpage_id'], e))"
                    :initOptions="fieldData?.init_options || []"
                    required
                    :placeholder="trans('Select webpage to redirect')"
                    :fetchRoute="{
                        name: 'grp.org.shops.show.web.webpages.index',
                        parameters: {
                            organisation: route().params.organisation,
                            shop: route().params.shop,
                            website: route().params.website,
                        }
                    }"
                >
                    <template #singlelabel="{ value }">
                        <div class="w-full text-left pl-4 whitespace-nowrap truncate">
                            <Icon :data="value.typeIcon" />
                            {{ value.code }}
                            <span class="text-sm text-gray-400">({{ value.href }})</span>
                        </div>
                    </template>
                    <template #option="{ option, isSelected, isPointed }">
                        <div class="">
                            <Icon :data="option.typeIcon" />
                            {{ option.code }}
                            <span class="text-sm text-gray-400">({{ option.href }})</span>
                        </div>
                    </template>
                </PureMultiselectInfiniteScroll>

                <div v-if="fieldData?.default_storefront?.id" @click="form[fieldName].redirect_webpage_id = fieldData?.default_storefront?.id" class="text-gray-400 hover:text-gray-700 cursor-pointer mt-2 hover:underline w-fit">
                    {{ fieldData?.default_storefront?.code }}
                    <span class="text-sm text-gray-400">({{ fieldData?.default_storefront?.href }})</span>
                </div>
            </div>
        </Transition>

        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600" :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
