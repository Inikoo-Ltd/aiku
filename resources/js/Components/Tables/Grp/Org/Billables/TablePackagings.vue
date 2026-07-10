<!--
  -  Author: Andi Ferdiawan
  -  Created: Thu, 09 Jul 2026 11:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Icon from "@/Components/Icon.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt } from "@far"
import { faFileAlt, faPrint } from "@fal"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"

library.add(faPencil, faTrashAlt, faFileAlt, faPrint)

defineProps<{
    data: {
        data: {}
        links: Links
        meta: Meta
    },
    tab?: string
}>()

const locale = inject('locale', aikuLocaleStructure)

const togglingStateId = ref<number | null>(null)

const toggleState = (packaging: { id: number, state: string }, isActive: boolean) => {
    router.patch(
        route('grp.models.billables.packagings.update', [packaging.id]),
        { state: isActive ? 'active' : 'in_process' },
        {
            preserveScroll: true,
            onStart: () => togglingStateId.value = packaging.id,
            onFinish: () => togglingStateId.value = null,
        }
    )
}

const packagingEditRoute = (packaging: { slug: string }) => {
    const params = route().params as Record<string, string>

    return route('grp.org.shops.show.billables.packagings.edit', [
        params['organisation'],
        params['shop'],
        packaging.slug,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: packaging }">
            <Icon :data="packaging['state_icon']" />
        </template>
        <template #cell(code)="{ item: packaging }">
            <Link :href="packagingEditRoute(packaging)" class="primaryLink font-medium">
                {{ packaging["code"] }}
            </Link>
        </template>
        <template #cell(dimensions)="{ item: packaging }">
            <span v-if="packaging.dimensions" class="whitespace-nowrap">{{ packaging.dimensions }}</span>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(leaflets)="{ item: packaging }">
            <div v-if="packaging.leaflets?.length" class="space-y-1">
                <div
                    v-for="leaflet in packaging.leaflets"
                    :key="leaflet.name"
                    class="flex items-center gap-2 whitespace-nowrap"
                >
                    <FontAwesomeIcon :icon="faFileAlt" fixed-width class="text-gray-500" aria-hidden="true" />
                    <span class="font-medium">{{ leaflet.name }}</span>
                    <!-- <FontAwesomeIcon
                        :icon="faPrint"
                        fixed-width
                        class="text-orange-500"
                        v-tooltip="trans('Printable')"
                        aria-hidden="true"
                    /> -->
                </div>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(price)="{ item: packaging }">
            {{ locale.currencyFormat(packaging.currency_code, packaging.price) }}
        </template>
        <template #cell(actions)="{ item: packaging }">
            <div class="flex items-center gap-3 justify-end">
                <ToggleSwitch
                    :modelValue="packaging.state === 'active'"
                    :disabled="togglingStateId === packaging.id || packaging.state === 'discontinued'"
                    v-tooltip="packaging.state === 'active' ? trans('Deactivate') : trans('Activate')"
                    @update:modelValue="(value: boolean) => toggleState(packaging, value)"
                />
                <Link
                    :href="packagingEditRoute(packaging)"
                    class="text-gray-400 hover:text-gray-600"
                    v-tooltip="trans('Edit')"
                >
                    <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                </Link>

                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.models.billables.packagings.delete',
                        parameters: [packaging.id],
                    }"
                    :title="trans('Delete packaging :code?', { code: packaging.code })"
                    @success="router.reload()"
                >
                    <template #default="{ changeModel }">
                        <button
                            class="text-red-400 hover:text-red-600"
                            v-tooltip="trans('Delete')"
                            @click="changeModel"
                        >
                            <FontAwesomeIcon :icon="faTrashAlt" fixed-width aria-hidden="true" />
                        </button>
                    </template>
                </ModalConfirmationDelete>
            </div>
        </template>
    </Table>
</template>
