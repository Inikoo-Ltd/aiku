<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import PureInput from "@/Components/Pure/PureInput.vue"
import IconPicker from "@/Components/Pure/IconPicker.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLink, faTimes, faEnvelope, faPhone, faComments, faGlobe, faMapMarkerAlt } from '@fal'
import {
    faWhatsapp,
    faFacebookF,
    faInstagram,
    faXTwitter,
    faTelegram,
    faTiktok,
    faYoutube,
    faLinkedinIn,
    faLine,
    faWeixin,
    faPinterest,
    faSnapchat,
    faDiscord,
} from "@fortawesome/free-brands-svg-icons"
import { library } from "@fortawesome/fontawesome-svg-core"
import { set, get } from 'lodash-es'
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"

const contactIconList = [
    faWhatsapp, faFacebookF, faInstagram, faXTwitter, faTelegram, faTiktok, faYoutube,
    faLinkedinIn, faLine, faWeixin, faPinterest, faSnapchat, faDiscord,
    faEnvelope, faPhone, faComments, faGlobe, faMapMarkerAlt,
]

library.add(faTimes, faLink, ...contactIconList)

defineOptions({ inheritAttrs: false })

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        readonly?: boolean
    }
}>()

interface ContactOption {
    icon: string[] | null
    label: string
    url: string
}

const contactOptions = computed<ContactOption[]>(() => get(props.form, props.fieldName) ?? [])

const addContactOption = () => {
    set(props.form, props.fieldName, [
        ...contactOptions.value,
        {
            icon: null,
            label: '',
            url: '',
        }
    ])
}

const removeContactOption = (index: number) => {
    set(props.form, props.fieldName, contactOptions.value.filter((_, idx) => idx !== index))
}
</script>

<template>
    <div class="w-full">
        <div class="space-y-2">
            <div v-for="(contactOption, index) in contactOptions" :key="index"
                class="group relative rounded-lg border border-gray-300 bg-white p-3 transition hover:border-gray-400">
                <button type="button"
                    class="absolute -right-2 -top-2 flex h-5 w-5 items-center justify-center rounded-full border border-red-300 bg-white text-xxs leading-none text-red-500 shadow-sm transition hover:border-red-400 hover:bg-red-500 hover:text-white"
                    :title="trans('Remove')" @click="removeContactOption(index)">
                    <FontAwesomeIcon :icon="faTimes" aria-hidden="true" />
                </button>

                <div class="grid grid-cols-1 gap-2 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
                    <PureInput v-model="contactOption.label" :placeholder="trans('Label')"
                        :readonly="fieldData?.readonly" class-input="py-1.5 text-xs">
                        <template #prefix>
                            <div class="flex h-full items-center border-r border-gray-200 px-2 text-gray-600">
                                <IconPicker v-model="contactOption.icon" :icon-list="contactIconList"
                                    list-type="custom" value-type="array"
                                    class="h-5 w-5 cursor-pointer rounded hover:bg-gray-100" />
                            </div>
                        </template>
                    </PureInput>

                    <PureInput v-model="contactOption.url" placeholder="https://example.com"
                        :readonly="fieldData?.readonly" class-input="py-1.5 text-xs" />
                </div>
            </div>

            <div v-if="!contactOptions.length"
                class="flex flex-col items-center gap-1 rounded-lg border border-dashed border-gray-300 px-3 py-5 text-center text-gray-400">
                <FontAwesomeIcon :icon="faLink" class="text-base" fixed-width aria-hidden="true" />
                <span class="text-xs">{{ trans("No contact options yet") }}</span>
            </div>

            <Button type="dashed" size="xs" full icon="fal fa-plus" :label="trans('Add contact option')"
                @click="addContactOption" />
        </div>

        <p v-if="get(form, ['errors', `${fieldName}`])" class="mt-2 text-sm text-red-600"
            :id="`${fieldName}-error`">
            {{ form.errors[fieldName] }}
        </p>
    </div>
</template>
