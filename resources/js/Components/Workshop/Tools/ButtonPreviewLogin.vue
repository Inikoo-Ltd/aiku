<script setup lang='ts'>
import { ctrans } from '@/Composables/useTrans'
import Toggle from "@/Components/Pure/Toggle.vue"

const model = defineModel<boolean>({
    required: true
})

</script>

<template>
    <div class="flex items-center gap-x-2">
        <div :class="!model ? 'text-gray-600' : 'text-gray-400'">
            {{ ctrans("Logged out") }}
        </div>
        <Toggle
            v-model="model"
        />
        <div :class="model ? 'text-gray-600' : 'text-gray-400'">
            {{ ctrans("Logged in") }}
        </div>
    </div>
</template>