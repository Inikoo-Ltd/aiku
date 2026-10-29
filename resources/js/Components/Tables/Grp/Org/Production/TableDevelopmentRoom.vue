<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Thu, 29 Oct 2026 Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import { computed, ref } from "vue"
import Table from "@/Components/Table/Table.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import Dialog from "primevue/dialog"
import InputNumber from "primevue/inputnumber"
import InputText from "primevue/inputtext"
import Select from "primevue/select"
import Textarea from "primevue/textarea"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes, faMinus, faFlask, faRocketLaunch, faBan, faExclamationTriangle } from "@fal"

library.add(faCheck, faTimes, faMinus, faFlask, faRocketLaunch, faBan, faExclamationTriangle)

type ExperimentRow = {
    id: number
    slug: string
    code: string
    name: string | null
    recipe_steps: number
    trial_runs: number
    trial_runs_received: number
    org_stock_code: string | null
    is_on_sale: boolean
    is_ready: boolean
}

type DevelopmentActions = {
    trial_run_route: routeType
    launch_route: routeType
    cancel_route: routeType
    artisans: { id: number, name: string }[]
}

const props = defineProps<{
    data: object
    tab: string
    routes: {
        artefact: routeType
        job_order: routeType
    }
    actions: DevelopmentActions | null
}>()

const emits = defineEmits<{
    (e: "launched", code: string): void
}>()

const isBusy = ref(false)

const artefactUrl = (slug: string) => route(props.routes.artefact.name, { ...props.routes.artefact.parameters, artefact: slug })
const jobOrderUrl = (slug: string) => route(props.routes.job_order.name, { ...props.routes.job_order.parameters, jobOrder: slug })
const formatDate = (date: string | null) => useFormatTime(date ?? undefined, { formatTime: "aiku" })

const trialRunFor = ref<ExperimentRow | null>(null)
const trialRunQuantity = ref<number | null>(null)
const trialRunArtisan = ref<number | null>(null)
const trialRunNotes = ref("")
const trialRunErrors = ref<Record<string, string>>({})

const openTrialRun = (row: ExperimentRow) => {
    trialRunFor.value = row
    trialRunQuantity.value = null
    trialRunArtisan.value = null
    trialRunNotes.value = ""
    trialRunErrors.value = {}
}

const submitTrialRun = () => {
    if (!props.actions || !trialRunFor.value) return

    router.post(
        route(props.actions.trial_run_route.name, props.actions.trial_run_route.parameters),
        {
            artefact_id: trialRunFor.value.id,
            quantity: trialRunQuantity.value,
            employee_id: trialRunArtisan.value,
            notes: trialRunNotes.value || null,
        },
        {
            preserveScroll: true,
            onStart: () => isBusy.value = true,
            onFinish: () => isBusy.value = false,
            onError: (errors) => trialRunErrors.value = errors,
            onSuccess: () => trialRunFor.value = null,
        }
    )
}

const trialRunOtherErrors = computed(() =>
    Object.entries(trialRunErrors.value)
        .filter(([key]) => !["quantity", "employee_id", "notes"].includes(key))
        .map(([, error]) => error)
)

const launchFor = ref<ExperimentRow | null>(null)
const launchError = ref<string | null>(null)

const openLaunch = (row: ExperimentRow) => {
    launchFor.value = row
    launchError.value = null
}

const launchChecks = computed(() => {
    const row = launchFor.value
    if (!row) return []

    return [
        { key: "recipe", label: ctrans("Recipe the floor makes it from"), done: row.recipe_steps > 0, required: true },
        { key: "trial_run", label: ctrans("A trial run made and received"), done: row.trial_runs_received > 0, required: false },
        { key: "stock", label: ctrans("Linked to the SKO it is stocked as"), done: !!row.org_stock_code, required: true },
        { key: "product", label: ctrans("A product on sale with that SKO"), done: row.is_on_sale, required: true },
    ]
})

const submitLaunch = () => {
    if (!props.actions || !launchFor.value) return

    const code = launchFor.value.code

    router.post(
        route(props.actions.launch_route.name, { ...props.actions.launch_route.parameters, artefact: launchFor.value.id }),
        {},
        {
            preserveScroll: true,
            onStart: () => isBusy.value = true,
            onFinish: () => isBusy.value = false,
            onError: (errors) => launchError.value = Object.values(errors).join(" "),
            onSuccess: () => {
                launchFor.value = null
                emits("launched", code)
            },
        }
    )
}

const cancelFor = ref<ExperimentRow | null>(null)
const cancelReason = ref("")
const cancelErrors = ref<Record<string, string>>({})

const openCancel = (row: ExperimentRow) => {
    cancelFor.value = row
    cancelReason.value = ""
    cancelErrors.value = {}
}

const submitCancel = () => {
    if (!props.actions || !cancelFor.value) return

    router.post(
        route(props.actions.cancel_route.name, { ...props.actions.cancel_route.parameters, artefact: cancelFor.value.id }),
        { reason: cancelReason.value },
        {
            preserveScroll: true,
            onStart: () => isBusy.value = true,
            onFinish: () => isBusy.value = false,
            onError: (errors) => cancelErrors.value = errors,
            onSuccess: () => cancelFor.value = null,
        }
    )
}
</script>

<template>
    <Table :resource="data" :name="tab" class="mt-5">
        <template #cell(code)="{ item }">
            <Link :href="artefactUrl(item.slug)" class="primaryLink whitespace-nowrap">
                {{ item.code }}
            </Link>
        </template>

        <template #cell(recipe_steps)="{ item }">
            <span v-if="item.recipe_steps" class="tabular-nums">{{ item.recipe_steps }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(trial_runs)="{ item }">
            <span v-if="item.trial_runs" class="tabular-nums">{{ item.trial_runs }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(org_stock_code)="{ item }">
            <span v-if="item.org_stock_code" class="whitespace-nowrap">{{ item.org_stock_code }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(is_on_sale)="{ item }">
            <FontAwesomeIcon v-if="item.is_on_sale" :icon="['fal', 'check']" class="text-green-600" fixed-width v-tooltip="ctrans('On sale')" :aria-label="ctrans('On sale')" />
            <FontAwesomeIcon v-else :icon="['fal', 'minus']" class="text-gray-300" fixed-width v-tooltip="ctrans('Not on sale')" :aria-label="ctrans('Not on sale')" />
        </template>

        <template #cell(actions)="{ item }">
            <div v-if="actions" class="flex justify-end gap-1.5">
                <Button type="tertiary" size="xs" :label="ctrans('Trial run')" @click="openTrialRun(item)">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'flask']" fixed-width class="text-gray-500" aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Launch')" @click="openLaunch(item)">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'rocket-launch']" fixed-width class="text-green-600" aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel')" @click="openCancel(item)">
                    <template #icon><FontAwesomeIcon :icon="['fal', 'ban']" fixed-width class="text-red-500" aria-hidden="true" /></template>
                </Button>
            </div>
        </template>

        <template #cell(launched_at)="{ item }">
            <span class="whitespace-nowrap">{{ formatDate(item.launched_at) }}</span>
        </template>

        <template #cell(launched_by)="{ item }">
            <span v-if="item.launched_by">{{ item.launched_by }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(cancelled_at)="{ item }">
            <span class="whitespace-nowrap">{{ formatDate(item.cancelled_at) }}</span>
        </template>

        <template #cell(cancelled_by)="{ item }">
            <span v-if="item.cancelled_by">{{ item.cancelled_by }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(reference)="{ item }">
            <Link :href="jobOrderUrl(item.slug)" class="primaryLink whitespace-nowrap">
                {{ item.reference }}
            </Link>
        </template>

        <template #cell(artefact_code)="{ item }">
            <Link :href="artefactUrl(item.artefact_slug)" class="secondaryLink whitespace-nowrap" v-tooltip="item.artefact_name">
                {{ item.artefact_code }}
            </Link>
        </template>

        <template #cell(quantity)="{ item }">
            <span class="tabular-nums whitespace-nowrap">
                {{ item.quantity }}
                <span v-if="item.quantity_received" class="text-xs text-gray-400">({{ ctrans(":count received", { count: item.quantity_received }) }})</span>
            </span>
        </template>

        <template #cell(artisan_name)="{ item }">
            <span v-if="item.artisan_name">{{ item.artisan_name }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>

        <template #cell(date)="{ item }">
            <span class="whitespace-nowrap">{{ formatDate(item.date) }}</span>
        </template>

        <template #cell(received_at)="{ item }">
            <span v-if="item.received_at" class="whitespace-nowrap">{{ formatDate(item.received_at) }}</span>
            <span v-else class="text-gray-300">-</span>
        </template>
    </Table>

    <Dialog :visible="!!trialRunFor" modal :closable="!isBusy" :header="ctrans('Trial run of :code', { code: trialRunFor?.code ?? '' })" :style="{ width: '26rem' }" @update:visible="(visible: boolean) => { if (!visible && !isBusy) trialRunFor = null }">
        <div class="text-xs text-gray-500">{{ ctrans('The floor makes it like any job order; what is made goes to the development office, not the warehouse.') }}</div>

        <label for="trialRunQuantity" class="mt-4 block text-sm font-medium">{{ ctrans('Units') }}</label>
        <InputNumber v-model="trialRunQuantity" inputId="trialRunQuantity" :min="1" :useGrouping="false" fluid :invalid="!!trialRunErrors.quantity" class="mt-1" />
        <div v-if="trialRunErrors.quantity" class="mt-1 text-xs text-red-600">{{ trialRunErrors.quantity }}</div>

        <label for="trialRunArtisan" class="mt-3 block text-sm font-medium">{{ ctrans('Artisan') }} <span class="font-normal text-gray-400">({{ ctrans('optional') }})</span></label>
        <Select v-model="trialRunArtisan" inputId="trialRunArtisan" :options="actions?.artisans ?? []" optionLabel="name" optionValue="id" filter showClear :placeholder="ctrans('Their usual artisan')" fluid :invalid="!!trialRunErrors.employee_id" class="mt-1" />
        <div v-if="trialRunErrors.employee_id" class="mt-1 text-xs text-red-600">{{ trialRunErrors.employee_id }}</div>

        <label for="trialRunNotes" class="mt-3 block text-sm font-medium">{{ ctrans('Notes') }}</label>
        <InputText v-model="trialRunNotes" id="trialRunNotes" :maxlength="255" fluid :invalid="!!trialRunErrors.notes" class="mt-1" />
        <div v-if="trialRunErrors.notes" class="mt-1 text-xs text-red-600">{{ trialRunErrors.notes }}</div>

        <div v-for="(error, index) in trialRunOtherErrors" :key="index" class="mt-3 text-xs text-red-600">{{ error }}</div>

        <div class="mt-5 flex justify-end gap-2">
            <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isBusy" @click="trialRunFor = null">
                <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
            </Button>
            <Button type="tertiary" :label="ctrans('Raise trial run')" :loading="isBusy" :disabled="!trialRunQuantity || trialRunQuantity < 1" @click="submitTrialRun">
                <template #icon><FontAwesomeIcon :icon="['fal', 'check']" fixed-width class="text-green-600" aria-hidden="true" /></template>
            </Button>
        </div>
    </Dialog>

    <Dialog :visible="!!launchFor" modal :closable="!isBusy" :header="ctrans('Launch :code', { code: launchFor?.code ?? '' })" :style="{ width: '28rem' }" @update:visible="(visible: boolean) => { if (!visible && !isBusy) launchFor = null }">
        <div class="text-xs text-gray-500">{{ ctrans('Once launched it can be sold: it joins the artefacts list, the boards and normal job orders.') }}</div>

        <ul class="mt-4 flex flex-col gap-2">
            <li v-for="check in launchChecks" :key="check.key" class="flex items-start gap-2 text-sm">
                <FontAwesomeIcon v-if="check.done" :icon="['fal', 'check']" fixed-width class="mt-0.5 text-green-600" aria-hidden="true" />
                <FontAwesomeIcon v-else-if="check.required" :icon="['fal', 'times']" fixed-width class="mt-0.5 text-red-500" aria-hidden="true" />
                <FontAwesomeIcon v-else :icon="['fal', 'minus']" fixed-width class="mt-0.5 text-gray-400" aria-hidden="true" />
                <span :class="check.done ? '' : 'text-gray-500'">
                    {{ check.label }}
                    <span v-if="check.key === 'stock' && launchFor?.org_stock_code" class="whitespace-nowrap text-gray-400">({{ launchFor.org_stock_code }})</span>
                    <span v-if="!check.required" class="text-xs text-gray-400">({{ ctrans('advisable') }})</span>
                </span>
            </li>
        </ul>

        <div v-if="launchError" class="mt-3 text-xs text-red-600">{{ launchError }}</div>

        <div class="mt-5 flex justify-end gap-2">
            <Button type="tertiary" :label="ctrans('Cancel')" :disabled="isBusy" @click="launchFor = null">
                <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
            </Button>
            <Button type="tertiary" :label="ctrans('Launch')" :loading="isBusy" :disabled="!launchFor?.is_ready" @click="submitLaunch">
                <template #icon><FontAwesomeIcon :icon="['fal', 'rocket-launch']" fixed-width class="text-green-600" aria-hidden="true" /></template>
            </Button>
        </div>
    </Dialog>

    <Dialog :visible="!!cancelFor" modal :closable="!isBusy" :header="ctrans('Cancel :code', { code: cancelFor?.code ?? '' })" :style="{ width: '28rem' }" @update:visible="(visible: boolean) => { if (!visible && !isBusy) cancelFor = null }">
        <div class="text-xs text-gray-500">{{ ctrans('It stays in the Development room with its recipe and trial runs as the record of what was tried, and can never be launched.') }}</div>

        <label for="cancelReason" class="mt-4 block text-sm font-medium">{{ ctrans('Why') }}</label>
        <Textarea v-model="cancelReason" id="cancelReason" rows="3" :maxlength="500" autoResize fluid :invalid="!!cancelErrors.reason" class="mt-1" />
        <div v-for="(error, key) in cancelErrors" :key="key" class="mt-1 text-xs text-red-600">{{ error }}</div>

        <div class="mt-5 flex justify-end gap-2">
            <Button type="tertiary" :label="ctrans('Keep it')" :disabled="isBusy" @click="cancelFor = null">
                <template #icon><FontAwesomeIcon :icon="['fal', 'times']" fixed-width class="text-red-500" aria-hidden="true" /></template>
            </Button>
            <Button type="tertiary" :label="ctrans('Cancel experiment')" :loading="isBusy" :disabled="!cancelReason.trim()" @click="submitCancel">
                <template #icon><FontAwesomeIcon :icon="['fal', 'exclamation-triangle']" fixed-width class="text-amber-500" aria-hidden="true" /></template>
            </Button>
        </div>
    </Dialog>
</template>
