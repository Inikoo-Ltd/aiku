<script setup lang="ts">
import { useFormatTime } from '@/Composables/useFormatTime'
import UnderConstruction from '@/Pages/Grp/Disclosure/UnderConstruction.vue'
import { Link } from '@inertiajs/vue3'
import { ctrans } from '@/Composables/useTrans'

const props = defineProps<{
    data: {

    }
}>()

</script>

<template>
    <div class="pl-6">
        <div class="mt-6 py-3 rounded border border-gray-300 px-6 space-y-3 max-w-lg">
            <div class="font-semibold">{{ ctrans("Picked Bay Information") }}</div>

            <div class="grid grid-cols-5 gap-x-10 text-sm">
                <div class="col-span-2 text-gray-400">
                    {{ ctrans("Date created") }}
                </div>
                
                <div class="col-span-3 font-medium text-right lg:text-left">
                    {{ useFormatTime(data.created_at) }}
                </div>
            </div>

            <div class="grid grid-cols-5 gap-x-10 text-sm">
                <div class="col-span-2 text-gray-400">
                    {{ ctrans("Name") }}
                </div>
                
                <div class="col-span-3 font-medium text-right lg:text-left">
                    {{ data.name ?? '-' }}
                </div>
            </div>

            <div class="grid grid-cols-5 gap-x-10 text-sm">
                <div class="col-span-2 text-gray-400">
                    {{ ctrans("Delivery note") }}
                </div>

                <div class="col-span-3 font-medium text-right lg:text-left">
                    {{ data.delivery_note ?? '-' }}
                </div>
            </div>
        </div>
    </div>
</template>