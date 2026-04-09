<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 08 Apr 2026 00:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3'
import { notify } from "@kyvg/vue3-notification"
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from "@/Composables/capitalize"
import { PageHeadingTypes } from '@/types/PageHeading'
import TableWaitingDeliveryNoteItems from '@/Components/Tables/Grp/Org/Dispatching/TableWaitingDeliveryNoteItems.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { ref } from "vue"
import { routeType } from '@/types/route'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
    pageHead: PageHeadingTypes
    title: string
    data: {}
    picking_session_route: routeType
}>()

const selectedDeliveryNoteIds = ref<number[]>([])
const loading = ref(false)

function createPickingSession() {
    if (selectedDeliveryNoteIds.value.length === 0) return

    if (!props.picking_session_route) {
        notify({
            title: trans('Something went wrong'),
            text: trans('Please try again or contact support.'),
            type: 'error',
        })
        return
    }

    loading.value = true

    router.post(
        route(props.picking_session_route.name, props.picking_session_route.parameters),
        { delivery_notes: selectedDeliveryNoteIds.value },
        {
            onFinish: () => {
                loading.value = false
            },
            onError: (errors) => {
                loading.value = false
                if (errors.message) {
                    notify({
                        title: 'Validation Error',
                        text: errors.message,
                        type: 'error',
                    })
                }
            },
        }
    )
}
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead">
        <template #other>
            <Button
                type="create"
                :label="trans('Picking session') + (selectedDeliveryNoteIds.length > 0 ? ` (${selectedDeliveryNoteIds.length})` : '')"
                :loading="loading"
                @click="createPickingSession"
                :disabled="selectedDeliveryNoteIds.length < 1"
                v-tooltip="selectedDeliveryNoteIds.length > 0 ? '' : ctrans('Select items to add Picking Session')"
            />
        </template>
    </PageHeading>
    <TableWaitingDeliveryNoteItems :data="data" v-model:selectedDeliveryNoteIds="selectedDeliveryNoteIds" />
</template>
