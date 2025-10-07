<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Tue, 14 Mar 2023 23:44:10 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import EmailSubscribetion from '@/Components/EmailSubscribetion.vue'

const props = defineProps<{
    form: any
    fieldName: string
    options?: any
    fieldData?: {
        type: string
        placeholder: string
        readonly?: boolean
        copyButton: boolean
        maxLength?: number
    }
}>()


</script>
<template>
    <EmailSubscribetion :emailSubscriptions="fieldData.customer.email_subscriptions" containerClass="w-full border p-2 rounded-md" />
</template>