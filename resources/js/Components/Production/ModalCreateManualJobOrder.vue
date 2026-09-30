<script setup lang="ts">
import { computed, reactive, ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Modal from "@/Components/Utils/Modal.vue"
import { ctrans } from "@/Composables/useTrans"
import { useLocaleStore } from "@/Stores/locale"

type ArtefactOption = { id: number, code: string, name: string, packed_in: number | null, stock_available: number | null, gross_weight: number | null, on_board: number }

const props = defineProps<{
    isOpen: boolean
    options: { reasons: string[], artefacts: ArtefactOption[] } | null
    artisans: { id: number, name: string }[]
}>()

const emits = defineEmits<{ (e: "onClose"): void }>()

const reasonLabels: Record<string, string> = {
    stock: ctrans("Stock"),
    partner: ctrans("Partner / PO"),
    sample: ctrans("Sample"),
    rework: ctrans("Rework"),
    other: ctrans("Other"),
}

const form = reactive({
    reason: "",
    notes: "",
    needed_by: "",
    employee_id: null as number | null,
    lines: [] as { artefact: ArtefactOption, quantity: number }[],
})
const search = ref("")
const saving = ref(false)
const errors = ref<Record<string, string>>({})

watch(() => props.isOpen, (open) => {
    if (open && !props.options) {
        router.reload({ only: ["manualJobOrder"] })
    }
}, { immediate: true })

const otherError = computed(() => Object.entries(errors.value).find(([key]) => key !== "reason" && key !== "lines")?.[1] ?? null)

const matches = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (term.length < 2 || !props.options) {
        return []
    }

    return props.options.artefacts
        .filter(artefact => !form.lines.some(line => line.artefact.id === artefact.id))
        .filter(artefact => artefact.code.toLowerCase().includes(term) || artefact.name.toLowerCase().includes(term))
        .slice(0, 12)
})

function addLine(artefact: ArtefactOption) {
    form.lines.push({ artefact, quantity: 1 })
    search.value = ""
}

const units = (line: { artefact: ArtefactOption, quantity: number }) => line.artefact.packed_in ? line.quantity * line.artefact.packed_in : null
const totalUnits = computed(() => form.lines.reduce((total, line) => total + (units(line) ?? 0), 0))
const totalKilos = computed(() => form.lines.reduce((total, line) => total + (line.artefact.gross_weight ?? 0) * line.quantity, 0) / 1000)

function close() {
    form.reason = ""
    form.notes = ""
    form.needed_by = ""
    form.employee_id = null
    form.lines = []
    errors.value = {}
    emits("onClose")
}

function save() {
    saving.value = true
    router.post(
        route("grp.org.productions.show.to_produce.manual_job_order.store", [route().params["organisation"], route().params["production"]]),
        {
            reason: form.reason,
            notes: form.notes || null,
            needed_by: form.needed_by || null,
            employee_id: form.employee_id,
            lines: form.lines.map(line => ({ artefact_id: line.artefact.id, quantity: line.quantity })),
        },
        {
            preserveScroll: true,
            onSuccess: () => close(),
            onError: (errorBag) => errors.value = errorBag,
            onFinish: () => saving.value = false,
        }
    )
}
</script>

<template>
    <Modal :isOpen="isOpen" width="w-full max-w-3xl" @onClose="close">
        <div class="text-sm">
            <h2 class="text-lg font-semibold">{{ ctrans("Create job order") }}</h2>
            <p class="text-gray-500">{{ ctrans("Plan a job by hand. It goes on the board as Assigned, waiting for the floor. The reference is given when it is saved.") }}</p>

            <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2">
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Why is it made?") }}</span>
                    <select v-model="form.reason" class="rounded border-gray-300">
                        <option value="" disabled>{{ ctrans("Choose a reason") }}</option>
                        <option v-for="reason in options?.reasons ?? []" :key="reason" :value="reason">{{ reasonLabels[reason] ?? reason }}</option>
                    </select>
                    <span v-if="errors.reason" class="text-xs text-red-600">{{ errors.reason }}</span>
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Name or notes") }} <span class="font-normal text-gray-400">({{ ctrans("optional") }})</span></span>
                    <input v-model="form.notes" type="text" maxlength="255" :placeholder="ctrans('e.g. ELEMENTS - PO85')" class="rounded border-gray-300" />
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Artisan") }}</span>
                    <select v-model="form.employee_id" class="rounded border-gray-300">
                        <option :value="null">{{ ctrans("Whoever usually makes the first product") }}</option>
                        <option v-for="artisan in artisans" :key="artisan.id" :value="artisan.id">{{ artisan.name }}</option>
                    </select>
                </label>
                <label class="flex flex-col gap-1">
                    <span class="font-medium">{{ ctrans("Target date") }} <span class="font-normal text-gray-400">({{ ctrans("optional") }})</span></span>
                    <input v-model="form.needed_by" type="date" class="rounded border-gray-300" />
                </label>
            </div>

            <div class="mt-5">
                <div class="font-medium">{{ ctrans("Products to make") }} <span class="font-normal text-gray-400">· {{ ctrans("in SKOs, as the board counts them") }}</span></div>
                <div class="relative mt-1">
                    <input v-model="search" type="search" :disabled="!options" :placeholder="options ? ctrans('Search product code or name') : ctrans('Loading products…')" class="w-full rounded border-gray-300" />
                    <div v-if="matches.length" class="absolute z-10 mt-1 max-h-64 w-full overflow-y-auto rounded border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-900">
                        <button v-for="artefact in matches" :key="artefact.id" type="button" class="flex w-full items-center gap-2 px-3 py-1.5 text-left hover:bg-gray-50 dark:hover:bg-gray-800" @click="addLine(artefact)">
                            <span class="font-medium">{{ artefact.code }}</span>
                            <span class="truncate text-gray-600">{{ artefact.name }}</span>
                        </button>
                    </div>
                </div>
                <span v-if="errors.lines" class="text-xs text-red-600">{{ errors.lines }}</span>

                <table v-if="form.lines.length" class="mt-3 w-full">
                    <thead class="text-left text-xs uppercase text-gray-400">
                        <tr>
                            <th class="py-1">{{ ctrans("Product") }}</th>
                            <th class="w-24 py-1">{{ ctrans("SKOs") }}</th>
                            <th class="w-20 py-1 text-right">{{ ctrans("Units") }}</th>
                            <th class="w-8" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="(line, index) in form.lines" :key="line.artefact.id" class="border-t border-gray-100 align-top dark:border-gray-800">
                            <td class="py-1.5">
                                <div><span class="font-medium">{{ line.artefact.code }}</span> <span class="text-gray-600">{{ line.artefact.name }}</span></div>
                                <div v-if="line.artefact.on_board" class="text-xs text-amber-700">{{ line.artefact.on_board === 1 ? ctrans("Already on the board once") : ctrans("Already on the board :count times", { count: String(line.artefact.on_board) }) }}</div>
                                <div v-if="line.artefact.stock_available" class="text-xs text-amber-700">{{ ctrans(":count SKOs in stock now", { count: useLocaleStore().number(line.artefact.stock_available) }) }}</div>
                            </td>
                            <td class="py-1.5"><input v-model.number="line.quantity" type="number" min="0.001" step="any" class="w-20 rounded border-gray-300 py-0.5" /></td>
                            <td class="py-1.5 text-right tabular-nums text-gray-600">{{ units(line) !== null ? useLocaleStore().number(units(line) as number) : "—" }}</td>
                            <td class="py-1.5 text-right"><button type="button" class="text-gray-400 hover:text-red-600" @click="form.lines.splice(index, 1)">×</button></td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 font-medium dark:border-gray-700">
                            <td class="py-1.5" colspan="2">{{ ctrans("Total") }}<span v-if="totalKilos" class="font-normal text-gray-500"> · {{ useLocaleStore().number(Math.round(totalKilos * 10) / 10) }} kg</span></td>
                            <td class="py-1.5 text-right tabular-nums">{{ useLocaleStore().number(totalUnits) }}</td>
                            <td />
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div v-if="otherError" class="mt-3 text-xs text-red-600">{{ otherError }}</div>
            <div class="mt-5 flex justify-end gap-2">
                <button type="button" class="rounded border border-gray-300 px-3 py-1.5" @click="close">{{ ctrans("Cancel") }}</button>
                <button type="button" class="rounded bg-indigo-600 px-3 py-1.5 font-medium text-white disabled:opacity-50" :disabled="saving || !form.reason || !form.lines.length || form.lines.some(line => !(line.quantity > 0))" @click="save">
                    {{ ctrans("Create job order") }}
                </button>
            </div>
        </div>
    </Modal>
</template>
