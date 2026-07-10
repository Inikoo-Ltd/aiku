<!--
  -  Author: Andi Ferdiawan
  -  Created: Fri, 10 Jul 2026 10:00:00 Central Indonesia Time, Bali, Indonesia
  -  Copyright (c) 2026, Inikoo Ltd
  -->

<script setup lang="ts">
import Table from "@/Components/Table/Table.vue"
import type { Links, Meta } from "@/types/Table"
import { aikuLocaleStructure } from "@/Composables/useLocaleStructure"
import { inject, ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import Icon from "@/Components/Icon.vue"
import ModalConfirmationDelete from "@/Components/Utils/ModalConfirmationDelete.vue"
import ToggleSwitch from "primevue/toggleswitch"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt } from "@far"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { trans } from "laravel-vue-i18n"

library.add(faPencil, faTrashAlt)

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

const toggleState = (leaflet: { id: number, state: string }, isActive: boolean) => {
    router.patch(
        route('grp.models.billables.leaflets.update', [leaflet.id]),
        { state: isActive ? 'active' : 'inactive' },
        {
            preserveScroll: true,
            onStart: () => togglingStateId.value = leaflet.id,
            onFinish: () => togglingStateId.value = null,
        }
    )
}

const leafletEditRoute = (leaflet: { id: number }) => {
    const params = route().params as Record<string, string>

    return route('grp.org.shops.show.billables.leaflets.edit', [
        params['organisation'],
        params['shop'],
        leaflet.id,
    ])
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(state)="{ item: leaflet }">
            <Icon :data="leaflet['state_icon']" />
        </template>
        <template #cell(name)="{ item: leaflet }">
            <Link :href="leafletEditRoute(leaflet)" class="primaryLink font-medium">
                {{ leaflet["name"] }}
            </Link>
        </template>
        <template #cell(family_codes)="{ item: leaflet }">
            <div v-if="leaflet.family_codes?.length" class="flex flex-wrap gap-1">
                <span
                    v-for="familyCode in leaflet.family_codes"
                    :key="familyCode"
                    class="rounded bg-gray-100 px-1.5 py-0.5 text-xs font-medium text-gray-700"
                >
                    {{ familyCode }}
                </span>
            </div>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(price)="{ item: leaflet }">
            {{ locale.currencyFormat(leaflet.currency_code, leaflet.price) }}
        </template>
        <template #cell(actions)="{ item: leaflet }">
            <div class="flex items-center gap-3 justify-end">
                <ToggleSwitch
                    :modelValue="leaflet.state === 'active'"
                    :disabled="togglingStateId === leaflet.id"
                    v-tooltip="leaflet.state === 'active' ? trans('Deactivate') : trans('Activate')"
                    @update:modelValue="(value: boolean) => toggleState(leaflet, value)"
                />
                <Link
                    :href="leafletEditRoute(leaflet)"
                    class="text-gray-400 hover:text-gray-600"
                    v-tooltip="trans('Edit')"
                >
                    <FontAwesomeIcon :icon="faPencil" fixed-width aria-hidden="true" />
                </Link>

                <ModalConfirmationDelete
                    :routeDelete="{
                        name: 'grp.models.billables.leaflets.delete',
                        parameters: [leaflet.id],
                    }"
                    :title="trans('Delete leaflet :name?', { name: leaflet.name })"
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
