<script setup lang="ts">
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ref } from "vue"
import { useForm, router } from "@inertiajs/vue3"
import Modal from "@/Components/Utils/Modal.vue"
import { notify } from "@kyvg/vue3-notification"
import CreateStoredItems from "./CreateStoredItems.vue"
import Tag from '@/Components/Tag.vue'
import { get } from 'lodash-es'
import { routeType } from "@/types/route"
import { trans } from "laravel-vue-i18n"
import RetinaCreateStoredItems from "./RetinaCreateStoredItems.vue"


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
}>()

const emits = defineEmits<{
    (e: 'renderTable'): void
}>()

const isModalOpen = ref(false)
const form = useForm({ id: null, quantity: 1, oldData: null })

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
    const stored_items = [...props.pallet.stored_items].filter((item) => item.id != data.id)
    const finalData = {}
    for (const d of stored_items) finalData[d.id] = { quantity: d.quantity }
    sendToServer(finalData)
}

const sendToServer = async (data : {}, replaceData?: boolean) => {
    // console.log('-=-=-=-=', props.saveRoute.name, props.saveRoute.parameters)
    router.post(route(props.saveRoute.name, props.saveRoute.parameters), replaceData ? data : { stored_item_ids: data }, {
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
            isModalOpen.value = false
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

        <Modal :isOpen="isModalOpen" @onClose="isModalOpen = false" width="w-[500px]">
            <slot name="modal" :form :sendToServer="sendToServer" :closeModal="() => isModalOpen = false" >
                <div class="space-y-4">
                    <RetinaCreateStoredItems
                        :storedItemsRoute="storedItemsRoute"
                        :form="form"
                        @onSave="sendToServer"
                        :stored_items="pallet.stored_items"
                        @closeModal="isModalOpen = false"
                        :title
                        :prefixQuery
                    />
                </div>
            </slot>
        </Modal>
    </div>
</template>