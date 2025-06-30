<script setup lang='ts'>
import { onMounted } from 'vue'
import JsBarcode from 'jsbarcode'
import { Link } from '@inertiajs/vue3'
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
import ButtonWithLink from '@/Components/Elements/Buttons/ButtonWithLink.vue'
import Icon from '@/Components/Icon.vue'
import '@/Composables/Icon/PalletStateEnum'


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
            pallet_delivery: {
                reference: string
                route: routeType
            }
            pallet_return: {}
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
    list_stored_items:{
        reference: string
        name: string
        quantity: number
    }[]
}>()


console.log('www', props.data.data)

// Blueprint: data
const blueprint = {
    note: {
        label: 'Note',
        value: props.data.data.notes
    },
    reference: {
        label: 'Reference',
        value: props.data.data.reference || '-'
    },
    customer: {
        label: 'Customer',
        value: props.data.data.customer || '-'
    },
    customer_reference: {
        label: "Customer's reference",
        value: props.data.data.customer_reference || '-'
    },
    location: {
        label: 'Location',
        value: props.data.data.location || '-'
    },
    // state: {
    //     label: 'State',
    //     value: props.data.data.state || '-'
    // },
    // status: {
    //     label: 'Status',
    //     value: props.data.data.status || '-'
    // },
    items: {
        label: 'Items',
        value: props.data.data.items || '-'
    },
}


onMounted(() => {
    if (props.data.data.slug) {
        JsBarcode('#palletBarcode', props.data.data.slug, {
            lineColor: "rgb(41 37 36)",
            width: 2,
            height: 70,
            background:"#F9FAFB",
            displayValue: true
        })
    }

    // if (props.data.data.customer_reference) {
    //     JsBarcode('#customerReferenceBarcode', props.data.data.customer_reference, {
    //         lineColor: "rgb(41 37 36)",
    //         width: 2,
    //         height: 70,
    //         background:"#F9FAFB",
    //         displayValue: true
    //     })
    // }
})

const printBarcodePallet = (id: string, code: string) => {
    printBarcode(id, code)
};


const generateRouteEditBarcode = () => {
    switch (route().current()) {
        case 'grp.org.fulfilments.show.operations.pallets.current.show':
            return {
                name: 'grp.org.fulfilments.show.operations.pallets.current.edit',
                parameters: { ...route().params }
            }
        case 'grp.org.fulfilments.show.crm.customers.show.pallets.show':
            return {
                name: 'grp.org.fulfilments.show.crm.customers.show.pallets.edit',
                parameters: { ...route().params }
            }
        case 'grp.org.warehouses.show.inventory.pallets.current.show':  // Warehouse
            return {
                name: 'grp.org.warehouses.show.inventory.pallets.current.edit',
                parameters: { ...route().params }
            }
        default:
            return null
    }
} 
</script>


<template>
    <div
        class="grid max-w-2xl grid-cols-1 gap-x-8 gap-y-4 lg:gap-y-16 lg:max-w-7xl lg:grid-cols-2 px-4 lg:px-8 pb-10 pt-4">
        <div class="col-span-2 w-full pb-4 border-b border-gray-300 overflow-x-auto whitespace-nowrap">
            <Timeline :options="data.data.timeline" :slidesPerView="8" :state="data.data.state" />
        </div>

<!-- <pre>{{ list_stored_items }}</pre> -->
        <!-- Section: field data -->
        <dl class="grid grid-cols-1 gap-x-6 gap-y-10 sm:grid-cols-2 sm:gap-y-8 lg:gap-x-8">
            <div :class="[blueprint.note.value && 'border-t border-gray-200', 'pt-4']">
                <dt class="font-medium">
                    {{ trans("Pallet Reference") }}
                </dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">{{ blueprint.reference.value }}</dd>
            </div>

            <div :class="[blueprint.note.value && 'border-t border-gray-200', 'pt-4']">
                <dt class="font-medium">{{ trans("Customer") }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <Link :href="route(blueprint.customer.value.route.name, blueprint.customer.value.route.parameters)"
                        class="primaryLink">
                    <!-- <Link :href="'#'" class="primaryLink"> -->
                    {{ blueprint.customer.value.name }}
                    </Link>
                </dd>
            </div>

            <!-- Field: Items -->
            <div class="border-t border-gray-200 pt-4" v-if="blueprint.items.value.length > 0">
                <dt class="font-medium">
                    {{ blueprint.items.label }}
                    <FontAwesomeIcon icon="fal fa-narwhal" class="ml-1 text-gray-400" fixed-width aria-hidden="true" />
                </dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <div v-if="blueprint.items.value.length" class="flex flex-wrap gap-1">
                        <Tag
                            v-for="item of list_stored_items"
                            v-tooltip="item.name"
                            :key="item.reference"
                            :label="item.reference"
                            stringToColor
                        >
                            <template #label>
                                <!-- <pre>{{ item }}</pre> -->
                                <span class="whitespace-nowrap">{{ item.reference }} ({{ item.quantity }})</span>
                            </template>
                        </Tag>
                    </div>
                    <span v-else class="text-gray-400 italic">{{ trans("No items in this pallet.") }}</span>
                </dd>
            </div>

            <!-- Customer Reference -->
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">{{ blueprint.customer_reference.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">{{ blueprint.customer_reference.value }}</dd>
            </div>

            <!-- Location -->
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">
                    {{ blueprint.location.label }}
                    <FontAwesomeIcon icon="fal fa-inventory" class="ml-1 text-gray-400" fixed-width aria-hidden="true" />
                </dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <Link v-if="blueprint.location.value.route?.name"
                        :href="route(blueprint.location.value.route.name, blueprint.location.value.route.parameters)"
                        class="primaryLink">
                        {{ blueprint.location.value.resource.code }}
                    </Link>
                    <span v-else>{{ blueprint?.location?.value?.resource?.code ?? '-' }}</span>
                </dd>
            </div>

            <!-- Info -->
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">{{ trans("Info") }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">{{ trans("State") }}:
                    <Tag :class="'capitalize'" :label="data.data.state" ></Tag>
                </dd>
                <dd class="mt-2 text-sm text-gray-500 text-justify">{{ trans("Status") }} :
                    <Tag :label="data.data.status"
                        :xxstyle="{ backgroundColor: data.data.status_icon?.color }"
                        stringToColor
                    >
                    <template #label>
                        <div class="flex gap-2 capitalize">
                            <!-- <FontAwesomeIcon :icon="data.data.status_icon.icon"></FontAwesomeIcon> -->
                            <Icon :data="data?.data?.status_icon" />
                            <div>{{ data.data.status }}</div>
                        </div>
                    </template>
                    </Tag>
                </dd>
            </div>

            

            <!-- Pallet Delivery -->
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">
                    {{ trans("Pallet Delivery") }}
                    <FontAwesomeIcon icon="fal fa-truck-couch" class="ml-1 text-gray-400" fixed-width aria-hidden="true" />
                </dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <Link v-if="data.data.pallet_delivery.reference"
                        :href="data?.data?.pallet_delivery?.route?.name ? route(data?.data?.pallet_delivery?.route?.name, data?.data?.pallet_delivery?.route?.parameters) : ''"
                        class="secondaryLink">
                        {{ data?.data?.pallet_delivery?.reference }}
                    </Link>
                    <span v-else class="text-gray-400">
                        -
                    </span>
                </dd>
            </div>

            <!-- Pallet Return -->
            <div class="border-t border-gray-200 pt-4">
                <dt class="font-medium">
                    {{ trans("Pallet Return") }}
                    <FontAwesomeIcon icon="fal fa-sign-out" class="ml-1 text-gray-400" fixed-width aria-hidden="true" />
                </dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <Link v-if="data.data.pallet_return.reference"
                        :href="data?.data?.pallet_return?.route?.name ? route(data?.data?.pallet_return?.route?.name, data?.data?.pallet_return?.route?.parameters) : ''"
                        class="secondaryLink">
                        {{ data?.data?.pallet_return?.reference }}
                    </Link>
                    <span v-else class="text-gray-400">
                        -
                    </span>
                </dd>
            </div>

            <!-- Note -->
            <div class="col-span-2 ">
                <dt class="font-medium">{{ blueprint.note.label }}</dt>
                <dd class="mt-2 text-sm text-gray-500 text-justify">
                    <PureTextarea :modelValue="blueprint.note.value" :rows="5"
                        :placeholder="trans('No note for this pallet')" disabled />
                </dd>
            </div>

            
        </dl>



        <div class="col-span-2 lg:col-span-1  lg:order-2">
        <div class="flex flex-col items-center gap-6">
          <!-- Pallet Code -->
          <div class="relative w-full border rounded-lg p-4 shadow-sm bg-gray-50 group">
            <div class="text-sm font-medium text-center mb-2">{{ trans("Barcode") }}</div>
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
              <div
                class="bg-white/50 absolute inset-0 flex items-center gap-3 justify-center opacity-0 group-hover:opacity-100 group-hover:visible transition duration-300">
                <ButtonWithLink
                    v-if="generateRouteEditBarcode()"
                    :routeTarget="generateRouteEditBarcode()"
                    icon="fal fa-pencil"
                    xxsize="xs"
                />
                <Button v-if="props.data.data.slug" :icon="faPrint" xxsize="xs" type="white"
                    class="border border-gray-300"
                  @click="() => printBarcodePallet('palletBarcode', props.data.data.slug)" />
              </div>
            </div>
          </div>
        </div>
      </div>


    </div>
</template>

<style lang="scss" scoped>
.fill-black {
    fill: black;
}
</style>
