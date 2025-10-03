<script setup lang='ts'>
import { onMounted, ref } from 'vue'
import JsBarcode from 'jsbarcode'
import { Link, useForm } from '@inertiajs/vue3'
import PureTextarea from '@/Components/Pure/PureTextarea.vue'
import { trans } from 'laravel-vue-i18n'
import { routeType } from '@/types/route'
import PureTimeline from '@/Components/Pure/PureTimeline.vue'
import Timeline from '@/Components/Utils/Timeline.vue'
import Tag from '@/Components/Tag.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { faPencil, faPrint } from '@fal'
import { printBarcode } from '@/Composables/printBarcode'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faEmptySet } from '@fas'
import Dialog from 'primevue/dialog';
import PureInput from '@/Components/Pure/PureInput.vue'
import { notify } from '@kyvg/vue3-notification'

const props = defineProps<{
    data: {
        data: {
            id: number
            reference: string
            customer_reference: string
            slug: string
            customer: {
                name: string
                route: routeType
            }
            pallet_delivery_id: {}
            pallet_return_id: {}
            location: {
                id: number
                slug: string
                code: string
                tags: []
            }
            state: string
            status: string
            notes: string
            items: []
            timeline: []
        }
    }
}>()

// Blueprint: data
console.log(props)

const form = useForm({
    customer_reference: props.data.data.customer_reference,
    notes: props.data.data.notes,
})
const typeForm = ref('customer_reference')

const blueprint = {
    note: {
        label: 'Note',
    },
    reference: {
        label: 'Reference',
        value: props.data.data.reference || '-'
    },
    customer: {
        label: 'Customer',
        value: props.data.data.customer || '-'
    },
    location: {
        label: 'Location',
        value: props.data.data.location || '-'
    },
    items: {
        label: 'Items',
        value: props.data.data.items || '-'
    },
}

const visible = ref(false);
const setVisible = (type: string) => {
    visible.value = true
    typeForm.value = type
}

const printBarcodePallet = (id: string, code: string) => {
    printBarcode(id, code)
};

const sendEdit = () => {
    form.patch(route("xxx", { //kirin will handle the action
        preserveScroll: true,
        onSuccess: () => {
            notify({
                title: trans("Success"),
                text: trans("Success to set Pallet"),
                type: "success"
            })
        },
        onError: errors => {
            notify({
                title: trans("Something went wrong"),
                text: trans("Failed to set location"),
                type: "error"
            })
        },
    }))
}

onMounted(() => {
    if (props.data.data.slug) {
        JsBarcode('#palletBarcode', props.data.data.slug, {
            lineColor: "rgb(41 37 36)",
            width: 2,
            height: 70,
            background: "#F9FAFB",
            displayValue: true
        })
    }

    if (props.data.data.customer_reference) {
        JsBarcode('#customerReferenceBarcode', props.data.data.customer_reference, {
            lineColor: "rgb(41 37 36)",
            width: 2,
            height: 70,
            background: "#F9FAFB",
            displayValue: true
        })
    }
})

</script>


<template>
    <!--     <pre>{{ data.data }}</pre>-->
    <div
        class="grid max-w-2xl grid-cols-1 gap-x-8 gap-y-4 lg:gap-y-16 lg:max-w-7xl lg:grid-cols-2 px-4 lg:px-8 pb-10 pt-4">
<!--        <div class="col-span-2 w-full pb-4 border-b border-gray-300 overflow-x-auto whitespace-nowrap">-->
<!--            <Timeline :options="data.data.timeline" :slidesPerView="8" :state="data.data.state" />-->
<!--        </div>-->


        <!-- Section: field data -->
        <dl class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 sm:gap-y-8 lg:gap-x-8">
            <div class="col-span-2" v-if="blueprint.note.value">
                <dt class="font-medium">{{ blueprint.note.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <PureTextarea :modelValue="blueprint.note.value" :rows="5"
                        :placeholder="trans('No note from customer.')" disabled />
                </dd>
            </div>

            <div :class="['border-t border-gray-200', 'pt-4']">
                <dt class="font-medium">{{ blueprint.reference.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">{{ blueprint.reference.value }}</dd>
            </div>

            <!-- <div :class="[blueprint.note.value && 'border-t border-gray-200', 'pt-4']">
                <dt class="font-medium">{{ blueprint.customer.label }}</dt>
            </div> -->

            <!-- Field: Items -->
            <div class="border-t border-gray-200 pt-4" v-if="blueprint.items.value.length > 0">
                <dt class="font-medium">{{ blueprint.items.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <span v-if="blueprint.items.value.length" class="flex gap-1">
                        <Tag v-for="item of blueprint.items.value" :key="item.id" :label="item.reference"
                            :theme="item.id" />
                    </span>
                    <span v-else class="text-gray-400 italic">{{ trans("No items in this pallet.") }}</span>
                </dd>
            </div>
            <!-- <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">{{ blueprint.customer_reference.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">{{ blueprint.customer_reference.value }}</dd>
            </div> -->

            <div class="border-gray-200 pt-4 border-t">
                <dt class="font-medium">{{ blueprint.location.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                  {{ blueprint?.location?.value?.code }}

                </dd>
            </div>

            <div class="border-t border-gray-200 pt-4 border-t">
                <dt class="font-medium">Notes</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <div>{{ props?.data?.data?.notes }}</div>
                </dd>
            </div>

            <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">Info</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">State:
                    <Tag :label="data.data.state"></Tag>
                </dd>
                <dd class="mt-2 text-sm text-gray-500 text-justify">Status :
                    <Tag label="" :class="data.data.status_icon.color && 'text-white'"
                        :style="{ backgroundColor: data.data.status_icon.color }">

                        <template #label>
                            <div class="flex gap-2">
                                <FontAwesomeIcon :icon="data.data.status_icon.icon"></FontAwesomeIcon>
                                <div>{{ data.data.status }}</div>
                            </div>
                        </template>
                    </Tag>
                </dd>
            </div>
        </dl>



        <div class="col-span-2 lg:col-span-1  lg:order-2">
            <div class="flex flex-col items-center gap-6">
                <!-- Pallet Code -->
                <div class="relative w-full border rounded-lg p-4 shadow-sm bg-gray-50 group">
                    <div class="text-sm font-medium text-center mb-2">Barcode</div>
                    <div class="relative">
                        <div v-if="props.data.data.slug" class="relative hover:bg-black/30 rounded-lg p-2">
                            <svg id="palletBarcode" class="mx-auto group-hover:fill-black"></svg>
                        </div>
                        <div v-else
                            class="text-sm italic text-gray-400 flex flex-col justify-center items-center space-y-2">
                            <div>{{ trans("No customer reference barcode") }}</div>
                            <div>
                                <FontAwesomeIcon :icon="faEmptySet" class="text-3xl" />
                            </div>
                        </div>
                        <!-- Hover Buttons -->
                        <div
                            class="absolute inset-0 flex items-center gap-3 justify-center opacity-0 group-hover:opacity-100 group-hover:visible transition duration-300">
                            <Button v-if="props.data.data.slug" :icon="faPrint" size="xs" type="white"
                                @click="() => printBarcodePallet('palletBarcode', props.data.data.slug)" />
                        </div>
                    </div>
                </div>

                <!-- Customer Reference -->
                <div class="relative w-full border  rounded-lg p-4 shadow-sm bg-gray-50 group">
                    <div class="text-sm font-medium text-center mb-2">Customer Reference</div>
                    <div class="relative">
                        <div v-if="props.data.data.customer_reference"
                            class="relative hover:bg-black/30 rounded-lg p-2">
                            <svg id="customerReferenceBarcode" class="mx-auto group-hover:fill-black"></svg>
                        </div>
                        <div v-else
                            class="text-sm italic text-gray-400 flex flex-col justify-center items-center space-y-2">
                            <div>{{ trans("No customer reference barcode available") }}</div>
                            <div>
                                <FontAwesomeIcon :icon="faEmptySet" class="text-3xl" />
                            </div>
                        </div>

                        <!-- Hover Buttons -->
                        <div
                            class="absolute inset-0 flex items-center gap-3 justify-center opacity-0 group-hover:opacity-100 group-hover:visible transition duration-300">
                           <!--  <Button :icon="faPencil" size="xs" @click="() => setVisible('customer_reference')" /> -->
                            <Button v-if="props.data.data.customer_reference" :icon="faPrint" size="xs" type="white"
                                @click="() => printBarcodePallet('customerReferenceBarcode', props.data.data.customer_reference)" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <Dialog v-model:visible="visible" modal header="Edit Profile" :style="{ width: '25rem' }" :showHeader="false">
        <div class="mt-4">
            <div v-if="typeForm == 'customer_reference'" class="items-center gap-4 mb-4">
                <span class="font-semibold w-24 mb-2">Customer Reference</span>
                <PureInput v-model="form.customer_reference"></PureInput>
            </div>
            <div v-if="typeForm == 'notes'" class="items-center gap-4 mb-4">
                <span class="font-semibold w-24 mb-2">Notes</span>
                <PureTextarea :rows="4" v-model="form.notes"></PureTextarea>
            </div>
            <div class="flex justify-end gap-2">
                <Button type="white" label="Cancel" @click="visible = false"></Button>
                <Button type="save" label="Save" @click="sendEdit"></Button>
            </div>
        </div>

    </Dialog>
</template>

<style lang="scss" scoped>
.fill-black {
    fill: black;
}
</style>
