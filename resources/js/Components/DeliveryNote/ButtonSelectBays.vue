<script setup lang="ts">
import { inject, ref, watch } from 'vue'
import Modal from '../Utils/Modal.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import { router } from '@inertiajs/vue3'

import Button from '../Elements/Buttons/Button.vue'
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faMonument } from "@fas"
import { library } from "@fortawesome/fontawesome-svg-core"
library.add(faMonument)

const props = defineProps<{
    warehouse: {
        slug: string
    }
    deliveryNote: {
        id: number
        slug: string
        refrence: string
    }
}>()
const layout = inject('layout', layoutStructure)

const isOpenModal = ref(false)

const listBays = ref([])
const isLoadingFetch = ref(false)
const fetchTrolleysList = async () => {
    try {
        isLoadingFetch.value = true
        const response = await axios.get(
            route(
                'grp.org.warehouses.show.inventory.picked_bays.index',
                {
                    organisation: layout.currentParams.organisation,
                    warehouse: props.warehouse.slug,
                }
            )
        )
        
        console.log('Response axios:', response.data)
        listBays.value = response.data.data
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    } finally {
        isLoadingFetch.value = false
    }
}

watch(isOpenModal, (newVal) => {
    if (newVal) {
        fetchTrolleysList()
    }
})

const selectedBay = ref<number | null>(null)
const isLoadingSubmitBay = ref(false)
const submitSelectBay = () => {
    // Section: Submit
    router.patch(
        route(
            'grp.models.delivery_note.state.packed-with-picking-bay',
            {
                deliveryNote: props.deliveryNote.id
            }
        ),
        {
            picking_bay: selectedBay.value
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmitBay.value = true
            },
            onSuccess: () => {
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
                    type: "success"
                })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to submit bay"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitBay.value = false
            },
        }
    )
}
</script>

<template>
    <div>
        <Button
            :label="trans('Select picked bays')"
            @click="() => isOpenModal = true" 
            icon="fas fa-monument"
        />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="font-bold text-xl text-center mb-8">
                {{ trans("Select picked bay to start packing") }}
            </div>

            <div class="mb-1">
                {{ trans("Available picked bay") }} ({{ isLoadingFetch ? '-' : listBays.length }}):
            </div>
            <div class="h-64">
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-if="isLoadingFetch"
                        v-for="bay in 6"
                        class="h-10 cursor-pointer py-2 px-3 border border-gray-300 text-sm rounded skeleton"
                        
                    />

                    <div
                        v-else
                        v-for="bay in listBays"
                        :key="bay.id"
                        @click="() => selectedBay = bay.slug"
                        class="cursor-pointer py-2 px-3 border border-gray-300 text-sm rounded"
                        :class="selectedBay == bay.slug ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'bg-gray-50 hover:bg-gray-200'"
                    >
                        {{ bay.code }}
                    </div>
                </div>
            </div>

            <Button
                @click="() => submitSelectBay()"
                label="Select bay and start packing"
                full
                iconRight="fas fa-arrow-right"
                class="mt-4"
                :disabled="!selectedBay"
                :loading="isLoadingSubmitBay"
            />
        </Modal>
    </div>
</template>