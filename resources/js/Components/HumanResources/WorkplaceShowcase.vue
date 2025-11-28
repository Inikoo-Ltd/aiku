<script setup lang='ts'>
import { trans } from 'laravel-vue-i18n'

defineProps<{
    data: {
        workplace: {
            address: {
                formatted_address: string
            }
            number_clocking_machines: number
        }
    }
}>()
</script>

<template>
    <dl class="p-4 grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 sm:gap-y-8 lg:gap-x-8">



        <div class="border-t border-gray-200 pt-4">
            <dt class="font-medium">{{ trans("Location") }}</dt>
            <dd class="mt-2 text-sm text-gray-500 text-justify">
                <div v-html="data.workplace.address.formatted_address" class="p-3 border border-gray-300 rounded w-fit">

                </div>
            </dd>
        </div>

        <div class="border-t border-gray-200 pt-4">
            <dt class="font-medium">{{ trans("Number clocking machines") }}</dt>
            <dd class="mt-2 text-sm text-gray-500 text-justify">
                {{data.workplace.number_clocking_machines}}
            </dd>
        </div>



        
    </dl>
</template>