<script setup lang="ts">
const props = defineProps<{
    data: {
        data: {
            bank_name: string
            bank_code: string
            iban: string
            account_number: string
        }
    }
}>()
</script>

<template>
    <div class="relative w-full max-w-xl mx-auto my-8 overflow-hidden">
        <div class="mx-auto max-w-md flex flex-col gap-x-4 rounded-xl bg-gray-100 p-6 ring-1 ring-inset ring-white/10">
            <div class="flex items-center justify-between w-full">
                <h3 class="font-semibold">{{ data?.data?.bank_name }}</h3>
                <div class="font-normal text-gray-400 text-sm">
                    {{ data?.data?.bank_code }}
                    {{ data?.data?.iban }}
                </div>
            </div>
            <p class="text-gray-400 italic text-sm">{{ data?.data?.account_number }}</p>
        </div>
    </div>
</template>