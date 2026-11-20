<script setup lang="ts">
import { Link } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faHamsa, faArrowRight } from "@fal"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { packCount } from "@/Composables/packs"
import { useLocaleStore } from "@/Stores/locale"

library.add(faHamsa, faArrowRight)

interface ProductionBatch {
    id: number
    job_order_route: routeType
    reference: string
    status_label: string
    artisan: string | null
    date: string | null
    quantity: number
    quantity_made: number
    quantity_received: number
}

const props = defineProps<{
    data: {
        code: string
        route: routeType
        recommended_batch_size: number | null
        packed_in: number
        open_jobs: number
        units_in_production: number
        jobs_done: number
        todo_batches: ProductionBatch[]
        recent_batches: ProductionBatch[]
        partners: { code: string, name: string, ordered: number, in_bay: number }[]
    }
}>()

const locale = useLocaleStore()

const inSkos = (units: number): string => packCount(units / props.data.packed_in, props.data.packed_in)
const inUnits = (units: number): string => ctrans(":count units", { count: locale.number(units) })
const skosNote = (units: number | null): string => props.data.packed_in > 1 && units ? `${ctrans("SKOs")} · ${inUnits(units)}` : ctrans("SKOs")

const stats = [
    { title: ctrans("Open jobs"), value: props.data.open_jobs },
    { title: ctrans("In production"), value: inSkos(props.data.units_in_production), note: skosNote(props.data.units_in_production) },
    { title: ctrans("Jobs done"), value: props.data.jobs_done },
    { title: ctrans("Batch size"), value: props.data.recommended_batch_size ? inSkos(props.data.recommended_batch_size) : "-", note: skosNote(props.data.recommended_batch_size) },
]

const batchLists = [
    { key: "todo_batches", title: ctrans("To do (SKOs)") },
    { key: "recent_batches", title: ctrans("Last produced (SKOs)") },
] as const
</script>

<template>
    <section class="rounded-xl border border-gray-200 bg-white shadow-sm" aria-labelledby="org-stock-production-title">
        <div class="flex items-center gap-2 border-b border-gray-200 px-4 py-3">
            <FontAwesomeIcon icon="fal fa-hamsa" class="text-gray-400" fixed-width aria-hidden="true" />
            <h3 id="org-stock-production-title" class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ ctrans("Production") }}</h3>
            <Link :href="route(data.route.name, data.route.parameters)" class="ml-auto flex items-center gap-1 text-sm text-gray-600 hover:text-gray-900 hover:underline">
                {{ data.code }}
                <FontAwesomeIcon icon="fal fa-arrow-right" class="text-xs" fixed-width aria-hidden="true" />
            </Link>
        </div>

        <dl class="grid grid-cols-2 gap-px bg-gray-100 sm:grid-cols-4">
            <div v-for="stat in stats" :key="stat.title" class="bg-white px-4 py-3">
                <dt class="text-xs text-gray-400 whitespace-nowrap">{{ stat.title }}</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-gray-800">{{ stat.value }}</dd>
                <dd v-if="stat.note" class="text-xs tabular-nums text-gray-400">{{ stat.note }}</dd>
            </div>
        </dl>

        <div v-if="data.partners.length" class="border-t border-gray-200 px-4 py-3">
            <div class="mb-1 text-xs text-gray-400">{{ ctrans("Partners (SKOs)") }}</div>
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-gray-400">
                        <th scope="col" class="font-normal"></th>
                        <th scope="col" class="font-normal text-right">{{ ctrans("Ordered") }}</th>
                        <th scope="col" class="font-normal text-right">{{ ctrans("In bay") }}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="partner in data.partners" :key="partner.code">
                        <td class="py-0.5 text-gray-700">{{ partner.name }}</td>
                        <td class="py-0.5 text-right tabular-nums">{{ partner.ordered }}</td>
                        <td class="py-0.5 text-right tabular-nums">{{ partner.in_bay }}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-for="list in batchLists" :key="list.key" class="border-t border-gray-200 px-4 py-3">
            <div class="mb-1 text-xs text-gray-400">{{ list.title }}</div>
            <table v-if="data[list.key].length" class="w-full text-sm">
                <tbody>
                    <tr v-for="batch in data[list.key]" :key="batch.id">
                        <td class="py-0.5">
                            <Link :href="route(batch.job_order_route.name, batch.job_order_route.parameters)" class="text-gray-700 hover:underline">{{ batch.reference }}</Link>
                        </td>
                        <td class="py-0.5 text-gray-500">{{ batch.artisan ?? "-" }}</td>
                        <td class="py-0.5 text-gray-500 whitespace-nowrap">{{ useFormatTime(batch.date) }}</td>
                        <td class="py-0.5 pl-2 text-right tabular-nums whitespace-nowrap">
                            <template v-if="list.key === 'todo_batches'">{{ inSkos(batch.quantity_made) }} / {{ inSkos(batch.quantity) }}</template>
                            <template v-else>{{ inSkos(batch.quantity_received) }}</template>
                            <span v-if="data.packed_in > 1" class="ml-1 text-xs text-gray-400">({{ inUnits(list.key === 'todo_batches' ? batch.quantity : batch.quantity_received) }})</span>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-sm text-gray-400">{{ ctrans("None") }}</p>
        </div>
    </section>
</template>
