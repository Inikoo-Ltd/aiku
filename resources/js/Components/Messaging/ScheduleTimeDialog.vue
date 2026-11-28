<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sat, 28 Nov 2026 Malaysia Time, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { computed, ref, watch } from "vue"
import Dialog from "primevue/dialog"
import DatePicker from "primevue/datepicker"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faCheck, faTimes } from "@fal"
import { fromPicker, inAnHour, toPicker, useUserTimezone, wallClock } from "@/Components/Messaging/scheduledMessages"

library.add(faCheck, faTimes)

const props = defineProps<{
    header: string
    confirmLabel: string
    initial?: string | null
    error?: string | null
    busy?: boolean
}>()

const visible = defineModel<boolean>("visible", { default: false })
const emit = defineEmits<{ (e: "pick", local: string): void }>()

const timezone = useUserTimezone()
const picked = ref<Date | null>(null)
const minDate = computed(() => toPicker(wallClock(new Date(), timezone.value)))

watch(visible, (open) => {
    if (open) picked.value = toPicker(props.initial ?? inAnHour(timezone.value))
}, { immediate: true })
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="header" :style="{ width: '22rem' }">
        <DatePicker v-model="picked" inline show-time hour-format="24" :min-date="minDate" class="w-full" :aria-label="header" />
        <p class="mt-2 text-xs text-gray-500">{{ ctrans("Times are in your time zone, :zone.", { zone: timezone }) }}</p>
        <p v-if="error" class="mt-1 text-xs text-red-600" role="alert">{{ error }}</p>
        <template #footer>
            <div class="flex w-full gap-2">
                <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon><FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="primary" full :label="confirmLabel" :disabled="!picked || busy" @click="picked && emit('pick', fromPicker(picked))">
                    <template #icon><FontAwesomeIcon icon="fal fa-check" fixed-width aria-hidden="true" /></template>
                </Button>
            </div>
        </template>
    </Dialog>
</template>
