<!--
  - Author: Artha <artha@aw-advantage.com>
  - Created: Wed, 15 Oct 2025 16:52:36 Central Indonesia Time, Sanur, Bali, Indonesia
  - Copyright (c) 2025, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import Table from "@/Components/Table/Table.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { trans } from "laravel-vue-i18n"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes, faSpinnerThird } from "@fal"
import { useFormatTime } from "@/Composables/useFormatTime"
import { useLocaleStore } from "@/Stores/locale"
import PlatformLogResponse from "@/Components/Dropshipping/PlatformLogResponse.vue"

defineProps<{
    data: {}
    tab?: string
}>()

library.add(faCheck, faTimes, faSpinnerThird)

const locale = useLocaleStore()

const statusLabels: Record<string, string> = {
    ok: trans("Done"),
    processing: trans("In progress"),
    fail: trans("Failed"),
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(item_code)="{ item: log }">
            <Link :href="route('retina.dropshipping.customer_sales_channels.portfolios.show',         [
        route().params['customerSalesChannel'], log.portfolio_id])" class="primaryLink" v-if="log.portfolio_id">
                {{ log.item_code }}
            </Link>
            <span v-else>{{ log.item_code }}</span>
        </template>

        <template #cell(created_at)="{ item: log }">
            <div class="text-gray-500">{{ useFormatTime(log.created_at, {
                localeCode: locale.language.code,
                formatTime: "hm"
                }) }}
            </div>
        </template>

        <template #cell(status)="{ item: log }">
            <div class="whitespace-nowrap">
                <FontAwesomeIcon v-if="log.status === 'ok'" icon="fal fa-check" class="text-green-500"
                    fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else-if="log.status === 'fail'" icon="fal fa-times" class="text-red-500"
                    fixed-width aria-hidden="true" />
                <FontAwesomeIcon v-else icon="fal fa-spinner-third" class="text-gray-400"
                    fixed-width aria-hidden="true" />
                <span class="ml-2">{{ statusLabels[log.status] ?? log.status }}</span>
            </div>
        </template>

        <template #cell(response)="{ item: log }">
            <PlatformLogResponse
                :message="log.response"
                :hint="log.response_hint"
                :code="log.response_code"
                :detail="log.response_detail"
                :status="log.status"
                :platformName="log.platform_name"
                :itemCode="log.item_code" />
        </template>
    </Table>
</template>
