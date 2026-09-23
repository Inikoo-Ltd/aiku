<script setup lang="ts">
import { computed, ref } from "vue"
import { Link } from "@inertiajs/vue3"
import axios from "axios"
import { notify } from "@kyvg/vue3-notification"
import SetOrderingPositionOfProduct from "@/Components/Master/SetOrderingPositionOfProduct.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Image from "@common/Components/Image.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"

interface MasterFamilyOrderItem {
    id: number
    slug: string
    code: string
    name: string
    position: number | null
    sub_department_name: string | null
    created_at: string
    number_products: number
    image_thumbnail?: any
}

const props = defineProps<{
    data: {
        data: MasterFamilyOrderItem[]
        editable: boolean
        number_uncurated: number
        payload_key: string
        route_save_order: routeType
        follows_master?: boolean
        shop_settings_route?: routeType
    }
}>()

const families = ref<MasterFamilyOrderItem[]>(props.data?.data ?? [])

const sortOptions = computed(() => [
    { key: 'created_at', label: ctrans('New arrivals'), type: 'date' as const, defaultDirection: 'desc' as const },
    { key: 'code', label: ctrans('Code'), type: 'string' as const },
    { key: 'name', label: ctrans('Name'), type: 'string' as const },
    { key: 'number_products', label: ctrans('Products'), type: 'number' as const, defaultDirection: 'desc' as const },
])
const saveActive = ref(false)
const loadingOrder = ref(false)

const saveOrder = async () => {
    if (!props.data?.editable) {
        return
    }

    loadingOrder.value = true

    try {
        await axios.patch(
            route(props.data.route_save_order.name, props.data.route_save_order.parameters),
            { [props.data.payload_key]: families.value.map((family) => family.id) }
        )

        saveActive.value = false

        notify({
            title: ctrans("Success!"),
            text: ctrans("The families order has been saved, every shop following the master will use it"),
            type: "success",
        })
    } catch (error: any) {
        notify({
            title: ctrans("Something went wrong"),
            text: error?.response?.data?.message || ctrans("Failed to save the families order"),
            type: "error",
        })
    } finally {
        loadingOrder.value = false
    }
}
</script>

<template>
    <div class="p-4 space-y-4">
        <div class="flex justify-between items-center">
            <div>
                <div class="text-xl font-semibold text-gray-700">
                    {{ ctrans('Families order in website') }}
                </div>
                <div class="text-xs text-gray-500 mt-1 max-w-2xl">
                    {{ ctrans('The order below is what the family blocks on the department, sub department and collection pages show. Families left at the bottom with no position fall back to latest arrivals first.') }}
                </div>

                <div v-if="props.data?.follows_master" class="text-xs text-amber-600 mt-1 max-w-2xl">
                    {{ ctrans('This shop follows the master family order, so this list is read only. Turn off :setting in the shop settings to order it here.', { setting: ctrans('Family Order In Website Follow Master') }) }}
                    <Link
                        v-if="props.data?.shop_settings_route"
                        :href="route(props.data.shop_settings_route.name, props.data.shop_settings_route.parameters)"
                        class="underline">
                        {{ ctrans('Open shop settings') }}
                    </Link>
                </div>
                <div v-if="props.data?.number_uncurated" class="text-xs text-gray-400 mt-1">
                    {{ ctrans(':count of them have no position yet and follow latest arrivals', { count: String(props.data.number_uncurated) }) }}
                </div>
            </div>

            <Button
                v-if="props.data?.editable"
                :label="ctrans('Save')"
                :disabled="!saveActive"
                :loading="loadingOrder"
                type="save"
                @click="saveOrder"
            />
        </div>

        <div class="bg-white border rounded-lg p-4">
            <SetOrderingPositionOfProduct
                :data="families"
                :sort-options="sortOptions"
                :disabled="!props.data?.editable"
                @update:data="(event: MasterFamilyOrderItem[]) => { families = event; saveActive = true }"
            >
                <template #list-content="{ item }">
                    <Image
                        :src="item.image_thumbnail?.main?.thumbnail || item?.image_thumbnail"
                        class="w-10 h-10 object-cover rounded"
                    />

                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">
                            {{ item.code }}
                        </div>
                        <div class="text-xs text-gray-400 truncate">
                            {{ item.name }}
                            <span v-if="item.sub_department_name" class="text-gray-300">
                                · {{ item.sub_department_name }}
                            </span>
                        </div>
                    </div>

                    <div class="hidden sm:block w-24 text-right text-xs text-gray-500">
                        {{ item.number_products }} {{ ctrans('products') }}
                    </div>

                    <div class="hidden md:block w-28 text-right text-xs text-gray-400">
                        {{ useFormatTime(item.created_at) }}
                    </div>
                </template>

                <template #image-card="{ item }">
                    <Image
                        :src="item.image_thumbnail?.main?.thumbnail || item?.image_thumbnail"
                        class="w-full h-24 object-cover rounded mb-2"
                    />
                </template>

                <template #empty>
                    <div class="flex flex-col items-center justify-center text-center py-12 px-6 border border-dashed rounded-lg bg-gray-50">
                        <div class="text-sm font-semibold text-gray-700">
                            {{ ctrans('No families found') }}
                        </div>

                        <div class="text-xs text-gray-500 mt-1 max-w-xs">
                            {{ ctrans('This master department has no active families yet.') }}
                        </div>
                    </div>
                </template>
            </SetOrderingPositionOfProduct>
        </div>
    </div>
</template>
