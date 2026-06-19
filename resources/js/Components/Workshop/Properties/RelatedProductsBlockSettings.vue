<script setup lang="ts">
import { ref, watch } from 'vue'
import axios from 'axios'
import { debounce } from 'lodash-es'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'

import SideEditorInputHTML from '@/Components/CMS/Fields/SideEditorInputHTML.vue'
import PureInputNumber from '@/Components/Pure/PureInputNumber.vue'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faInfoCircle } from '@fal'

library.add(faInfoCircle)

const props = withDefaults(
    defineProps<{
        modelValue?: {
            title?: string | null
            min_amt_shown?: number | null
            max_amt_shown?: number | null
        }
        updateRoute?: routeType
        uploadRoutes?: routeType
        payloadKeys?: {
            title: string
            min_amt_shown: string
            max_amt_shown: string
        }
    }>(),
    {
        payloadKeys: () => ({
            title: 'title_recommender',
            min_amt_shown: 'min_amt_shown_recommender',
            max_amt_shown: 'max_amt_shown_recommender',
        }),
    }
)

const emits = defineEmits<{
    (e: 'update:modelValue', value: object): void
}>()

const title = ref(props.modelValue?.title ?? null)
const minAmount = ref(props.modelValue?.min_amt_shown ?? 5)
const maxAmount = ref(props.modelValue?.max_amt_shown ?? 100)

watch(
    () => props.modelValue,
    (newVal) => {
        title.value = newVal?.title ?? null
        minAmount.value = newVal?.min_amt_shown ?? 5
        maxAmount.value = newVal?.max_amt_shown ?? 100
    }
)

const saveSettings = async () => {
    if (!props.updateRoute?.name) {
        notify({
            title: trans('Something went wrong'),
            text: trans('Missing the update route for this website.'),
            type: 'error',
        })
        return
    }

    try {
        await axios.patch(
            route(props.updateRoute.name, props.updateRoute.parameters),
            {
                [props.payloadKeys.title]: title.value,
                [props.payloadKeys.min_amt_shown]: minAmount.value,
                [props.payloadKeys.max_amt_shown]: maxAmount.value,
            },
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } }
        )
    } catch (error: any) {
        notify({
            title: trans('Something went wrong'),
            text: error?.response?.data?.message || error?.message || trans('Failed to update settings.'),
            type: 'error',
        })
    }
}

const debouncedSave = debounce(saveSettings, 1000)

const onChange = () => {
    emits('update:modelValue', {
        ...(props.modelValue ?? {}),
        title: title.value,
        min_amt_shown: minAmount.value,
        max_amt_shown: maxAmount.value,
    })
    debouncedSave()
}
</script>

<template>
    <div class="space-y-4">
        <div class="flex items-start gap-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-700">
            <FontAwesomeIcon icon="fal fa-info-circle" class="mt-0.5" fixed-width aria-hidden="true" />
            <span>{{ trans('These settings are applied site-wide, affecting this block on every page across the whole website.') }}</span>
        </div>

        <div class="flex flex-col gap-2">
            <label class="font-medium text-sm">{{ trans('Title') }}</label>
            <SideEditorInputHTML v-model="title" :uploadRoutes="uploadRoutes" @update:modelValue="onChange" />
        </div>

        <div class="flex flex-col gap-2">
            <label class="flex items-center font-medium text-sm">
                {{ trans('Min Amount') }}
                <InformationIcon
                    :information="trans('If product count is less than min amount, then that web block will not be shown')"
                    class="ml-1 opacity-50 hover:opacity-100 cursor-pointer" />
            </label>
            <PureInputNumber v-model="minAmount" :minValue="1" @update:modelValue="onChange" />
        </div>

        <div class="flex flex-col gap-2">
            <label class="flex items-center font-medium text-sm">
                {{ trans('Max Amount') }}
                <InformationIcon
                    :information="trans('If product count is more than max amount, then the exceeding products will not be shown')"
                    class="ml-1 opacity-50 hover:opacity-100 cursor-pointer" />
            </label>
            <PureInputNumber v-model="maxAmount" :minValue="1" @update:modelValue="onChange" />
        </div>
    </div>
</template>
