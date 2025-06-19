<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faTimesCircle, faCheckCircle, faExclamationCircle, faInfoCircle } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'

library.add(faTimesCircle, faCheckCircle, faExclamationCircle, faInfoCircle)


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

</script>

<template>
    <div @click="props.notification.close" :class="props.notification.class" class="flex pl-3 pr-4 py-2 gap-x-3">
        <div class="flex items-center justify-center">
            <FontAwesomeIcon v-if="props.notification.item.type == 'error'" icon='fal fa-times-circle' class='h-7'
                aria-hidden='true' />
            <FontAwesomeIcon v-if="props.notification.item.type == 'success'" icon='fal fa-check-circle' class='h-7'
                aria-hidden='true' />
            <FontAwesomeIcon v-if="props.notification.item.type == 'warning'" icon='fal fa-exclamation-circle' class='h-7'
                aria-hidden='true' />
            <FontAwesomeIcon v-if="props.notification.item.type == 'info'" icon='fal fa-info-circle' class='h-7'
                aria-hidden='true' />
        </div>
        <div class="grid flex-col justify-center">
            <p v-if="props.notification.item.title" class="font-bold">
                {{ props.notification.item.title }}
            </p>
            <!-- <button class="close" @click="props.notification.close">
                        <i class="fa fa-fw fa-close"></i>
                    </button> -->
            <p v-if="props.notification.item.text" class="text-sm truncate mb-0 max-w-full">
                {{ props.notification.item.text }}
            </p>
            <div @click.stop="(e) => (props.notification.item.data?.function())" v-html="props.notification.item.data?.html">
                
            </div>
        </div>
    </div>
</template>