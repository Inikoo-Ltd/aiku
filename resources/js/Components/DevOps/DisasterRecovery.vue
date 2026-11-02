<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 01 Nov 2026 14:00:00 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Textarea from "primevue/textarea"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faLifeRing, faPlus, faTimes } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"

library.add(faCheck, faLifeRing, faPlus, faTimes)

export interface DisasterRecoveryReport {
    last: { rehearsed_on: string; notes: string | null; user: string | null } | null
    next_due_on: string | null
    days_until_due: number | null
    issue: string | null
}

const props = defineProps<{ report: DisasterRecoveryReport }>()

const status = computed(() => {
    if (props.report.issue) {
        return { dot: "bg-red-500", text: "text-red-700", label: ctrans("Overdue") }
    }
    if ((props.report.days_until_due ?? 0) <= 14) {
        return { dot: "bg-amber-500", text: "text-amber-700", label: ctrans("Due soon") }
    }

    return { dot: "bg-emerald-500", text: "text-emerald-700", label: ctrans("On schedule") }
})

const day = (iso: string | null) => (iso ? useFormatTime(iso, { formatTime: "dd MMM yyyy" }) : "-")
const isoDate = (date: Date) => `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}`
const threeMonthsAfter = (date: Date) => new Date(date.getFullYear(), date.getMonth() + 3, date.getDate())

const isOpen = ref(false)
const isSaving = ref(false)
const rehearsedOn = ref<Date>(new Date())
const nextDueOn = ref<Date>(threeMonthsAfter(new Date()))
const notes = ref("")
const errors = ref<Record<string, string>>({})

const open = () => {
    rehearsedOn.value = new Date()
    nextDueOn.value = threeMonthsAfter(rehearsedOn.value)
    notes.value = ""
    errors.value = {}
    isOpen.value = true
}

const save = () => {
    router.post(route("grp.devops.disaster_recovery_rehearsals.store"), {
        rehearsed_on: isoDate(rehearsedOn.value),
        next_due_on: isoDate(nextDueOn.value),
        notes: notes.value || null,
    }, {
        preserveScroll: true,
        onStart: () => (isSaving.value = true),
        onFinish: () => (isSaving.value = false),
        onError: (formErrors) => (errors.value = formErrors),
        onSuccess: () => (isOpen.value = false),
    })
}
</script>

<template>
    <div>
        <section class="rounded-lg border border-gray-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-x-2">
                    <FontAwesomeIcon icon="fal fa-life-ring" class="text-gray-500" fixed-width aria-hidden="true" />
                    <h3 class="text-sm font-semibold text-gray-900">{{ ctrans("Disaster recovery rehearsal") }}</h3>
                    <span class="inline-flex items-center gap-x-1.5 text-xs" :class="status.text">
                        <span class="h-2 w-2 rounded-full" :class="status.dot" />
                        {{ status.label }}
                    </span>
                </div>
                <Button type="tertiary" :label="ctrans('Record rehearsal')" @click="open">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-plus" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
            <dl class="mt-3 grid grid-cols-1 gap-3 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Last rehearsal") }}</dt>
                    <dd class="text-gray-900">{{ report.last ? day(report.last.rehearsed_on) : ctrans("Never") }}<span v-if="report.last?.user" class="text-gray-500"> · {{ report.last.user }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500">{{ ctrans("Next one due") }}</dt>
                    <dd :class="status.text">
                        {{ day(report.next_due_on) }}
                        <span v-if="report.days_until_due !== null" class="text-gray-500">
                            ({{ report.days_until_due >= 0 ? ctrans("in :days days", { days: report.days_until_due }) : ctrans(":days days late", { days: -report.days_until_due }) }})
                        </span>
                    </dd>
                </div>
                <div class="sm:col-span-1">
                    <dt class="text-xs text-gray-500">{{ ctrans("Notes") }}</dt>
                    <dd class="text-gray-700">{{ report.last?.notes || "-" }}</dd>
                </div>
            </dl>
        </section>

        <Dialog v-model:visible="isOpen" modal :header="ctrans('Record disaster recovery rehearsal')" :style="{ width: '32rem' }">
            <div class="space-y-4 text-sm">
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-500">{{ ctrans("Done on") }}</span>
                        <DatePicker v-model="rehearsedOn" date-format="dd M yy" :max-date="new Date()" class="w-full" @update:model-value="(date: Date) => (nextDueOn = threeMonthsAfter(date))" />
                        <span v-if="errors.rehearsed_on" class="mt-1 block text-xs text-red-600">{{ errors.rehearsed_on }}</span>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-xs text-gray-500">{{ ctrans("Next one due") }}</span>
                        <DatePicker v-model="nextDueOn" date-format="dd M yy" :min-date="rehearsedOn" class="w-full" />
                        <span v-if="errors.next_due_on" class="mt-1 block text-xs text-red-600">{{ errors.next_due_on }}</span>
                    </label>
                </div>
                <label class="block">
                    <span class="mb-1 block text-xs text-gray-500">{{ ctrans("Notes: times, image, what went wrong") }}</span>
                    <Textarea v-model="notes" rows="4" class="w-full" />
                    <span v-if="errors.notes" class="mt-1 block text-xs text-red-600">{{ errors.notes }}</span>
                </label>
            </div>
            <template #footer>
                <Button type="tertiary" :label="ctrans('Cancel')" @click="isOpen = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="ctrans('Save')" :loading="isSaving" @click="save">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </template>
        </Dialog>
    </div>
</template>
