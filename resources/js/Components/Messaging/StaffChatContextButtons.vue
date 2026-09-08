<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 22 Aug 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { useStaffMessaging } from "@/Stores/staff-messaging"

const props = defineProps<{
    context: { context_type: string; context_id: number; audiences: { key: string; label: string }[] }
}>()

const store = useStaffMessaging()
const loadingKey = ref<string | null>(null)

const open = async (audience: string) => {
    loadingKey.value = audience
    try {
        await store.openContext(props.context.context_type, props.context.context_id, audience)
    } finally {
        loadingKey.value = null
    }
}
</script>

<template>
    <div class="flex items-center gap-x-2">
        <Button
            v-for="audience in context.audiences"
            :key="audience.key"
            type="tertiary"
            icon="fal fa-comments"
            :label="audience.label"
            :loading="loadingKey === audience.key"
            @click="open(audience.key)"
        />
    </div>
</template>
