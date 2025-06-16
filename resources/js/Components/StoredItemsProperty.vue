<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref, watch } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from "@kyvg/vue3-notification"
import CreateStoredItems from "./CreateStoredItems.vue"
import Tag from '@/Components/Tag.vue'
import { get } from 'lodash-es'
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"


const props = defineProps<{
    pallet: {
        storeStoredItemRoute : routeType,
        stored_items :  Array<any>;
    }
    saveRoute : routeType
    storedItemsRoute: {
		store: routeType
		index: routeType
		delete: routeType
	}
    editable?: boolean
    title?: string
    prefixQuery?: string   // from filter[global] to stored_items_filter[global]
    sendToServerOptions?: {
        preserveScroll?: boolean
        preserveState?: boolean
    }
    isModalOpened?: boolean
    storedItemToEdit?: {
        id: string
        quantity: number
        reference: string
    }
}>()

const emits = defineEmits<{
    (e: 'renderTable'): void
    (e: 'onCloseModal'): void
    (e: 'onSuccessSubmit'): void
}>()

// Section: modal condition
const isModalOpen = ref(props.isModalOpened || false)
watch(() => props.isModalOpened, (value) => {
    isModalOpen.value = value
})

// Form
const form = useForm({ id: props.storedItemToEdit?.id, quantity: props.storedItemToEdit?.quantity || 1, oldData: props.storedItemToEdit })
watch(() => props.storedItemToEdit, (value) => {
    form.id = value?.id
    form.quantity = value?.quantity || 1
    form.oldData = value
})
const setFormOnEdit = (data) => {
    form.id = data.id
    form.quantity = data.quantity
    form.oldData = data
    isModalOpen.value = true
}

const setFormOnCreate = () => {
    form.reset()
    isModalOpen.value = true
}

const onDelete = (data : { id : ''}) => {
    const stored_items = [...props.pallet?.stored_items].filter((item) => item.id != data.id)
    const finalData = {}
    for (const d of stored_items) finalData[d.id] = { quantity: d.quantity }
    sendToServer(finalData)
}

const sendToServer = async (data : {}, replaceData?: boolean) => {
    // console.log('-=-=-=-=', props.saveRoute.name, props.saveRoute.parameters)
    router.post(route(props.saveRoute.name, props.saveRoute.parameters), replaceData ? data : { stored_item_ids: data }, {
        ...props.sendToServerOptions,
        onError: (e) => {
            form.errors = {
                id: get(e, [`stored_item_ids`])
            }
            notify({
                title: trans("Something went wrong"),
                text: trans("Failed to update the stored items"),
                type: "error"
            })
        },
        onSuccess: (e) => {
            emits('renderTable')
            emits('onSuccessSubmit')
            isModalOpen.value = false
            form.reset()
            form.errors = {}
        },
        onBefore: () => {
            form.processing = true
        },
        onFinish: () => {
            form.processing = false
        }
    })
}


</script>

<template>
    <div class="flex">
        <slot :openModal="setFormOnCreate">
            <div class="flex gap-x-1.5 gap-y-1.5 flex-wrap">
                <template v-if="pallet?.stored_items?.length">
                    <div v-for="item of pallet.stored_items" class="cursor-pointer">
                        <Tag @onClose="(event) => { event.stopPropagation(), onDelete(item) }" :theme="item.id"
                            :label="`${item.reference}`"
                            :closeButton="editable ? true : false" :stringToColor="true"
                            @click="() => editable ? setFormOnEdit(item) : null"
                        >
                            <template #label>
                                <div class="whitespace-nowrap text-xs">
                                    {{ item.reference }} (<span class="font-light">{{ item.quantity }}</span>)
                                </div>
                            </template>
                        </Tag>
                    </div>
                </template>
                
                        <div v-else-if="!editable" class="pl-2.5 text-gray-400">
                    -
                </div>
                <Button v-if="editable" icon="fal fa-plus" @click="setFormOnCreate" :type="'dashed'" :size="'xs'"/>
            </div>
        </slot>
        
        <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false, emits('onCloseModal'), form.reset()" width="w-[500px]">
            <slot name="modal" :form :sendToServer="sendToServer" :closeModal="() => isModalOpen = false" >
                <div class="space-y-4">
                    <CreateStoredItems
                        :storedItemsRoute="storedItemsRoute"
                        :form="form"
                        @onSave="sendToServer"
                        :stored_items="pallet?.stored_items"
                        @closeModal="isModalOpen = false, emits('onCloseModal'), form.reset()"
                        :title
                        :prefixQuery
                        :storedItemToEdit
                    />
                </div>
            </slot>
        </Modal>
    </div>
</template>