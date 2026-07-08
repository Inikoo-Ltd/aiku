<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import { trans } from 'laravel-vue-i18n'
import PasskeyList from '@/Components/Forms/Fields/PasskeyList.vue'
import { routeType } from '@/types/route'

defineProps<{
    passkeys: {
        value: { id: number, name: string, last_used_at: string | null }[]
        deleteRoute: routeType
    }
}>()
</script>

<template>
    <Head title="Passkeys" />
    <div class="max-w-md mx-auto p-6 space-y-4">
        <h1 class="text-lg font-medium text-gray-900">{{ trans('Passkeys') }}</h1>
        <PasskeyList :fieldData="passkeys" />
    </div>
</template>
