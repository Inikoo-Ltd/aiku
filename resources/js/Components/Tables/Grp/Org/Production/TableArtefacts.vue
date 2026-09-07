<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Icon from "@/Components/Icon.vue"
import { Link, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import Table from '@/Components/Table/Table.vue'
import PureMultiselectInfiniteScroll from '@/Components/Pure/PureMultiselectInfiniteScroll.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'
import { ctrans } from '@/Composables/useTrans'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { library } from '@fortawesome/fontawesome-svg-core'
import { faCheckSquare, faFolderPlus } from '@fal'

library.add(faCheckSquare, faFolderPlus)

type Family = { id: number, code: string, name: string, number_artefacts: number }

const props = defineProps<{
    data: object
    tab?: string
    moveToFamily?: {
        families_route: routeType
        move_route: routeType
        create_route: routeType
    }
}>()

const routeCurrent = route().current()
const routeParams = route().params

const tableRef = ref<any>(null)
const selected = ref<Record<string, boolean>>({})
const targetFamily = ref<Family | null>(null)
const isMoving = ref(false)

const selectedIds = computed(() => Object.entries(selected.value).filter(([, on]) => on).map(([id]) => Number(id)))

const clearSelection = () => {
    const rows = tableRef.value?.selectRow
    if (rows) {
        Object.keys(rows).forEach(id => rows[id] = false)
    }
    selected.value = {}
}

const submitMove = () => {
    if (!props.moveToFamily || !targetFamily.value || !selectedIds.value.length) return

    const family = targetFamily.value
    const count = selectedIds.value.length

    router.post(
        route(props.moveToFamily.move_route.name, props.moveToFamily.move_route.parameters),
        { artefacts: selectedIds.value, artefact_family_id: family.id },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({
                    title: ctrans('Artefacts moved'),
                    text: ctrans(':count moved to :family', { count: count, family: family.name }),
                    type: 'success',
                })
                clearSelection()
                targetFamily.value = null
            },
            onError: (errors) => notify({ title: ctrans('Something went wrong'), text: Object.values(errors).join(' '), type: 'error' }),
        }
    )
}

function productionRoute(artefact: { slug: string }) {
    switch (routeCurrent) {
        case 'grp.org.productions.show.crafts.artefacts.index':
        case 'grp.org.productions.show.crafts.artefact_families.show':
            return route(
                'grp.org.productions.show.crafts.artefacts.show',
                [routeParams['organisation'], routeParams['production'], artefact.slug]);
    }
}
</script>

<template>
    <Transition
        enter-active-class="transition duration-150 ease-out"
        enter-from-class="-translate-y-2 opacity-0"
        leave-active-class="transition duration-100 ease-in"
        leave-to-class="-translate-y-2 opacity-0">
        <div
            v-if="moveToFamily && selectedIds.length"
            class="sticky top-0 z-10 mx-4 mt-4 flex flex-wrap items-center gap-x-3 gap-y-2 rounded-md bg-indigo-600 px-4 py-2.5 text-white shadow-lg"
            role="region"
            :aria-label="ctrans('Bulk actions')">
            <span class="flex items-center gap-2 whitespace-nowrap font-medium" aria-live="polite">
                <FontAwesomeIcon icon="fal fa-check-square" fixed-width aria-hidden="true" />
                {{ selectedIds.length === 1 ? ctrans('1 artefact selected') : ctrans(':count artefacts selected', { count: selectedIds.length }) }}
            </span>

            <button type="button" class="text-xs text-indigo-100 underline underline-offset-2 hover:text-white" @click="clearSelection">
                {{ ctrans('Clear') }}
            </button>

            <div class="ml-auto flex flex-wrap items-center gap-x-2 gap-y-2">
                <div class="w-72 text-gray-700">
                    <PureMultiselectInfiniteScroll
                        v-model="targetFamily"
                        :fetchRoute="moveToFamily.families_route"
                        :placeholder="ctrans('Move to family')"
                        :noOptionsText="ctrans('No families yet')"
                        valueProp="id"
                        labelProp="name"
                        labelAdditionalProp="code"
                        fetchOnOpen
                        :object="true" />
                </div>

                <Button
                    :label="ctrans('Move')"
                    :loading="isMoving"
                    :disabled="!targetFamily"
                    :tooltip="targetFamily ? ctrans('Move to :family', { family: targetFamily.name }) : ctrans('Pick a family first')"
                    @click="submitMove" />

                <Link
                    :href="route(moveToFamily.create_route.name, moveToFamily.create_route.parameters)"
                    class="flex items-center gap-1.5 whitespace-nowrap text-sm text-indigo-100 underline underline-offset-2 hover:text-white">
                    <FontAwesomeIcon icon="fal fa-folder-plus" fixed-width aria-hidden="true" />
                    {{ ctrans('New family') }}
                </Link>
            </div>
        </div>
    </Transition>

    <Table ref="tableRef" :resource="data" :name="tab" class="mt-5" :isCheckBox="!!moveToFamily" checkboxKey="id" @onSelectRow="(rows) => selected = { ...rows }">
        <template #cell(state)="{ item: artefact }">
            <Icon :data="artefact.state" />
        </template>
        <template #cell(code)="{ item: production }">
            <Link :href="productionRoute(production)" class="primaryLink">
                {{ production['code'] }}
            </Link>
        </template>
        <template #cell(artefact_family_name)="{ item }">
            <Link v-if="item.artefact_family_slug" :href="route('grp.org.productions.show.crafts.artefact_families.show', [routeParams['organisation'], routeParams['production'], item.artefact_family_slug])" class="secondaryLink">
                {{ item.artefact_family_name }}
            </Link>
            <span v-else class="text-gray-400">-</span>
        </template>
        <template #cell(tags)="{ item }">
            <div class="flex flex-wrap gap-1">
                <span v-for="tag in item.tags" :key="tag" class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">#{{ tag }}</span>
            </div>
        </template>
    </Table>
</template>
