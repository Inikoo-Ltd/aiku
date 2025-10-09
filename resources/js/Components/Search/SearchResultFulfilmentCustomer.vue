<script setup lang='ts'>
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faUser } from '@fal'
import { library } from '@fortawesome/fontawesome-svg-core'
import { Link } from '@inertiajs/vue3'
import { FulfilmentCustomer } from '@/types/fulfilment-customer'
import { useFormatTime } from '@/Composables/useFormatTime'
library.add(faUser)

const props = defineProps<{
    data?: FulfilmentCustomer
}>()
</script>

<template>
    <Link as="a" href="" class="relative flex gap-x-2">
        <div class="bg-slate-100 h-10 aspect-square rounded-md flex justify-center items-center">
            <FontAwesomeIcon icon='fal fa-user' class='text-gray-400' fixed-width aria-hidden='true' />
        </div>
        <div>
            <h3 class="text-sm font-semibold leading-5">
                {{ data?.slug }}
            </h3>
            <ul class="mt-0.5 flex flex-col text-xs font-normal leading-4 text-gray-500">
                <li>Created at: {{ useFormatTime(data?.created_at) }}</li>
                <li>Pallets: {{ data.number_pallets }}</li>
                <!-- <li>{{ data?.shareCount }} shares</li> -->
            </ul>
        </div>
    </Link>
</template>