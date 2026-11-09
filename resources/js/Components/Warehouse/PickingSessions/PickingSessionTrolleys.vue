<script setup lang="ts">
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDolly, faTimes, faPlus } from "@fal"
import { routeType } from "@/types/route"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import AttachTrolleyToDeliverynote from "@/Components/DeliveryNote/AttachTrolleyToDeliverynote.vue"

library.add(faDolly, faTimes, faPlus)

const props = defineProps<{
    pickingSessionReference: string
    warehouse: { slug: string }
    trolleys: { id: number, slug: string, name: string }[]
    canChange: boolean
    attachRoute: routeType
    detachRoute: routeType
}>()
</script>

<template>
    <div class="flex flex-wrap items-center gap-1.5 text-sm">
        <span class="text-gray-500">{{ ctrans("Trolleys") }}:</span>

        <span
            v-for="trolley in trolleys"
            :key="trolley.id"
            class="inline-flex items-center gap-x-1 text-gray-700 bg-gray-100 border rounded px-1.5 py-0.5"
        >
            <FontAwesomeIcon icon="fal fa-dolly" fixed-width aria-hidden="true" />
            {{ trolley.name }}

            <ModalConfirmationDelete
                v-if="canChange"
                :title="ctrans('Take trolley :trolleyName off this picking session?', { trolleyName: trolley.name })"
                :description="ctrans('Replenishment will no longer see this trolley for the delivery notes of this session.')"
                :noLabel="ctrans('Yes, take it off')"
                noIcon=""
                :routeDelete="{
                    method: 'patch',
                    name: detachRoute.name,
                    parameters: { ...detachRoute.parameters, trolley: trolley.id },
                }"
            >
                <template #default="{ changeModel }">
                    <span @click="changeModel" v-tooltip="ctrans('Take off')" class="text-red-400 hover:text-red-600 cursor-pointer">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </span>
                </template>
            </ModalConfirmationDelete>
        </span>

        <span v-if="!trolleys.length && !canChange" class="italic text-xs text-gray-400">
            {{ ctrans("None") }}
        </span>

        <AttachTrolleyToDeliverynote
            v-if="canChange"
            :warehouse="warehouse"
            :attachRoute="attachRoute"
            :subtitle="`${ctrans('Picking session')} ${pickingSessionReference}`"
        >
            <template #default="{ setOpenModal }">
                <span
                    @click="setOpenModal()"
                    v-tooltip="ctrans('Tell replenishment which trolley holds the picks of this session')"
                    class="inline-flex items-center gap-x-1 text-xs text-gray-500 hover:text-gray-700 border border-dashed border-gray-300 hover:border-gray-400 rounded px-1.5 py-0.5 cursor-pointer"
                >
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    {{ ctrans("Trolley") }}
                </span>
            </template>
        </AttachTrolleyToDeliverynote>
    </div>
</template>
