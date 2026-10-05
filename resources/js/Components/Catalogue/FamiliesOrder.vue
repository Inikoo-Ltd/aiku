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
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faLayerGroup, faLock, faInfoCircle } from "@fal"

interface MasterFamilyOrderItem {
    id: number
    slug: string
    code: string
    name: string
    position: number | null
    sub_department_name: string | null
    department_name?: string | null
    collection_names?: string | null
    created_at: string
    number_products: number
    image_thumbnail?: any
}

const props = defineProps<{
    data: {
        data: MasterFamilyOrderItem[] | { data: MasterFamilyOrderItem[] }
        collection_families?: MasterFamilyOrderItem[] | { data: MasterFamilyOrderItem[] }
        editable: boolean
        number_uncurated: number
        payload_key: string
        route_save_order: routeType
        follows_master?: boolean
        shop_settings_route?: routeType
    }
}>()

const rowsOf = (resource?: MasterFamilyOrderItem[] | { data: MasterFamilyOrderItem[] }): MasterFamilyOrderItem[] =>
    Array.isArray(resource) ? resource : (resource?.data ?? [])

const families = ref<MasterFamilyOrderItem[]>(rowsOf(props.data?.data))
const collectionFamilies = computed<MasterFamilyOrderItem[]>(() => rowsOf(props.data?.collection_families))

const scrollToCollectionFamilies = () => {
    document.getElementById('families-order-from-collections')?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

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
    <div class="p-3 space-y-2">
        <div class="flex items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="text-base font-semibold text-gray-700">
                        {{ ctrans('Families order in website') }}
                    </h3>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-600 tabular-nums">{{ families.length }}</span>
                    <FontAwesomeIcon :icon="faInfoCircle" class="text-xs text-gray-400 hover:text-gray-600" fixed-width
                        v-tooltip="ctrans('The order below is what the family blocks on the department, sub department and collection pages show. Families left at the bottom with no position fall back to latest arrivals first.')" />

                    <span v-if="props.data?.number_uncurated" class="text-xs text-gray-400">
                        · {{ ctrans(':count without position', { count: String(props.data.number_uncurated) }) }}
                    </span>

                    <button v-if="collectionFamilies.length" type="button"
                        class="inline-flex items-center gap-1 rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700 hover:bg-indigo-100"
                        @click="scrollToCollectionFamilies">
                        <FontAwesomeIcon :icon="faLayerGroup" class="text-[10px]" fixed-width aria-hidden="true" />
                        {{ ctrans(':count from collections', { count: String(collectionFamilies.length) }) }}
                    </button>
                </div>

                <div v-if="props.data?.follows_master" class="mt-1 text-xs text-amber-600">
                    {{ ctrans('This shop follows the master family order, so this list is read only. Turn off :setting in the shop settings to order it here.', { setting: ctrans('Family Order In Website Follow Master') }) }}
                    <Link
                        v-if="props.data?.shop_settings_route"
                        :href="route(props.data.shop_settings_route.name, props.data.shop_settings_route.parameters)"
                        class="underline">
                        {{ ctrans('Open shop settings') }}
                    </Link>
                </div>
            </div>

            <Button
                v-if="props.data?.editable"
                :label="ctrans('Save')"
                :disabled="!saveActive"
                :loading="loadingOrder"
                type="save"
                size="sm"
                @click="saveOrder"
            />
        </div>

        <div class="bg-white border rounded-lg">
            <SetOrderingPositionOfProduct
                :data="families"
                :sort-options="sortOptions"
                :disabled="!props.data?.editable"
                list-max-height="60vh"
                dense
                @update:data="(event: MasterFamilyOrderItem[]) => { families = event; saveActive = true }"
            >
                <template #list-content="{ item }">
                    <Image :src="item.image_thumbnail" class="w-7 h-7 shrink-0 object-cover rounded" />

                    <div class="flex-1 min-w-0 truncate text-sm">
                        <span class="font-medium text-gray-800">{{ item.code }}</span>
                        <span class="ml-2 text-xs text-gray-400">{{ item.name }}</span>
                        <span v-if="item.sub_department_name" class="ml-1 text-xs text-gray-300">· {{ item.sub_department_name }}</span>
                    </div>

                    <div class="hidden sm:block w-20 shrink-0 text-right text-xs text-gray-500 tabular-nums">
                        {{ item.number_products }} {{ ctrans('products') }}
                    </div>

                    <div class="hidden md:block w-24 shrink-0 text-right text-xs text-gray-400">
                        {{ useFormatTime(item.created_at) }}
                    </div>
                </template>

                <template #image-card="{ item }">
                    <Image :src="item.image_thumbnail" class="w-full h-20 object-cover rounded mb-1" />
                </template>

                <template #after-list="{ viewMode, itemsCount }">
                    <template v-if="collectionFamilies.length">
                        <div id="families-order-from-collections" class="mt-3 mb-1 flex items-center gap-2 scroll-mt-2">
                            <span class="flex items-center gap-1 text-[11px] font-semibold uppercase tracking-wide text-indigo-600">
                                <FontAwesomeIcon :icon="faLayerGroup" fixed-width aria-hidden="true" />
                                {{ ctrans('From collections') }}
                            </span>
                            <span class="h-px flex-1 border-t border-dashed border-indigo-200"></span>
                            <span class="flex items-center gap-1 text-[11px] text-gray-400">
                                <FontAwesomeIcon :icon="faLock" class="text-[10px]" fixed-width aria-hidden="true" />
                                {{ ctrans('Ordered in their own department') }}
                            </span>
                        </div>

                        <div v-if="viewMode === 'list'" class="space-y-1">
                            <div v-for="(family, collectionIndex) in collectionFamilies" :key="family.id"
                                class="flex items-center gap-2 px-2 py-1 rounded border border-dashed border-indigo-200 bg-indigo-50/40">
                                <FontAwesomeIcon :icon="faLock" class="text-[10px] text-indigo-300" fixed-width aria-hidden="true" />
                                <div class="w-8 text-xs text-indigo-400 tabular-nums">{{ itemsCount + collectionIndex + 1 }}</div>

                                <Image :src="family.image_thumbnail" class="w-7 h-7 shrink-0 object-cover rounded" />

                                <div class="flex-1 min-w-0 truncate text-sm">
                                    <span class="font-medium text-gray-700">{{ family.code }}</span>
                                    <span class="ml-2 text-xs text-gray-400">{{ family.name }}</span>
                                    <span v-if="family.department_name" class="ml-1 text-xs text-gray-400">· {{ ctrans('from :department', { department: family.department_name }) }}</span>
                                </div>

                                <span v-if="family.collection_names"
                                    class="hidden sm:inline-flex max-w-[14rem] shrink-0 items-center gap-1 truncate rounded-full bg-indigo-100 px-2 py-0.5 text-[11px] text-indigo-700">
                                    <FontAwesomeIcon :icon="faLayerGroup" class="text-[9px]" fixed-width aria-hidden="true" />
                                    <span class="truncate">{{ family.collection_names }}</span>
                                </span>

                                <div class="hidden sm:block w-20 shrink-0 text-right text-xs text-gray-500 tabular-nums">
                                    {{ family.number_products }} {{ ctrans('products') }}
                                </div>
                            </div>
                        </div>

                        <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
                            <div v-for="(family, collectionIndex) in collectionFamilies" :key="family.id"
                                class="rounded border border-dashed border-indigo-200 bg-indigo-50/40 p-2">
                                <div class="mb-1 flex items-center gap-2 text-xs text-indigo-400">
                                    <FontAwesomeIcon :icon="faLock" class="text-[10px]" fixed-width aria-hidden="true" />
                                    <span class="tabular-nums">{{ itemsCount + collectionIndex + 1 }}</span>
                                </div>
                                <Image :src="family.image_thumbnail" class="w-full h-20 object-cover rounded mb-1" />
                                <div class="text-sm font-medium line-clamp-2">{{ family.name }}</div>
                                <div class="text-xs text-gray-400">{{ family.code }}</div>
                                <div v-if="family.collection_names" class="mt-1 truncate text-[11px] text-indigo-600">
                                    {{ family.collection_names }}
                                </div>
                            </div>
                        </div>
                    </template>
                </template>

                <template #empty>
                    <div class="flex flex-col items-center justify-center text-center py-10 px-6 border border-dashed rounded-lg bg-gray-50">
                        <div class="text-sm font-semibold text-gray-700">
                            {{ ctrans('No families found') }}
                        </div>
                        <div class="text-xs text-gray-500 mt-1 max-w-xs">
                            {{ ctrans('This department has no active families yet.') }}
                        </div>
                    </div>
                </template>
            </SetOrderingPositionOfProduct>
        </div>
    </div>
</template>
