<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import UnderConstruction from '@/Pages/Iris/Disclosure/UnderConstruction.vue'
import { Link } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    data: {

    }
}>()

</script>

<template>
    <div class="pl-6">
        <div class="mt-6 py-3 rounded border border-gray-300 px-6 space-y-3 max-w-lg">
            <div class="font-semibold">{{ trans("Picked Bay Information") }}</div>

            <div class="grid grid-cols-5 gap-x-10 text-sm">
                <div class="col-span-2 text-gray-400">
                    {{ trans("Date created") }}
                </div>
                
                <div class="col-span-3 font-medium text-right lg:text-left">
                    {{ useFormatTime(data.created_at) }}
                </div>
            </div>

            <div class="grid grid-cols-5 gap-x-10 text-sm">
                <div class="col-span-2 text-gray-400">
                    {{ trans("Name") }}
                </div>
                
                <div class="col-span-3 font-medium text-right lg:text-left">
                    {{ data.name ?? '-' }}
                </div>
            </div>

            <div class="grid grid-cols-5 gap-x-10 text-sm">
                <div class="col-span-2 text-gray-400">
                    {{ trans("Delivery note") }}
                </div>

                <div class="col-span-3 font-medium text-right lg:text-left">
                    {{ data.delivery_note ?? '-' }}
                </div>
            </div>
        </div>
    </div>
</template>