<script setup lang="ts">
import WhatsAppDashboardOptIn, { type WhatsappOptInData } from "@/Components/Retina/Dashboard/WhatsAppDashboardOptIn.vue"

defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData: {
        type: string
        optin: WhatsappOptInData | null
    }
}>()
</script>

<template>
    <WhatsAppDashboardOptIn v-if="fieldData.optin" :data="fieldData.optin" />
</template>
