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
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import { routeType } from '@/types/route'
library.add(faAsterisk, faBroadcastTower)

// const props = defineProps(['form', 'fieldName', 'fieldData'])
const props = defineProps<{
    form: any,
    fieldName: string,
    fieldData: {
        current_state: string
        route_delete: routeType
        init_options?: {}[]
        default_storefront?: {}
    }
}>()

const optionxx = [
    { value: 'closed', label: 'offline' },
    { value: 'live', label: 'online' },
]

const xxx = ref('')
</script>

<template>
    <div class="mx-auto w-fit">
        <!-- Set redirected -->
        <Transition
            v-if="fieldData.current_state === 'closed' || fieldData.current_state === 'live'"
            name="slide-to-left"
        >
            <div class="mt-4 w-full max-w-sm">
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

                <div v-if="fieldData?.default_storefront?.id" @click="form[fieldName].redirect_webpage_id = fieldData?.default_storefront?.id" class="text-xs text-gray-400 hover:text-gray-700 cursor-pointer mt-2 underline w-fit">
                    {{ trans("Click to set redirect to ") }} 
                    <Icon :data="fieldData?.default_storefront?.typeIcon" />
                    {{ fieldData?.default_storefront?.code }}
                    <span class="text-gray-400">({{ fieldData?.default_storefront?.href }})</span>
                </div>
            </div>
        </Transition>

        
        <div class="mt-4 w-fit max-w-sm">
            <ButtonWithLink
                v-if="fieldData.current_state === 'closed' || fieldData.current_state === 'live'"
                icon="far fa-trash-alt"
                label="Delete Webpage"
                type="negative"
                :routeTarget="fieldData.route_delete"
                :disabled="!form[fieldName].redirect_webpage_id"
                v-tooltip="form[fieldName].redirect_webpage_id ? trans('Select webpage to redirect before delete') : ''"
            />

            <ButtonWithLink
                v-else
                icon="far fa-trash-alt"
                label="Delete Webpage"
                type="negative"
                :routeTarget="fieldData.route_delete"
            />
        </div>
    </div>
</template>
