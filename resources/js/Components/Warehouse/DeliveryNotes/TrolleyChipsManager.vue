<script setup lang="ts">
import { computed } from "vue"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faDolly, faTimes, faPlus } from "@fal"
import { RouteParams } from "@/types/route-params"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import AttachTrolleyToDeliverynote from "@/Components/DeliveryNote/AttachTrolleyToDeliverynote.vue"

library.add(faDolly, faTimes, faPlus)

const props = defineProps<{
    deliveryNote: {
        id: number
        slug: string
        reference: string
    }
    trolleys?: {
        id: number
        name: string
    }[]
    isEditable?: boolean
}>()

const warehouse = computed(() => ({ slug: String((route().params as RouteParams).warehouse ?? '') }))

const trolleyList = computed(() => props.trolleys ?? [])
</script>

<template>
    <div v-if="warehouse.slug" class="flex flex-wrap items-center gap-1">
        <span
            v-for="trolley in trolleyList"
            :key="trolley.id"
            v-tooltip="trans('Trolley')"
            class="inline-flex items-center gap-x-1 text-xs text-gray-500 bg-gray-100 border rounded px-1.5 py-0.5"
        >
            <FontAwesomeIcon icon="fal fa-dolly" fixed-width aria-hidden="true" />
            {{ trolley.name }}

            <ModalConfirmationDelete
                :title="trans('Are you sure you want to unselect Trolley :trolleyName ?', { trolleyName: trolley.name })"
                :description="trans('The trolley will unselected from this Delivery. You can add it again if you want.')"
                :noLabel="trans('Yes, unselect')"
                noIcon=""
                :routeDelete="{
                    method: 'patch',
                    name: 'grp.models.delivery_note.trolleys.detach',
                    parameters: {
                        deliveryNote: deliveryNote.id,
                        trolley: trolley.id,
                    },
                }"
            >
                <template #default="{ changeModel }">
                    <span v-if="isEditable" @click="changeModel" v-tooltip="trans('Detach trolley')" class="text-red-400 hover:text-red-600 cursor-pointer">
                        <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                    </span>
                </template>
            </ModalConfirmationDelete>
        </span>

        <AttachTrolleyToDeliverynote
            v-if="isEditable"
            :warehouse="warehouse"
            :deliveryNote="deliveryNote"
        >
            <template #default="{ setOpenModal }">
                <span
                    @click="setOpenModal()"
                    v-tooltip="trans('Attach trolley')"
                    class="inline-flex items-center gap-x-1 text-xs text-gray-400 hover:text-gray-600 border border-dashed border-gray-300 hover:border-gray-400 rounded px-1.5 py-0.5 cursor-pointer"
                >
                    <FontAwesomeIcon icon="fal fa-plus" fixed-width aria-hidden="true" />
                    <template v-if="!trolleyList.length">{{ trans("Trolley") }}</template>
                </span>
            </template>
        </AttachTrolleyToDeliverynote>
    </div>
</template>
