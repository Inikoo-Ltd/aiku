<script setup lang="ts">
import { paymentProviderLogo } from "@/Composables/usePaymentProviderLogo"

defineProps<{
    label?: string | null
    method?: string | null
    accountType?: string | null
    accountName?: string | null
}>()
</script>

<template>
    <span class="inline-flex items-center gap-1.5">
        <span>{{ label || method || '-' }}</span>
        <img v-if="accountType && accountType !== method && paymentProviderLogo(accountType)"
            :src="paymentProviderLogo(accountType)" :alt="accountName || accountType" :title="accountName || accountType"
            class="h-3 w-auto max-w-16 opacity-60" />
    </span>
</template>
