<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import { aikuLocaleStructure } from '@/Composables/useLocaleStructure'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { trans } from 'laravel-vue-i18n'
import { inject } from 'vue'

const props = defineProps<{
    data: {
        amount: number
        currency_code: string
        status: string
    }
}>()

const locale = inject('locale', aikuLocaleStructure)
</script>

<template>
    <div>
        <dl
            class="px-4 mt-5 grid grid-cols-1 divide-gray-200 overflow-hidden rounded-lg md:grid-cols-3 md:divide-x md:divide-y-0">
            <div class="px-4 py-5 sm:p-6 border border-gray-300 rounded-md">
                <dt class="text-base font-normal">
                    {{ trans("Amount") }}
                    <span v-tooltip="useFormatTime(data.created_at, {formatTime: 'hm'})" class="text-gray-400">({{ useFormatTime(data.created_at) }})</span>
                </dt>
                <dd class="mt-1 flex items-baseline justify-between md:block lg:flex">
                    <div class="flex items-baseline text-2xl font-semibold text-indigo-600">
                        {{locale.currencyFormat(data.currency_code, data.amount)}}
                    </div>

                    <div
                        :class="[data.status === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800', 'inline-flex items-center gap-x-1 rounded-full px-2.5 py-0.5 text-sm font-medium md:mt-2 lg:mt-0']">
                        <FontAwesomeIcon icon="fal fa-check" class="" fixed-width aria-hidden="true" />
                        {{ data.status }}
                    </div>
                </dd>
            </div>
        </dl>
    </div>
</template>