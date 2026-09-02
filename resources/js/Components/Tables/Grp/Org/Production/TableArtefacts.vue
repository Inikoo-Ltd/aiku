<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 19 Mar 2023 16:45:18 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2023, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { trans } from 'laravel-vue-i18n'
import Table from '@/Components/Table/Table.vue'
import PureMultiselect from '@/Components/Pure/PureMultiselect.vue'
import Button from '@/Components/Elements/Buttons/Button.vue'
import { routeType } from '@/types/route'
import { notify } from '@kyvg/vue3-notification'

const props = defineProps<{
    data: object
    tab?: string
    moveToFamily?: {
        families: { value: number, label: string }[]
        move_route: routeType
        create_route: routeType
    }
}>()

const selected = ref<Record<string, boolean>>({})
const targetFamily = ref<number | null>(null)
const isMoving = ref(false)
const selectedIds = () => Object.entries(selected.value).filter(([, on]) => on).map(([id]) => Number(id))

const submitMove = () => {
    router.post(
        route(props.moveToFamily!.move_route.name, props.moveToFamily!.move_route.parameters),
        { artefacts: selectedIds(), artefact_family_id: targetFamily.value },
        {
            preserveScroll: true,
            onStart: () => isMoving.value = true,
            onFinish: () => isMoving.value = false,
            onSuccess: () => {
                notify({ title: trans('Success'), text: trans('Artefacts moved'), type: 'success' })
                selected.value = {}
            },
            onError: (errors) => notify({ title: trans('Something went wrong'), text: Object.values(errors).join(' '), type: 'error' }),
        }
    )
}

function productionRoute(artefact: { slug: string }) {
    switch (route().current()) {
        case 'grp.org.productions.show.crafts.artefacts.index':
        case 'grp.org.productions.show.crafts.artefact_families.show':
            return route(
                'grp.org.productions.show.crafts.artefacts.show',
                [route().params['organisation'], route().params['production'], artefact.slug]);
    }
}
</script>

<template>
    <div v-if="moveToFamily && selectedIds().length" class="sticky top-0 z-10 mx-4 mt-4 flex items-center gap-x-3 rounded-lg bg-indigo-600 px-4 py-2 text-white">
        <span class="whitespace-nowrap">{{ selectedIds().length }} {{ trans('artefacts selected') }}</span>
        <div class="w-64 text-gray-700">
            <PureMultiselect v-model="targetFamily" :options="moveToFamily.families" :placeholder="trans('Move to family')" :caret="true" />
        </div>
        <Button :label="trans('Move')" :loading="isMoving" :disabled="!targetFamily" @click="submitMove" />
        <Link :href="route(moveToFamily.create_route.name, moveToFamily.create_route.parameters)" class="ml-auto text-sm underline">
            {{ trans('New family') }}
        </Link>
    </div>

    <Table :resource="data" :name="tab" class="mt-5" :isCheckBox="!!moveToFamily" checkboxKey="id" @onSelectRow="(rows) => selected = { ...rows }">
        <template #cell(code)="{ item: production }">
            <Link :href="productionRoute(production)" class="primaryLink">
                {{ production['code'] }}
            </Link>
        </template>
        <template #cell(artefact_family_name)="{ item }">
            <Link v-if="item.artefact_family_slug" :href="route('grp.org.productions.show.crafts.artefact_families.show', [route().params['organisation'], route().params['production'], item.artefact_family_slug])" class="secondaryLink">
                {{ item.artefact_family_name }}
            </Link>
        </template>
        <template #cell(tags)="{ item }">
            <div class="flex flex-wrap gap-1">
                <span v-for="tag in item.tags" :key="tag" class="px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 text-xs">#{{ tag }}</span>
            </div>
        </template>
    </Table>
</template>
