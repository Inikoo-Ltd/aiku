<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Dialog from "primevue/dialog"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faArchive, faBoxOpen, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    archive: {
        is_archived: boolean
        supplier_name: string
        archived_at: string | null
        can_archive: boolean
        open_purchase_orders?: {
            reference: string
            state: string
            organisation: string
            is_draft: boolean
            route: routeType
        }[]
        route: routeType
    }
}>()

const openPurchaseOrders = computed(() => props.archive.is_archived ? [] : props.archive.open_purchase_orders ?? [])
const blockingPurchaseOrders = computed(() => openPurchaseOrders.value.filter((purchaseOrder) => !purchaseOrder.is_draft))
const draftPurchaseOrders = computed(() => openPurchaseOrders.value.filter((purchaseOrder) => purchaseOrder.is_draft))

const isDialogOpen = ref(false)
const isSaving = ref(false)
const error = ref<string | null>(null)

const openDialog = () => {
    error.value = null
    isDialogOpen.value = true
}

const submit = () => {
    router.patch(route(props.archive.route.name, props.archive.route.parameters), {}, {
        preserveScroll: true,
        onStart: () => {
            isSaving.value = true
            error.value = null
        },
        onSuccess: () => {
            isDialogOpen.value = false
        },
        onError: (errors) => {
            error.value = Object.values(errors).join(" ") || ctrans("Something went wrong")
        },
        onFinish: () => {
            isSaving.value = false
        },
    })
}
</script>

<template>
    <Button v-if="archive.can_archive" type="tertiary" :label="archive.is_archived ? ctrans('Unarchive') : ctrans('Archive')" :tooltip="archive.is_archived ? ctrans('Make this supplier active again in the organisations it was archived from') : ctrans('Stop using this supplier in every organisation. Its products stay active')" @click="openDialog">
        <template #icon>
            <FontAwesomeIcon :icon="archive.is_archived ? faBoxOpen : faArchive" :class="archive.is_archived ? 'text-green-600' : 'text-amber-500'" fixed-width aria-hidden="true" />
        </template>
    </Button>

    <Dialog v-model:visible="isDialogOpen" modal :header="archive.is_archived ? ctrans('Unarchive supplier') : ctrans('Archive supplier?')" :style="{ width: '28rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
        <div class="space-y-3 text-sm text-gray-700">
            <p v-if="archive.is_archived">{{ ctrans(':name will be active again in the organisations it was archived from.', { name: archive.supplier_name }) }}</p>
            <div v-else-if="blockingPurchaseOrders.length" class="space-y-2">
                <p class="text-red-600">{{ ctrans(':name cannot be archived while it still has submitted or agreed purchase orders. Finish or cancel them first:', { name: archive.supplier_name }) }}</p>
                <ul class="space-y-1">
                    <li v-for="purchaseOrder in blockingPurchaseOrders" :key="purchaseOrder.reference" class="flex items-center gap-2">
                        <a :href="route(purchaseOrder.route.name, purchaseOrder.route.parameters)" target="_blank" rel="noopener" class="primaryLink font-medium">{{ purchaseOrder.reference }}</a>
                        <span class="text-xs text-gray-500">{{ purchaseOrder.organisation }} · {{ purchaseOrder.state }}</span>
                    </li>
                </ul>
            </div>
            <template v-else>
                <p>{{ ctrans(':name will be archived in every organisation. Its history is kept.', { name: archive.supplier_name }) }}</p>
                <p class="text-gray-500">{{ ctrans('Its products are not changed and stay active.') }}</p>
                <div v-if="draftPurchaseOrders.length" class="space-y-1 rounded border border-amber-200 bg-amber-50 p-2">
                    <p class="text-amber-800">{{ ctrans('These draft purchase orders will be cancelled because the supplier is archived:') }}</p>
                    <ul class="space-y-1">
                        <li v-for="purchaseOrder in draftPurchaseOrders" :key="purchaseOrder.reference" class="flex items-center gap-2">
                            <a :href="route(purchaseOrder.route.name, purchaseOrder.route.parameters)" target="_blank" rel="noopener" class="font-medium underline">{{ purchaseOrder.reference }}</a>
                            <span class="text-xs text-gray-500">{{ purchaseOrder.organisation }}</span>
                        </li>
                    </ul>
                </div>
            </template>
            <p v-if="error && !blockingPurchaseOrders.length" class="text-xs text-red-600">{{ error }}</p>
        </div>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="isDialogOpen = false">
                    <template #icon>
                        <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-if="archive.is_archived" type="primary" full :label="ctrans('Unarchive')" :loading="isSaving" @click="submit">
                    <template #icon>
                        <FontAwesomeIcon :icon="faBoxOpen" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button v-else type="red" :icon="faArchive" full :disabled="blockingPurchaseOrders.length > 0" :label="ctrans('Yes, archive')" :loading="isSaving" @click="submit" />
            </div>
        </template>
    </Dialog>
</template>
