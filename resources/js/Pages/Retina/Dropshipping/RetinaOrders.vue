<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeading as PageHeadingTypes } from '@/types/PageHeading'
import RetinaTablePalletOrders from "@/Components/Tables/Retina/RetinaTablePalletOrders.vue";
import Button from '@/Components/Elements/Buttons/Button.vue'
import Modal from '@/Components/Utils/Modal.vue'
import { ref } from 'vue'

import { faArrowRight, faExternalLinkAlt } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import { notify } from '@kyvg/vue3-notification'
import { trans } from 'laravel-vue-i18n'
library.add(faArrowRight, faExternalLinkAlt)

const props = defineProps<{
    title: string,
    pageHead: PageHeadingTypes
    data: {}
    currency: {
        code: string
        symbol: string
        name: string
    }
    customer_sales_channel: {
        
    }
}>()

// Section: Modal Create Order
const isModalCreateOrder = ref(false)
const selectedCustomerClientId = ref(null)
const isLoadingSubmit = ref(false)
const onSubmitCreateOrder = () => {
    // Section: Submit
    router.post(
        route('retina.models.customer-client.order.store', {
            customerClient: selectedCustomerClientId.value
        }),
        {
            data: 'qqq'
        },
        {
            preserveScroll: true,
            preserveState: true,
            onStart: () => { 
                isLoadingSubmit.value = true
            },
            onSuccess: () => {
                isModalCreateOrder.value = false
                notify({
                    title: trans("Success"),
                    text: trans("Successfully submit the data"),
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
            onFinish: () => {
                isLoadingSubmit.value = false
            },
        }
    )
}

</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <div>
                <Button
                @click="() => isModalCreateOrder = true"
                    label="Create Order"
                    iconRight="fal fa-arrow-right"
                />
            </div>
        </template>
    </PageHeading>


    <RetinaTablePalletOrders :data="props.data" :currency  />

    <!-- Modal: Create order -->
    <Modal :isOpen="isModalCreateOrder" @onClose="isModalCreateOrder = false" closeButton :isClosableInBackground="false" width="max-w-lg w-full">
        <div>
            <div class="text-lg font-semibold mb-4 text-center">
                {{ trans("Create Order") }}
            </div>

            <div>
                <div class="mb-4">
                    <div class="text-sm xmb-2">
                        {{ trans("Select Customer Client") }}
                    </div>
                    <PureMultiselectInfiniteScroll
                        v-model="selectedCustomerClientId"
                        :fetchRoute="{
                            name: 'retina.dropshipping.customer_sales_channels.client.index',
                            parameters: {
                                customerSalesChannel: props.customer_sales_channel.slug,
                            }
                        }"
                        required
                        :disabled="isLoadingSubmit"
                    >
                        <template #singlelabel="{ value }">
                            <div class="w-full text-left pl-4">
                                {{ value.name}}
                                <span v-if="value.reference" class="text-sm text-gray-400">
                                    (#{{ value.reference }})
                                </span>
                            </div>
                        </template>

                        <template #afterlist>
                            <div class="m-2 cursor-auto text-gray-400 text-sm">
                                {{ trans("Can't find the client?") }}
                                
                                <Link
                                    :href="route('retina.dropshipping.customer_sales_channels.client.create', {
                                        customerSalesChannel: props.customer_sales_channel.slug
                                    })"
                                    class="hover:underline hover:text-gray-700 cursor-pointer"
                                >
                                    {{ trans("Create new client here") }}
                                </Link>
                            </div>
                        </template>
                    </PureMultiselectInfiniteScroll>
                </div>

                <Button 
                    @click="() => onSubmitCreateOrder()"
                    label="Create Order"
                    full
                    :loading="isLoadingSubmit"
                    :disabled="!selectedCustomerClientId"
                />
            </div>

            <!-- Divider -->
            <div class="relative my-3">
                <div class="absolute inset-0 flex items-center" aria-hidden="true">
                    <div class="w-full border-t border-gray-300" />
                </div>
                <div class="relative flex justify-center">
                    <span class="bg-white px-2 text-sm text-gray-500">Or create new client</span>
                </div>
            </div>

            <Link
                :href="route('retina.dropshipping.customer_sales_channels.client.create', {
                    customerSalesChannel: props.customer_sales_channel.slug
                })"
            >
                <Button
                    xclick="() => onSubmitCreateOrder()"
                    label="Create new client"
                    full
                    type="tertiary"
                    xloading="isLoadingSubmit"
                    xdisabled="!selectedCustomerClientId"
                    xiconRight="fal fa-external-link-alt"
                    icon="far fa-plus"
                />
            </Link>
        </div>
    </Modal>
</template>
