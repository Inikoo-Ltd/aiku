<!--
  - Author Louis Perez
  - Created on 20-10-2026-11h-10m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Popover from "primevue/popover"
import Checkbox from "primevue/checkbox"
import Button from "@/Components/Elements/Buttons/Button.vue"
import ProductDevelopmentRemoteSelect from "@/Components/Goods/ProductDevelopment/ProductDevelopmentRemoteSelect.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown, faLink, faUnlink, faClipboardList, faPlus, faTimes } from "@fal"
library.add(faChevronDown, faLink, faUnlink, faClipboardList, faPlus, faTimes)

const props = defineProps<{
    data: {
        rows: { id: number, reference: string, state: string, date: string | null, linked: "auto" | "manual", sample: "required" | "approved" | "overridden" | null, route: routeType }[]
        link: {
            route: routeType
            search_route: routeType
            create: { route: routeType, options: { value: number, label: string }[], empty: string }
        } | null
    }
}>()

const popover = ref<InstanceType<typeof Popover> | null>(null)
const picked = ref<number | null>(null)
const toLink = ref<{ value: number, label: string }[]>([])
const busy = ref(false)

/** Each purchase order picked in the search joins the list to link; the search clears for the next one */
function addToLink(option: { value: number, label: string }) {
    if (!toLink.value.some((entry) => entry.value === option.value) && !props.data.rows.some((row) => row.id === option.value)) {
        toLink.value.push(option)
    }
    setTimeout(() => picked.value = null)
}

function send(purchaseOrderIds: number[], operation: "link" | "unlink") {
    if (!props.data.link || !purchaseOrderIds.length) {
        return
    }
    router.patch(route(props.data.link.route.name, props.data.link.route.parameters), { purchase_order_ids: purchaseOrderIds, operation }, {
        preserveScroll: true,
        onStart: () => busy.value = true,
        onFinish: () => busy.value = false,
        onSuccess: () => {
            if (operation === "link") {
                toLink.value = []
            }
        },
        onError: (errors) => notify({ title: operation === "link" ? ctrans("Purchase orders not linked") : ctrans("Purchase order not unlinked"), text: Object.values(errors).join(" "), type: "error" }),
    })
}

const orgSupplierIds = ref<number[]>(props.data.link?.create.options.length === 1 ? [props.data.link.create.options[0].value] : [])
const creating = ref(false)

function create() {
    if (!props.data.link) {
        return
    }
    const count = orgSupplierIds.value.length
    router.post(route(props.data.link.create.route.name, props.data.link.create.route.parameters), { org_supplier_ids: orgSupplierIds.value }, {
        preserveScroll: true,
        onStart: () => creating.value = true,
        onFinish: () => creating.value = false,
        onSuccess: () => {
            notify({ title: count === 1 ? ctrans("Purchase order created") : ctrans(":count purchase orders created", { count: String(count) }), type: "success" })
            if ((props.data.link?.create.options.length ?? 0) > 1) {
                orgSupplierIds.value = []
            }
        },
        onError: (errors) => notify({ title: ctrans("Purchase orders not created"), text: Object.values(errors).join(" "), type: "error" }),
    })
}

const sampleLabels: Record<string, string> = {
    required: ctrans("Sample required, not approved yet"),
    approved: ctrans("Sample approved"),
    overridden: ctrans("Ships without an approved sample"),
}

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : ""
</script>

<template>
    <div>
        <button type="button" class="flex items-center gap-1 text-left text-gray-800 hover:text-[--app-accent]" @click="(event) => popover?.toggle(event)">
            {{ data.rows.length === 1 ? ctrans("1 purchase order") : data.rows.length ? ctrans(":count purchase orders", { count: String(data.rows.length) }) : ctrans("Not raised yet") }}
            <FontAwesomeIcon icon="fal fa-chevron-down" class="text-xs text-gray-400" fixed-width aria-hidden="true" />
        </button>
        <Popover ref="popover">
            <div class="w-80 space-y-3 text-sm">
                <ul v-if="data.rows.length" class="-mx-1.5 space-y-0.5">
                    <li v-for="purchaseOrder in data.rows" :key="purchaseOrder.id" class="group flex items-center gap-1 rounded-md transition-colors hover:bg-gray-50">
                        <Link :href="route(purchaseOrder.route.name, purchaseOrder.route.parameters)" class="flex min-w-0 flex-1 items-center gap-2 rounded-md px-1.5 py-2" @click="popover?.hide()">
                            <FontAwesomeIcon icon="fal fa-clipboard-list" class="shrink-0 text-gray-400 transition-colors group-hover:text-[--app-accent]" fixed-width aria-hidden="true" />
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium text-gray-800 transition-colors group-hover:text-[--app-accent-strong]">{{ purchaseOrder.reference }}</span>
                                <span class="block truncate text-xs text-gray-500">
                                    {{ [purchaseOrder.state, formatDate(purchaseOrder.date), purchaseOrder.linked === "manual" ? ctrans("linked by hand") : ctrans("orders this development's SKUs")].filter(Boolean).join(" · ") }}
                                </span>
                                <span v-if="purchaseOrder.sample" class="block text-xs" :class="purchaseOrder.sample === 'approved' ? 'text-green-700' : 'text-amber-700'">
                                    {{ sampleLabels[purchaseOrder.sample] }}
                                </span>
                            </span>
                        </Link>
                        <button v-if="data.link && purchaseOrder.linked === 'manual'" type="button" class="mr-1 shrink-0 rounded p-1.5 text-gray-400 transition-colors hover:bg-red-50 hover:text-red-600" v-tooltip="ctrans('Unlink')" :aria-label="ctrans('Unlink :reference', { reference: purchaseOrder.reference })" :disabled="busy" @click="send([purchaseOrder.id], 'unlink')">
                            <FontAwesomeIcon icon="fal fa-unlink" fixed-width aria-hidden="true" />
                        </button>
                    </li>
                </ul>
                <p v-else class="text-xs text-gray-500">{{ ctrans("Purchase orders that order this development's supplier products show here on their own.") }}</p>

                <div v-if="data.link" class="space-y-2 border-t border-gray-100 pt-3">
                    <span class="text-xs text-gray-500">{{ ctrans("Link another purchase order") }}</span>
                    <ProductDevelopmentRemoteSelect v-model="picked" :searchRoute="data.link.search_route" :placeholder="ctrans('Search by reference')" @picked="addToLink" />
                    <ul v-if="toLink.length" class="space-y-1">
                        <li v-for="(entry, index) in toLink" :key="entry.value" class="flex items-center gap-2 rounded-md bg-gray-50 px-2 py-1 text-xs text-gray-700">
                            <FontAwesomeIcon icon="fal fa-clipboard-list" class="shrink-0 text-gray-400" fixed-width aria-hidden="true" />
                            <span class="min-w-0 flex-1 truncate">{{ entry.label }}</span>
                            <button type="button" class="shrink-0 rounded p-0.5 text-gray-400 hover:text-red-600" :aria-label="ctrans('Remove')" @click="toLink.splice(index, 1)">
                                <FontAwesomeIcon icon="fal fa-times" fixed-width aria-hidden="true" />
                            </button>
                        </li>
                    </ul>
                    <div class="flex justify-end">
                        <Button type="primary" size="xs" icon="fal fa-link" :label="toLink.length > 1 ? ctrans('Link :count', { count: String(toLink.length) }) : ctrans('Link')" :disabled="!toLink.length" :loading="busy" class="disabled:cursor-not-allowed disabled:opacity-50" @click="send(toLink.map((entry) => entry.value), 'link')" />
                    </div>
                </div>

                <div v-if="data.link" class="space-y-2 border-t border-gray-100 pt-3">
                    <span class="text-xs text-gray-500">{{ ctrans("Create a new purchase order") }}</span>
                    <p v-if="!data.link.create.options.length" class="text-xs text-gray-400">{{ data.link.create.empty }}</p>
                    <template v-else>
                        <div v-if="data.link.create.options.length > 1" class="max-h-48 space-y-0.5 overflow-y-auto">
                            <label v-for="option in data.link.create.options" :key="option.value" class="flex cursor-pointer items-center gap-2 rounded-md px-1.5 py-1 text-sm text-gray-800 hover:bg-gray-50">
                                <Checkbox v-model="orgSupplierIds" :value="option.value" :inputId="`po-org-${option.value}`" />
                                <span class="min-w-0 flex-1 truncate">{{ option.label }}</span>
                            </label>
                        </div>
                        <p v-if="data.link.create.options.length > 1" class="text-xs text-gray-500">{{ ctrans("One purchase order per organisation, each linked to this development") }}</p>
                        <p v-else class="text-xs text-gray-700">{{ ctrans("For :organisation", { organisation: data.link.create.options[0].label }) }}</p>
                        <div class="flex justify-end">
                            <Button type="primary" size="xs" icon="fal fa-plus" :label="orgSupplierIds.length > 1 ? ctrans('Create :count purchase orders', { count: String(orgSupplierIds.length) }) : ctrans('Create purchase order')" :loading="creating" @click="create" />
                        </div>
                    </template>
                </div>
            </div>
        </Popover>
    </div>
</template>
