<script setup lang="ts">
import { inject, ref, watch } from 'vue'
import Modal from '../Utils/Modal.vue'
import axios from 'axios'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
import { layoutStructure } from '@/Composables/useLayoutStructure'
import Button from '../Elements/Buttons/Button.vue'
import { router } from '@inertiajs/vue3'
import LoadingIcon from '../Utils/LoadingIcon.vue'

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
const fetchBaysList = async () => {
    try {
        isLoadingFetch.value = true
        const response = await axios.get(
            route(
                'grp.json.picked_bays.list',
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
        fetchBaysList()
    }
})

const selectedBay = ref<number | null>(null)
const isLoadingSubmitBay = ref<number|null|undefined>(undefined)
const submitSelectBay = (bayId?: number|null) => {
    // Section: Submit
    router.patch(
        route(
            'grp.models.delivery_note.state.packed_with_picked_bay',
            {
                deliveryNote: props.deliveryNote.id
            }
        ),
        {
            picked_bay: bayId
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmitBay.value = bayId
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
                    text: trans("Failed to submit picked bay"),
                    type: "error"
                })
            },
            onFinish: () => {
                isLoadingSubmitBay.value = undefined
            },
        }
    )
}
</script>

<template>
    <div>
        <Button
            :label="trans('Finish picking')"
            @click="() => isOpenModal = true" 
            icon="fas fa-monument"
        />

        <Modal :isOpen="isOpenModal" width="w-full max-w-2xl" @close="isOpenModal = false">
            <div class="font-bold text-xl text-center mb-8">
                {{ trans("Select picked bay to start packing") }}
            </div>

            <div class="mb-1">
                {{ trans("Available picked bays") }} ({{ isLoadingFetch ? '-' : listBays.length }}):
            </div>
            <div class="h-64">
                <div class="grid grid-cols-3 gap-2">
                    <div
                        v-if="isLoadingFetch"
                        v-for="bay in 6"
                        class="h-10 cursor-pointer py-2 px-3 border border-gray-300 text-sm rounded skeleton"
                        
                    />

                    <template v-else-if="listBays.length">
                        <div
                            v-for="bay in listBays"
                            :key="bay.id"
                            @click="() => submitSelectBay(bay.id)"
                            class="cursor-pointer flex justify-between items-center py-2 px-3 border border-gray-300 text-sm rounded"
                            :class="isLoadingSubmitBay == bay.id ? 'bg-[var(--theme-color-0)] opacity-70 text-[var(--theme-color-1)]' : 'bg-gray-50 hover:bg-gray-200'"
                        >
                            {{ bay.name ?? bay.code ?? bay.slug }}
                            <LoadingIcon v-if="isLoadingSubmitBay == bay.id" />
                        </div>
                    </template>
                    
                    <!-- Section: no bays found -->
                    <div v-else class="flex items-center justify-center w-full col-span-3 pt-3">
                        <div class="text-center border-gray-200 p-14">
                            <h3 class="text-lg font-semibold tracking-wide pb-2">{{ trans("No picked bays found") }}</h3>
                            <a :href="route('grp.org.warehouses.show.dispatching.picked_bays.create', {
                                    organisation: layout.currentParams.organisation,
                                    warehouse: props.warehouse.slug
                                })"
                                target="_blank"
                            >
                                <Button label="Create picked bay" icon="fas fa-plus" size="xs" type="secondary" key="4">

                                </Button>
                            </a>

                        </div>
                    </div>
                </div>
            </div>

            <Button
                @click="() => submitSelectBay(null)"
                :label="trans('Skip')"
                full
                iconRight="far fa-arrow-right"
                class="mt-4"
                type="dashed"
                :disabled="isLoadingSubmitBay !== undefined"
                :loading="isLoadingSubmitBay === null"
            />
        </Modal>
    </div>
</template>