<script setup lang="ts">
import { computed, ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faWhatsapp, faInstagram, faFacebookMessenger } from "@fortawesome/free-brands-svg-icons"
import Button from '@/Components/Elements/Buttons/Button.vue'

library.add(faWhatsapp, faInstagram, faFacebookMessenger)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData?: any
}>()

// ponytail: local state, nothing is persisted yet. Sync to form[fieldName] once the Meta connect flow lands.
const selected = ref<string | null>(props.form?.[props.fieldName] ?? null)

const select = (option: any) => {
    if (!option.available) {
        return
    }
    selected.value = option.value
}

const selectedOption = computed(() => props.fieldData?.options?.find((option: any) => option.value === selected.value))
</script>

<template>
    <div class="flex flex-col gap-4">
        <div class="grid gap-3 sm:grid-cols-3">
            <div
                v-for="option in fieldData.options"
                :key="option.value"
                @click="select(option)"
                :class="[
                    'relative flex flex-col rounded-lg border p-3 shadow-sm transition-all',
                    option.available ? 'cursor-pointer' : 'cursor-not-allowed opacity-60',
                    selected === option.value ? 'border-indigo-400 bg-indigo-100' : 'border-gray-300',
                    option.available && selected !== option.value ? 'hover:bg-gray-50' : ''
                ]"
            >
                <div class="flex items-center gap-2">
                    <FontAwesomeIcon v-if="option.icon" :icon="option.icon" class="text-lg text-gray-600" fixed-width aria-hidden="true" />
                    <span class="text-sm font-medium text-gray-700">{{ option.title }}</span>
                </div>

                <span v-if="option.description" class="mt-1 text-xs text-gray-500">
                    {{ option.description }}
                </span>

                <span v-if="!option.available" class="mt-2 w-fit rounded-full bg-gray-200 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-600">
                    {{ trans('Coming soon') }}
                </span>
            </div>
        </div>

        <div v-if="selectedOption">
            <Button
                type="tertiary"
                :label="trans('Connect :channel', { channel: selectedOption.title })"
                :icon="selectedOption.icon"
                disabled
                v-tooltip="trans('Coming soon')"
            />
        </div>
    </div>
</template>
