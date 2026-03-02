<script setup lang="ts">
import { trans } from 'laravel-vue-i18n'
import ButtonSelectTrolleys from './ButtonSelectTrolleys.vue'
import Button from '../Elements/Buttons/Button.vue'
import ModalConfirmationDelete from '../Utils/ModalConfirmationDelete.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import AttachTrolleyToDeliverynote from './AttachTrolleyToDeliverynote.vue'

const props = defineProps<{
    trolleys: {
        id: number
        name: string
    }[]
    deliveryNote: {
        id: number
        state: string
        slug: string
        reference: string
    }
    warehouse: {
        slug: string
    }
}>()
</script>

<template>
    <div class="!mt-1.5 flex gap-x-2 items-center">
        <dl v-tooltip="trans('Trolleys selected')"
            class="border-l-4 border-pink-300 bg-pink-100 pl-1 flex items-center w-fit pr-1 py-0.5 flex-none gap-x-1.5">
            <dt class="flex-none">
                {{ trans("Trolleys") }} ({{ trolleys?.length }}):
            </dt>
            <dd class="flex flex-wrap gap-x-2 text-gray-500 align-middle">
                <div v-for="trolley in trolleys" class="bg-black/10 rounded-sm px-0.5 flex items-center">
                    {{ trolley.name }}

                    <!-- Section: detach Trolley -->
                    <ModalConfirmationDelete
                        :title="trans('Are you sure you want to unselect Trolley :trolleyName ?', { trolleyName: trolley.name})"
                        :description="trans('The trolley will unselected from this Delivery. You can add it again if you want.')"
                        :noLabel="trans('Yes, unselect')"
                        noIcon=""
                        :routeDelete="{
                            method: 'patch',
                            name: 'grp.models.delivery_note.trolleys.detach',
                            parameters: {
                                deliveryNote: deliveryNote.id,
                                trolley: trolley.id
                            }
                        }"
                    >
                        <template #default="{ changeModel }">
                            <span @click="changeModel" class="text-red-400 hover:text-red-600 cursor-pointer">
                                <FontAwesomeIcon icon="fal fa-times" class="" fixed-width aria-hidden="true" />
                            </span>
                        </template>
                    </ModalConfirmationDelete>
                </div>
                <!-- <Button type="dashed" :label="trans('Select new trolley')" icon="far fa-plus" size="xxs" /> -->
            </dd>
        </dl>

        <template v-if="['handling', 'picked'].includes(deliveryNote.state)">
            <AttachTrolleyToDeliverynote
                :warehouse="warehouse"
                :deliveryNote="deliveryNote"
            />
            
            <!-- <ButtonSelectTrolleys
                :warehouse="warehouse"
                :deliveryNote="deliveryNote"
            >
                <template #default="{ setOpenModal }">
                    <Button @click="setOpenModal()" type="dashed" :label="trans('Attach trolley')" size="xxs" />
                </template>
            </ButtonSelectTrolleys> -->
        </template>
    </div>
</template>