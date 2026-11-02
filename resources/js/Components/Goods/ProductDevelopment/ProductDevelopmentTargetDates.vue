<!--
  - Author Louis Perez
  - Created on 16-10-2026-02h-25m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import { router } from "@inertiajs/vue3"
import Popover from "primevue/popover"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faChevronDown } from "@fal"
import { faSave } from "@fas"
library.add(faChevronDown, faSave)

const props = defineProps<{
    landing: string | null
    online: string | null
    canEdit: boolean
    updateRoute: routeType
}>()

const popover = ref()
const isOpen = ref(false)
const landingDate = ref<Date | null>(null)
const onlineDate = ref<Date | null>(null)
const errors = ref<Record<string, string>>({})

watch([landingDate, onlineDate], () => {
    delete errors.value.target_online_date
    if (landingDate.value && onlineDate.value && onlineDate.value < landingDate.value) {
        onlineDate.value = null
    }
})
const saving = ref(false)

const formatDate = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy" }) : "—"

const toIso = (date: Date | null) => date ? `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, "0")}-${String(date.getDate()).padStart(2, "0")}` : null

function toggle(event: Event) {
    landingDate.value = props.landing ? new Date(props.landing) : null
    onlineDate.value = props.online ? new Date(props.online) : null
    errors.value = {}
    popover.value.toggle(event)
}

function save() {
    router.patch(route(props.updateRoute.name, props.updateRoute.parameters), {
        target_landing_date: toIso(landingDate.value),
        target_online_date: toIso(onlineDate.value),
    }, {
        preserveScroll: true,
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (validationErrors) => errors.value = validationErrors,
        onSuccess: () => popover.value.hide(),
    })
}
</script>

<template>
    <div>
        <button
            type="button"
            class="-mx-1 flex items-center gap-1.5 rounded px-1 tabular-nums text-gray-800 hover:bg-gray-50"
            :aria-expanded="isOpen"
            @click="toggle"
        >
            {{ formatDate(online) }}
            <FontAwesomeIcon icon="fal fa-chevron-down" class="text-xs text-gray-400 transition-transform" :class="isOpen ? 'rotate-180' : ''" fixed-width aria-hidden="true" />
        </button>

        <Popover ref="popover" @show="isOpen = true" @hide="isOpen = false">
            <div class="w-56 space-y-2.5 text-sm">
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target landing") }}</label>
                    <DatePicker v-if="canEdit" v-model="landingDate" size="small" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" />
                    <div v-else class="tabular-nums text-gray-800">{{ formatDate(landing) }}</div>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-500">{{ ctrans("Target online") }}</label>
                    <DatePicker v-if="canEdit" v-model="onlineDate" size="small" dateFormat="dd M yy" showIcon iconDisplay="input" showButtonBar class="w-full" panelClass="text-sm" :minDate="landingDate ?? undefined" :invalid="!!errors.target_online_date" />
                    <div v-else class="tabular-nums text-gray-800">{{ formatDate(online) }}</div>
                    <p v-if="errors.target_online_date" class="mt-1 text-xs text-red-600">{{ errors.target_online_date }}</p>
                </div>
                <div v-if="canEdit" class="flex justify-end">
                    <Button type="primary" size="xs" icon="fas fa-save" :label="ctrans('Save')" :loading="saving" @click="save" />
                </div>
                <p v-else class="text-xs text-gray-400">{{ ctrans("Only the owner and purchasing can change these dates") }}</p>
            </div>
        </Popover>
    </div>
</template>
