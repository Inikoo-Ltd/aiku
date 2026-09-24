<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimesCircle, faCheckCircle, faExclamationCircle, faInfoCircle, faTimes } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { computed } from 'vue'
import { trans } from 'laravel-vue-i18n'

library.add(faTimesCircle, faCheckCircle, faExclamationCircle, faInfoCircle, faTimes)


const props = defineProps<{
    notification: {
        class: string
        item: {
            data?: {
                html?: string
                function: Function
            }
            id: number
            title: string
            text: string
            html: string
            type: string
            state: number
            speed: number
            length: number
            timer: number
        }
        close: Function
    }
}>()

const textParts = computed(() =>
    String(props.notification.item.text ?? '')
        .split(/(https?:\/\/[^\s]+)/)
        .filter((value) => value !== '')
        .map((value) => ({ value, isLink: /^https?:\/\//.test(value) }))
)

</script>

<template>
    <div @click="props.notification.close" :class="props.notification.class" class="flex pl-3 pr-4 py-2 gap-x-3">
        <div class="flex items-center justify-center">
            <FontAwesomeIcon v-if="['error', 'failure'].includes(props.notification.item.type)" icon='fal fa-times-circle' class='h-7'
                fixed-width aria-hidden='true' />
            <FontAwesomeIcon v-if="props.notification.item.type == 'success'" icon='fal fa-check-circle' class='h-7'
                fixed-width aria-hidden='true' />
            <FontAwesomeIcon v-if="props.notification.item.type == 'warning'" icon='fal fa-exclamation-circle' class='h-7'
                fixed-width aria-hidden='true' />
            <FontAwesomeIcon v-if="props.notification.item.type == 'info'" icon='fal fa-info-circle' class='h-7'
                fixed-width aria-hidden='true' />
        </div>
        <div class="grid flex-col justify-center">
            <p v-if="props.notification.item.title" class="font-bold">
                {{ props.notification.item.title }}
            </p>

            <p v-if="props.notification.item.text" class="text-sm  mb-0 max-w-full">
                <template v-for="(part, index) in textParts" :key="index">
                    <a v-if="part.isLink" :href="part.value" target="_blank" rel="noopener" class="underline font-semibold break-all" @click.stop>{{ part.value }}</a>
                    <template v-else>{{ part.value }}</template>
                </template>
            </p>
            <div @click.stop="(e) => (props.notification.item.data?.function ? props.notification.item.data?.function() : false)" v-html="props.notification.item.data?.html">

            </div>
        </div>
        <button type="button" class="ml-auto self-start opacity-70 hover:opacity-100" :aria-label="trans('Close')" @click.stop="props.notification.close">
            <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
        </button>
    </div>
</template>