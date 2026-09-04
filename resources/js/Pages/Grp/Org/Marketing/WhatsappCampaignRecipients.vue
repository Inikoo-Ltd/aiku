<script setup lang="ts">
import { computed, ref, watch } from "vue"
import { Head, router } from "@inertiajs/vue3"
import axios from "axios"
import { trans } from "laravel-vue-i18n"
import { Message } from "primevue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheckSquare, faSquare } from "@fal"
import PageHeading from "@/Components/Headings/PageHeading.vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import TableTemplateRecipients from "@/Components/Tables/TableTemplateRecipients.vue"
import { routeType } from "@/types/route"

library.add(faCheckSquare, faSquare)

const props = defineProps<{
    title: string
    pageHead: any
    customers: any
    recipientsCount: number
    templateTags: string[]
    channels: Record<string, boolean>
    filters: Record<string, any>
    filtersStructure: Record<string, any>
    shop_id: number
    shop_slug: string
    storeRoute: routeType
    backRoute: routeType
}>()

const channelOptions = [
    { value: "subscriber", label: "Subscriber" },
    { value: "contacted", label: "Contacted" },
    { value: "customers", label: "Customers" },
]

/* How many toggles one save may carry, matching the cap the endpoint enforces. Past this the
   page is describing an audience rather than an edit. */
const DELTA_CAP = 5000

/* What the rows already say is stored, which the server answers per row, plus what the user
   has changed since. Neither grows with the campaign: the flags arrive on the page's own rows
   and the two sets only ever hold contacts somebody actually clicked.

   isWholeAudience is the one selection the page cannot express as keys, because it reaches
   contacts no page ever loaded. Everything else is an edit to what is already stored. */
const pendingSelect = ref<Set<string>>(new Set())
const pendingUnselect = ref<Set<string>>(new Set())
const isWholeAudience = ref(false)
const isCleared = ref(false)

const rows = computed<any[]>(() => props.customers?.data ?? [])
const audienceTotal = computed<number>(() => props.customers?.meta?.total ?? rows.value.length)

const isRowSelected = (row: any) => {
    if (isWholeAudience.value) return !pendingUnselect.value.has(row.recipient_key)
    if (pendingSelect.value.has(row.recipient_key)) return true
    if (pendingUnselect.value.has(row.recipient_key)) return false

    return isCleared.value ? false : !!row.is_selected
}

/* Table reads this once per mount and never watches it, so it is rebuilt for the rows on
   screen and the table is remounted whenever the answer changes. Bounded by the page. */
const selection = computed<Record<string, boolean>>(() =>
    Object.fromEntries(rows.value.map((row) => [row.recipient_key, isRowSelected(row)]))
)

const deltaSize = computed(() => pendingSelect.value.size + pendingUnselect.value.size)
const isOverCap = computed(() => deltaSize.value > DELTA_CAP)

const selectedCount = computed(() => {
    if (isWholeAudience.value) return audienceTotal.value - pendingUnselect.value.size
    if (isCleared.value) return pendingSelect.value.size

    return props.recipientsCount + pendingSelect.value.size - pendingUnselect.value.size
})

/* Whether every contact on screen is ticked, which is what makes offering the rest of the
   audience worth the room. Read off the same predicate the checkboxes use, so the banner
   cannot disagree with what is on screen. */
const isPageFullyTicked = computed(() => rows.value.length > 0 && rows.value.every(isRowSelected))

const hasSelection = computed(() => selectedCount.value > 0)

/* Table snapshots its checkbox map once per mount and seeds anything it has not been told
   about to false, so a new page of rows would render unticked however the server marked them.
   Paging is therefore part of the key: the table has to be rebuilt to hear about them. */
const tableVersion = ref(0)
const tableKey = computed(() =>
    [
        Object.keys(props.channels).filter((key) => props.channels[key]).join("-"),
        props.customers?.meta?.current_page ?? 1,
        tableVersion.value,
    ].join("-")
)

/* Recorded against what the row already was, so ticking a contact back to how they started
   drops the toggle instead of storing a second one. That is what keeps the sets the size of
   the edit rather than the size of the browsing. */
const setRow = (row: any, checked: boolean) => {
    const key = row.recipient_key
    const base = isWholeAudience.value ? true : isCleared.value ? false : !!row.is_selected

    pendingSelect.value.delete(key)
    pendingUnselect.value.delete(key)

    if (checked !== base) {
        (checked ? pendingSelect : pendingUnselect).value.add(key)
    }

    pendingSelect.value = new Set(pendingSelect.value)
    pendingUnselect.value = new Set(pendingUnselect.value)
}

const onSelectRow = (emitted: Record<string, boolean>) => {
    rows.value.forEach((row) => {
        const checked = !!emitted[row.recipient_key]

        if (checked !== isRowSelected(row)) {
            setRow(row, checked)
        }
    })
}

/* Ticks the contacts on screen and nothing else. It reads the loaded page, so an all ticked
   page is the untick.

   Each row goes through setRow, so a page tick is an ordinary edit recorded against what the
   rows already were, not a mode. Selecting the audience the page cannot see is the banner's
   job, and it says so in words with the count attached. */
const onTogglePage = (isPageChecked: boolean) => {
    rows.value.forEach((row) => setRow(row, !isPageChecked))
    tableVersion.value++
}

/* Both of these restate the selection rather than edit it, so the toggles made against the
   previous one are dropped: they answered a question the user has just replaced. */
const selectWholeAudience = () => {
    isWholeAudience.value = true
    isCleared.value = false
    pendingSelect.value = new Set()
    pendingUnselect.value = new Set()
    tableVersion.value++
}

const clearSelection = () => {
    isWholeAudience.value = false
    isCleared.value = true
    pendingSelect.value = new Set()
    pendingUnselect.value = new Set()
    tableVersion.value++
}

/* Narrows an audience wide selection back to the contacts on screen. It clears first so the
   page is being added to nothing, rather than removed from everything: the contacts on the
   pages the browser never loaded cannot be named, so they can only be dropped wholesale. */
const selectOnlyThisPage = () => {
    clearSelection()
    rows.value.forEach((row: any) => setRow(row, true))
    tableVersion.value++
}

/* Agent tags are filled from whoever is handling a conversation, and a campaign send has no
   agent, so a template carrying one reaches nobody. Called out on its own because the empty
   table it produces otherwise reads as a bug. */
const unfillableTags = computed(() => props.templateTags.filter((tag) => tag.startsWith("Agent ")))

const isSaving = ref(false)
const saveError = ref<string | null>(null)

const goBack = () => router.visit(route(props.backRoute.name, props.backRoute.parameters))

/* Selecting the audience and clearing it both restate the selection, so they are sent as such
   and cost nothing per contact. Anything else is the toggles the user made, and only those. */
const savePayload = () => {
    /* Still says select all when contacts were unticked after it: the audience is everything,
       less the handful named. Sending the unticks alone would forget the all and leave the
       campaign holding whatever it happened to hold before. */
    if (isWholeAudience.value) {
        return { select_all: true, unselect: [...pendingUnselect.value] }
    }

    /* A cleared selection is a complete statement rather than a delta, so it replaces what is
       stored: phone_keys is the whole of the new selection, empty if nothing was re-ticked. */
    if (isCleared.value) {
        return { phone_keys: [...pendingSelect.value] }
    }

    return {
        select: [...pendingSelect.value],
        unselect: [...pendingUnselect.value],
    }
}

const onSelect = async () => {
    isSaving.value = true
    saveError.value = null

    try {
        await axios.post(route(props.storeRoute.name, props.storeRoute.parameters), {
            channels: props.channels,
            customer_filters: props.filters ?? {},
            ...savePayload(),
        })

        goBack()
    } catch (error: any) {
        saveError.value =
            error?.response?.data?.message ?? trans("Could not save the recipients, please try again.")
        isSaving.value = false
    }
}
</script>

<template>
    <Head :title="title" />

    <PageHeading :data="pageHead" />

    <div class="px-4 sm:px-6 py-6">
        <div class="mb-4">
            <h2 class="text-lg font-medium text-gray-800">
                {{ trans(":count contacts in your audience", { count: selectedCount }) }}
            </h2>
            <p class="text-sm text-gray-500">{{ trans("Only the selected contacts will be included.") }}</p>
            <p v-if="templateTags.length" class="mt-1 text-sm text-gray-500">
                {{ trans("Your template needs :tags, so contacts without them are left out.", { tags: templateTags.join(", ") }) }}
            </p>
        </div>

        <Message v-if="unfillableTags.length" severity="warn" :closable="false" class="mb-4">
            {{ trans("Your template uses :tags, which a campaign has no value for, so it cannot be sent to anyone. Remove it from the template to choose recipients.", { tags: unfillableTags.join(", ") }) }}
        </Message>

        <Message v-if="isOverCap" severity="warn" :closable="false" class="mb-4">
            {{ trans("You have changed :count contacts one by one, which is more than a single save carries. Narrow the audience with the filters instead, or select every contact.", { count: deltaSize }) }}
        </Message>

        <Message v-if="saveError" severity="error" :closable="false" class="mb-4">{{ saveError }}</Message>

        <TableTemplateRecipients
            :filters="filters ?? {}"
            :filters-structure="filtersStructure"
            :recipients-recipe="filters && Object.keys(filters).length ? filters : null"
            :channels="channels"
            :channel-options="channelOptions"
            :reload-only="['customers', 'filters', 'channels', 'recipientsCount', 'queryBuilderProps']"
            :shop-id="shop_id"
            :shop-slug="shop_slug"
            :show-save="false"
            :show-estimate="false" />

        <!-- The header checkbox only reaches the page, so the rest of the audience is offered
             here in words, with the number it stands for, rather than hidden in that tick. -->
        <Message v-if="isWholeAudience || (isPageFullyTicked && rows.length < audienceTotal)" severity="info"
            :closable="false" class="mb-4">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <template v-if="isWholeAudience">
                    <span>{{ trans("All :total contacts in this audience are selected.", { total: audienceTotal }) }}</span>
                    <button type="button" class="underline font-medium" @click="selectOnlyThisPage">
                        {{ trans("Select only this page instead") }}
                    </button>
                </template>

                <template v-else>
                    <span>{{ trans("All :count contacts on this page are selected.", { count: rows.length }) }}</span>
                    <button type="button" class="underline font-medium" @click="selectWholeAudience">
                        {{ trans("Select all :total in this audience", { total: audienceTotal }) }}
                    </button>
                </template>
            </div>
        </Message>

        <Table
            :key="tableKey"
            :resource="customers"
            :isCheckBox="true"
            checkboxKey="recipient_key"
            :selectedRow="selection"
            @onSelectRow="onSelectRow">
            <template #header-checkbox="{ header }">
                <div class="py-1.5 cursor-pointer" :title="trans('Select the contacts on this page')"
                    @click="onTogglePage(header.value)">
                    <FontAwesomeIcon :icon="header.value ? 'fal fa-check-square' : 'fal fa-square'"
                        class="mx-auto block h-5 my-auto" fixed-width aria-hidden="true" />
                </div>
            </template>

            <template #cell(sources)="{ item }">
                <div class="flex flex-wrap gap-1">
                    <span v-for="source in item.sources" :key="source"
                        class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600 whitespace-nowrap">
                        {{ source }}
                    </span>
                </div>
            </template>
        </Table>

        <div class="sticky bottom-0 z-10 mt-6 flex items-center justify-between border-t border-gray-200 bg-white py-4">
            <Button :label="trans('Back')" style="tertiary" @click="goBack" />
            <div class="flex items-center gap-x-4">
                <!-- Emptying the audience has no checkbox of its own now that the header one
                     only reaches a page, so it lives here where it is reachable from any page. -->
                <button v-if="hasSelection" type="button" class="text-sm text-gray-500 underline"
                    @click="clearSelection">
                    {{ trans("Clear selection") }}
                </button>
                <Button
                    :label="trans('Select')"
                    style="primary"
                    :loading="isSaving"
                    :disabled="isOverCap"
                    @click="onSelect" />
            </div>
        </div>
    </div>
</template>
