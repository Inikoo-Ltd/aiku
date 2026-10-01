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
        parts: { code: string | null, name: string | null, quantity: number, locations?: string[] }[]
    }
    size?: string
}>()
</script>

<template>
    <ModalConfirmation
        :routeYes="action.route"
        :body="{}"
        :title="ctrans('Are these parts back on the shelf?')"
        :noLabel="ctrans('Not yet')">
        <template #description>
            <div class="mt-2 space-y-3 text-sm text-gray-500">
                <p>
                    {{ ctrans('A part of a set sold only complete was not found, so the whole product can not be sent and the customer is refunded for it.') }}
                </p>
                <p class="font-medium text-gray-700">
                    {{ ctrans('Before you confirm, put these parts back on the shelf:') }}
                </p>
                <ul class="divide-y divide-gray-100 rounded border border-gray-200">
                    <li v-for="part in action.parts" :key="part.code ?? ''" class="flex items-baseline gap-x-2 px-3 py-2">
                        <span class="font-semibold tabular-nums text-red-600">{{ Math.round(part.quantity * 100) / 100 }}&times;</span>
                        <span class="min-w-0 flex-1">
                            <span class="block font-medium text-gray-700">{{ part.code }}</span>
                            <span class="block text-xs text-gray-400">{{ part.name }}</span>
                        </span>
                        <span v-if="part.locations?.length" class="whitespace-nowrap text-xs text-gray-500">
                            {{ ctrans('to :locations', { locations: part.locations.join(', ') }) }}
                        </span>
                    </li>
                </ul>
                <p class="text-xs">
                    {{ ctrans('Confirming takes these parts off the delivery note and returns them to stock, so only confirm once they really are back on the shelf.') }}
                </p>
            </div>
        </template>
        <template #btn-yes="{ isLoadingdelete, clickYes }">
            <Button
                :loading="isLoadingdelete"
                :label="ctrans('Yes, they are back on the shelf')"
                type="save"
                @click="clickYes" />
        </template>
        <template #default="{ changeModel }">
            <Button
                v-tooltip="action.tooltip"
                @click="changeModel"
                :label="action.label"
                :icon="action.icon"
                :type="action.style ?? 'save'"
                :size="size"
                class="whitespace-nowrap" />
        </template>
    </ModalConfirmation>
</template>
