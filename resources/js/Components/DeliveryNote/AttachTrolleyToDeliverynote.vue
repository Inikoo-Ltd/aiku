<script setup lang="ts">
import { inject, ref, watch } from 'vue'
import Modal from '../Utils/Modal.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Button from '../Elements/Buttons/Button.vue'
import { Link, router } from '@inertiajs/vue3'
import LoadingIcon from '../Utils/LoadingIcon.vue'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'

interface Trolley {
    id: number
    name: string
    current_delivery_note: {
        slug: string
        reference: string
    }
}

const props = defineProps<{
    warehouse: {
        slug: string
    }
    deliveryNote: {
        id: number
        slug: string
        reference: string
    }
}>()
const layout = inject('layout', layoutStructure)

const isOpenModal = ref(false)

const listTrolleys = ref<Trolley[]>([])
const isLoadingFetch = ref(false)
const fetchTrolleysList = async () => {
    try {
        isLoadingFetch.value = true
        const response = await axios.get(
            route(
                'grp.json.available_trolleys.list',
                {
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

const listUnavailableTrolleys = ref<Trolley[]>([])
const isLoadingFetchUnavailableTrolleys = ref(false)
const fetchUnavailableTrolleysList = async () => {
    try {
        isLoadingFetchUnavailableTrolleys.value = true
        const response = await axios.get(
            route(
                'grp.json.unavailable_trolleys.list',
                {
                    warehouse: props.warehouse.slug,
                }
            )
        )
        
        console.log('Response unavailable_trolleys:', response.data)
        listUnavailableTrolleys.value = response.data.data
    } catch (error: any) {
        notify({
            title: trans("Something went wrong"),
            text: error.message || trans("Please try again or contact administrator"),
            type: 'error'
        })
    } finally {
        isLoadingFetchUnavailableTrolleys.value = false
    }
}

watch(isOpenModal, (newVal) => {
    if (newVal) {
        fetchTrolleysList()
        fetchUnavailableTrolleysList()
    }
})

const selectedTrolley = ref<number | null>(null)
const isLoadingSubmitTrolley = ref<number|null|undefined>(undefined)
const submitSelectTrolley = (trolleyId?: number|null) => {
    // Section: Submit
    router.patch(
        route(
            'grp.models.delivery_note.trolleys.attach',
            {
                deliveryNote: props.deliveryNote.id,
                trolley: trolleyId
            }
        ),
        {
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmitTrolley.value = trolleyId
            },
            onSuccess: () => {
                isOpenModal.value = false
                // notify({
                //     title: trans("Success"),
                //     text: trans("Successfully submit the data"),
                //     type: "success"
                // })
            },
            onError: errors => {
                notify({
                    title: trans("Something went wrong"),
                    text: trans("Failed to attach trolley"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitTrolley.value = undefined
            },
        }
    )
}

const getUrlDeliveryNote = (deliveryNoteSlug: string) => {
    if (!deliveryNoteSlug) {
        return '#'
    }

    return route('grp.org.warehouses.show.dispatching.delivery_notes.show', {
        organisation: layout.currentParams.organisation,
        warehouse: props.warehouse.slug,
        deliveryNote: deliveryNoteSlug
    })
}
</script>

<template>
    <div>
        <slot name="default" :setOpenModal="() => isOpenModal = !isOpenModal">
            <Button
                :label="trans('Attach trolley')"
                @click="() => isOpenModal = true"
                icon="far fa-plus"
                type="dashed"
                size="xxs"
            />
        </slot>

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="font-bold text-xl text-center">
                {{ trans("Select trolley to attach") }}
            </div>

            <div class="italic opacity-50 text-xs text-center mb-8">
                {{ trans("Delivery Note") }} {{ deliveryNote.reference }}
            </div>

            <div class="mb-1">
                {{ trans("Available trolleys") }} ({{ isLoadingFetch ? '-' : listTrolleys.length }}):
            </div>
            <div class="h-64 overflow-y-auto border-b border-dashed border-gray-300 pb-4">
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-if="isLoadingFetch"
                        v-for="trolley in 6"
                        class="h-10 cursor-pointer py-2 px-3 border border-gray-300 text-sm rounded skeleton"
                        
                    />

                    <template v-else-if="listTrolleys.length">
                        <div
                            v-for="trolley in listTrolleys"
                            :key="trolley.id"
                            @click="() => submitSelectTrolley(trolley.id)"
                            class="cursor-pointer flex justify-between items-center py-2 px-3 border border-gray-300 text-sm rounded"
                            :class="isLoadingSubmitTrolley == trolley.id ? 'bg-[var(--theme-color-0)] opacity-70 text-[var(--theme-color-1)]' : 'bg-gray-50 hover:bg-gray-200'"
                        >
                            {{ trolley.name }}
                            <LoadingIcon v-if="isLoadingSubmitTrolley == trolley.id" />
                        </div>
                    </template>
                    
                    <!-- Section: no trolleys found -->
                    <div v-else class="flex items-center justify-center w-full col-span-3 pt-3">
                        <div class="text-center border-gray-200 p-14">
                            <h3 class="text-lg font-semibold tracking-wide pb-2">{{ trans("No trolleys found") }}</h3>
                            <a :href="route('grp.org.warehouses.show.dispatching.trolleys.create', {
                                    organisation: layout.currentParams.organisation,
                                    warehouse: props.warehouse.slug
                                })"
                                target="_blank"
                            >
                                <Button label="Create Trolley" icon="fas fa-plus" size="xs" type="secondary" key="4">

                                </Button>
                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <!-- Section: unavailable trolleys -->
            <div class="mb-1 text-red-500 text-sm">
                {{ trans("Unavailable trolleys") }} ({{ isLoadingFetchUnavailableTrolleys ? '-' : listUnavailableTrolleys.length }}):
            </div>
            <div class="xh-64">
                <div class="grid grid-cols-5 gap-2">
                    <div
                        v-if="isLoadingFetchUnavailableTrolleys"
                        v-for="trolley in 6"
                        class="h-10 py-0.5 px-1 border border-gray-300 text-sm rounded-sm skeleton"
                        
                    />

                    <template v-else-if="listUnavailableTrolleys.length">
                        <div
                            v-for="trolley in listUnavailableTrolleys"
                            :key="trolley.id"
                            class="py-0.5 px-1 bg-gray-300 border border-gray-400 text-xs rounded-sm"
                        >
                            {{ trolley.name }}
                            <span class="whitespace-nowrap">(<FontAwesomeIcon icon="fal fa-truck" class="opacity-70 mr-0.5" fixed-width aria-hidden="true" /><Link :href="getUrlDeliveryNote(trolley.current_delivery_note?.slug)" class="font-bold opacity-60 hover:opacity-100 cursor-pointer">{{ trolley.current_delivery_note?.reference }}</Link>)</span>
                            <LoadingIcon v-if="isLoadingSubmitTrolley == trolley.id" />
                        </div>
                    </template>
                </div>
            </div>
        </Modal>
    </div>
</template>