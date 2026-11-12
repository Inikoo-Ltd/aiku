<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import PageHeading from '@/Components/Headings/PageHeading.vue'
import { capitalize } from '@/Composables/capitalize'
import { ctrans } from '@/Composables/useTrans'
import { useLocaleStore } from '@/Stores/locale'
import InformationIcon from '@/Components/Utils/InformationIcon.vue'
import { library } from '@fortawesome/fontawesome-svg-core'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faPeopleArrows, faClipboardList, faWarehouse } from '@fal'
library.add(faPeopleArrows, faClipboardList, faWarehouse)

const warehouseStages: Record<string, string> = {
    in_agent_warehouse: ctrans("In the agent's warehouse, not packed yet"),
    ready_for_container: ctrans("In the agent's warehouse, packed for a container"),
}

interface Figures {
    number_purchase_orders: number
    number_lines: number
    amount: number
}

interface FigureRow extends Figures {
    agent: string
    client: string
    stage: string
    age: string
    deposit: string
}

const props = defineProps<{
    pageHead: object
    title: string
    currencyCode: string
    stages: { key: string, label: string, information?: string | null }[]
    ages: { key: string, label: string }[]
    agents: { slug: string, code: string, name: string }[]
    clients: { key: string, label: string, name: string }[]
    figures: FigureRow[]
    listRoute: { name: string, parameters: Record<string, string> }
}>()

const locale = useLocaleStore()
const money = (value: number) => locale.currencyFormat(props.currencyCode, value)

const depositFilter = ref<string | null>(null)
const ageFilter = ref<string[]>([])

const clientFilter = ref<string[]>([])
const matchesClient = (row: FigureRow) => !clientFilter.value.length || clientFilter.value.includes(row.client)
const toggleClient = (key: string) => clientFilter.value = clientFilter.value.includes(key)
    ? clientFilter.value.filter(client => client !== key)
    : [...clientFilter.value, key]

const excludeDrafts = ref(false)
const matchesDraft = (row: FigureRow) => !excludeDrafts.value || row.stage !== 'draft'
const shownStages = computed(() => props.stages.filter(stage => !excludeDrafts.value || stage.key !== 'draft'))

const matchesDeposit = (row: FigureRow) => !depositFilter.value || row.deposit === depositFilter.value
const matchesAge = (row: FigureRow) => !ageFilter.value.length || ageFilter.value.includes(row.age)
const toggleAge = (key: string) => ageFilter.value = ageFilter.value.includes(key)
    ? ageFilter.value.filter(age => age !== key)
    : [...ageFilter.value, key]

const sum = (rows: FigureRow[]): Figures => rows.reduce((total, row) => ({
    number_purchase_orders: total.number_purchase_orders + row.number_purchase_orders,
    number_lines: total.number_lines + row.number_lines,
    amount: total.amount + row.amount,
}), { number_purchase_orders: 0, number_lines: 0, amount: 0 })

const countPurchaseOrders = (rows: FigureRow[]) => sum(rows).number_purchase_orders

const filtered = computed(() => props.figures.filter(row => matchesClient(row) && matchesDeposit(row) && matchesAge(row) && matchesDraft(row)))

const columns = (agent: string | null) => {
    const rows = filtered.value.filter(row => !agent || row.agent === agent)
    return [...shownStages.value.map(stage => sum(rows.filter(row => row.stage === stage.key))), sum(rows)]
}

const depositOptions = computed(() => [
    { key: 'deposit', label: ctrans('Deposit paid') },
    { key: 'no_deposit', label: ctrans('No deposit') },
].map(option => ({
    ...option,
    count: countPurchaseOrders(props.figures.filter(row => row.deposit === option.key && matchesAge(row) && matchesClient(row) && matchesDraft(row))),
})))

const ageOptions = computed(() => props.ages.map(option => ({
    ...option,
    count: countPurchaseOrders(props.figures.filter(row => row.age === option.key && matchesDeposit(row) && matchesClient(row) && matchesDraft(row))),
})))

const filters = computed(() => [
    {
        label: ctrans('Deposit'),
        isAll: !depositFilter.value,
        clear: () => depositFilter.value = null,
        isSelected: (key: string) => depositFilter.value === key,
        select: (key: string) => depositFilter.value = key,
        allCount: countPurchaseOrders(props.figures.filter(row => matchesAge(row) && matchesClient(row) && matchesDraft(row))),
        options: depositOptions.value,
    },
    {
        label: ctrans('Sent'),
        isAll: !ageFilter.value.length,
        clear: () => ageFilter.value = [],
        isSelected: (key: string) => ageFilter.value.includes(key),
        select: toggleAge,
        allCount: countPurchaseOrders(props.figures.filter(row => matchesDeposit(row) && matchesClient(row) && matchesDraft(row))),
        options: ageOptions.value,
    },
    {
        label: ctrans('Client'),
        isAll: !clientFilter.value.length,
        clear: () => clientFilter.value = [],
        isSelected: (key: string) => clientFilter.value.includes(key),
        select: toggleClient,
        allCount: countPurchaseOrders(props.figures.filter(row => matchesDeposit(row) && matchesAge(row) && matchesDraft(row))),
        options: props.clients.map(client => ({
            ...client,
            count: countPurchaseOrders(props.figures.filter(row => row.client === client.key && matchesDeposit(row) && matchesAge(row) && matchesDraft(row))),
        })),
    },
    {
        label: ctrans('Drafts'),
        isAll: !excludeDrafts.value,
        clear: () => excludeDrafts.value = false,
        isSelected: (key: string) => key === 'exclude' && excludeDrafts.value,
        select: () => excludeDrafts.value = true,
        allCount: null,
        options: [{ key: 'exclude', label: ctrans('Exclude'), name: undefined, count: null }],
    },
])

const listHref = (agent: string | null, stage: string | null) => {
    const query = Object.fromEntries(Object.entries({
        'filter[agent]': agent,
        'filter[stage]': stage ?? (excludeDrafts.value ? shownStages.value.map(shown => shown.key).join(',') : null),
        'filter[deposit]': depositFilter.value,
        'filter[sent]': ageFilter.value.join(','),
        'filter[client]': clientFilter.value.join(','),
    }).filter(([, value]) => value))
    return route(props.listRoute.name, { ...props.listRoute.parameters, ...query })
}
const columnStage = (index: number) => shownStages.value[index]?.key ?? null

const capsuleClass = (selected: boolean) => selected ? 'bg-[var(--app-accent)] text-[var(--app-accent-text)] shadow-sm' : 'text-gray-600 hover:bg-gray-100'
const figureColours = { purchase_orders: 'text-gray-900', lines: 'text-gray-400', amount: 'text-emerald-800' }
const groupBorder = (index: number) => index === shownStages.value.length ? 'border-gray-200' : 'border-gray-100'
</script>

<template>
    <Head :title="capitalize(title)" />
    <PageHeading :data="pageHead" />

    <div class="p-4">
        <div class="rounded-xl ring-1 ring-gray-200 bg-white p-5">
            <div class="flex flex-wrap items-center gap-2">
                <div v-for="filter in filters" :key="filter.label"
                     class="flex flex-wrap items-center gap-0.5 rounded-lg border border-gray-200 bg-white px-1 py-1 text-xs">
                    <span class="mx-1 font-medium uppercase tracking-wide text-gray-400">{{ filter.label }}</span>
                    <button type="button" class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 transition duration-200"
                            :class="capsuleClass(filter.isAll)" @click="filter.clear()">
                        {{ ctrans('All') }} <span v-if="filter.allCount !== null" class="tabular-nums opacity-70">{{ locale.number(filter.allCount) }}</span>
                    </button>
                    <button v-for="option in filter.options" :key="option.key" type="button"
                            class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 whitespace-nowrap transition duration-200"
                            :class="capsuleClass(filter.isSelected(option.key))" v-tooltip="option.name" @click="filter.select(option.key)">
                        {{ option.label }} <span v-if="option.count !== null" class="tabular-nums opacity-70">{{ locale.number(option.count) }}</span>
                    </button>
                </div>
            </div>

            <div v-if="agents.length" class="mt-4 overflow-x-auto">
                <table class="w-full text-xs">
                    <thead class="whitespace-nowrap">
                        <tr class="text-gray-500">
                            <th class="text-left font-normal py-1 pr-2"></th>
                            <th v-for="(column, index) in [...shownStages, { key: 'total', label: ctrans('Total') }]" :key="column.key" colspan="3"
                                class="text-center font-medium py-1 px-2 border-l" :class="groupBorder(index)">
                                <span v-if="warehouseStages[column.key]" v-tooltip="warehouseStages[column.key]">
                                    <FontAwesomeIcon icon="fal fa-warehouse" class="mr-1 text-gray-400" fixed-width aria-hidden="true" />{{ column.label }}
                                </span>
                                <template v-else>{{ column.label }}</template>
                                <InformationIcon v-if="column.information" :information="column.information" class="ml-1" />
                            </th>
                        </tr>
                        <tr class="text-gray-400 border-b border-gray-200">
                            <th class="text-left font-normal py-1.5 pr-2">{{ ctrans('Agent') }}</th>
                            <template v-for="(column, index) in [...shownStages, { key: 'total' }]" :key="column.key">
                                <th class="text-right font-normal py-1.5 pl-3 pr-1 border-l" :class="[groupBorder(index), figureColours.purchase_orders]">{{ ctrans('POs') }}</th>
                                <th class="text-right font-normal py-1.5 px-1" :class="figureColours.lines">{{ ctrans('Lines') }}</th>
                                <th class="text-right font-normal py-1.5 pl-1 pr-2" :class="figureColours.amount">{{ ctrans('Amount') }}</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in [...agents.map(agent => ({ ...agent, total: false })), { slug: null, code: ctrans('Total'), name: '', total: true }]"
                            :key="row.slug ?? 'total'" :class="row.total ? 'border-t-2 border-gray-300' : 'border-b border-gray-100'">
                            <td class="py-2 pr-2 whitespace-nowrap text-gray-900" :class="row.total ? 'font-semibold' : 'font-medium'">
                                <span v-tooltip="row.name || undefined">{{ row.code }}</span>
                            </td>
                            <template v-for="(figures, index) in columns(row.slug)" :key="index">
                                <td class="text-right pl-3 pr-1 tabular-nums border-l" :class="[groupBorder(index), figureColours.purchase_orders]">
                                    <Link v-if="figures.number_purchase_orders" :href="listHref(row.slug, columnStage(index))" class="hover:underline">{{ locale.number(figures.number_purchase_orders) }}</Link>
                                </td>
                                <td class="text-right px-1 tabular-nums" :class="figureColours.lines">
                                    <Link v-if="figures.number_purchase_orders" :href="listHref(row.slug, columnStage(index))" class="hover:underline">{{ locale.number(figures.number_lines) }}</Link>
                                </td>
                                <td class="text-right pl-1 pr-2 tabular-nums whitespace-nowrap" :class="figureColours.amount">
                                    <Link v-if="figures.number_purchase_orders" :href="listHref(row.slug, columnStage(index))" class="hover:underline" v-tooltip="money(figures.amount)">{{ locale.CurrencyShort(currencyCode, figures.amount) }}</Link>
                                </td>
                            </template>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-3 text-xs text-gray-400">{{ ctrans('Pending purchase orders: not received, cancelled or closed yet') }}</div>
            </div>
            <div v-else class="mt-4 text-xs text-gray-400">{{ ctrans('No pending purchase orders') }}</div>
        </div>
    </div>
</template>
