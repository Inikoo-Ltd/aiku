<script setup lang="ts">
import { inject, ref, watch } from 'vue'
import Modal from '../Utils/Modal.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Button from '../Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'

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

const listTrolleys = ref([])
const isLoadingFetch = ref(false)
const fetchTrolleysList = async () => {
    try {
        isLoadingFetch.value = true
        const response = await axios.get(
            route(
                'grp.json.inventory.picking_trolleys.list',
                {
                    organisation: layout.currentParams.organisation,
                    warehouse: props.warehouse.slug,
                }
            )
        )
        
        console.log('Response axios:', response.data)
        listTrolleys.value = response.data.data
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

const selectedTrolley = ref<number | null>(null)
const isLoadingSubmitTrolley = ref(false)
const submitSelectTrolley = (trolleySlug?: number|null) => {
    // Section: Submit
    router.patch(
        route(
            'grp.models.delivery_note.state.handling-with-trolley',
            {
                deliveryNote: props.deliveryNote.id
            }
        ),
        {
            trolley: trolleySlug
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmitTrolley.value = true
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
                    text: trans("Failed to submit"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitTrolley.value = false
            },
        }
    )
}
</script>

<template>
    <div>
        <Button
            :label="trans('Select Trolley')"
            @click="() => isOpenModal = true" 
            icon="fal fa-dolly-flatbed-alt"
        />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="font-bold text-xl text-center mb-8">
                {{ trans("Select trolley to start picking") }}
            </div>

            <div class="mb-1">
                {{ trans("Available trolleys") }} ({{ isLoadingFetch ? '-' : listTrolleys.length }}):
            </div>
            <div class="h-64">
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-if="isLoadingFetch"
                        v-for="trolley in 6"
                        class="h-10 cursor-pointer py-2 px-3 border border-gray-300 text-sm rounded skeleton"
                        
                    />

                    <div
                        v-else
                        v-for="trolley in listTrolleys"
                        :key="trolley.id"
                        @click="() => selectedTrolley == trolley.slug ? selectedTrolley = null : selectedTrolley = trolley.slug"
                        class="cursor-pointer py-2 px-3 border border-gray-300 text-sm rounded"
                        :class="selectedTrolley == trolley.slug ? 'bg-[var(--theme-color-0)] text-[var(--theme-color-1)]' : 'bg-gray-50 hover:bg-gray-200'"
                    >
                        {{ trolley.code }}
                    </div>
                </div>
            </div>

            <Button
                @click="() => submitSelectTrolley(null)"
                :label="trans('Skip')"
                full
                class="mt-4"
                type="tertiary"
                :loading="isLoadingSubmitTrolley"
            />

            <Button
                @click="() => submitSelectTrolley(selectedTrolley)"
                label="Select trolley and start picking"
                full
                iconRight="fas fa-arrow-right"
                class="mt-2"
                :disabled="!selectedTrolley"
                :loading="isLoadingSubmitTrolley"
            />
        </Modal>
    </div>
</template>