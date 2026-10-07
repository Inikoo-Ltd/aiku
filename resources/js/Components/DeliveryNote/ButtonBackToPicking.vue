<script setup lang="ts">
import ModalConfirmation from "@/Components/Utils/ModalConfirmation.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

defineProps<{
    action: {
        label: string
        tooltip?: string
        style?: string
        icon?: string
        route: routeType
    }
    size?: string
    title?: string
    description?: string
}>()
</script>

<template>
    <ModalConfirmation
        :routeYes="action.route"
        :body="{}"
        :title="title ?? ctrans('Send this delivery note back to picking?')"
        :description="description ?? ctrans('The parts of the set that were not found go back to the picker to look for them again. Nothing is put back on the shelf.')"
        :noLabel="ctrans('No, keep waiting')">
        <template #default="{ changeModel }">
            <Button
                v-tooltip="action.tooltip"
                @click="changeModel"
                :label="action.label"
                :icon="action.icon"
                :type="action.style ?? 'tertiary'"
                :size="size"
                class="whitespace-nowrap" />
        </template>
    </ModalConfirmation>
</template>
