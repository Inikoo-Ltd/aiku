<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPallet } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link, router } from '@inertiajs/vue3'
import { routeType } from '@/types/route'
library.add(faPallet)

const props = defineProps<{
    data: {
        title?: string
        subtitle?: string
        label1?: string
        label2?: string
        route?: routeType
        icon?: string[]
    }
}>()

const emits = defineEmits<{
    (e: 'finishVisit'): void
}>()

router.on('success', () => emits('finishVisit'))  // On success component 'Link'
</script>

<template>
    <component :is="data?.route?.name ? Link : 'div'" as="a" :href="data.route?.name ? route(data.route?.name, data.route?.parameters) : '#'"
        class="relative flex gap-x-2 items-center"
    >
        <div v-if="data.icon" class="bg-slate-100 shadow ring-1 ring-slate-200 h-10 aspect-square rounded-md flex justify-center items-center">
            <FontAwesomeIcon :icon='data.icon' class='text-gray-500' fixed-width aria-hidden='true' />
        </div>
        
        <div>
            <h3 v-if="data?.title || data?.subtitle" class="text-sm font-semibold leading-5">
                {{ data?.title }}
                <span v-if="data.subtitle" class="font-normal text-sm">({{ data?.subtitle }})</span>
            </h3>
            <ul class="mt-1 flex space-x-1 text-xs font-normal leading-4 text-gray-500">
                <li>{{ data.label1 || '' }}</li>
                <li>&middot;</li>
                <li class="">{{ data?.label2 }}</li>
                <!-- <li>&middot;</li> -->
                <!-- <li>{{ data?.shareCount }} shares</li> -->
            </ul>
        </div>
    </component>
</template>