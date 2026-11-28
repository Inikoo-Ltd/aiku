<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from "vue"
import { usePage } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faTools, faCoffee } from "@fal"
library.add(faTools, faCoffee)

interface MaintenanceNotice {
    starts_at: string
    minutes: number
}

const notice = ref<MaintenanceNotice | null>(null)
const isOpen = ref(false)

const dismissedKey = (n: MaintenanceNotice) => `maintenance-notice-seen:${n.starts_at}:${n.minutes}`

const show = (n: MaintenanceNotice | null) => {
    notice.value = n
    isOpen.value = !!n && !localStorage.getItem(dismissedKey(n))
}

const dismiss = () => {
    if (notice.value) {
        localStorage.setItem(dismissedKey(notice.value), "1")
    }
    isOpen.value = false
}

const startsAt = computed(() => notice.value ? new Date(notice.value.starts_at) : null)
const backByLabel = computed(() => startsAt.value && notice.value ? new Date(startsAt.value.getTime() + notice.value.minutes * 60000).toLocaleTimeString(undefined, { hour: "2-digit", minute: "2-digit" }) : "")
const startsAtLabel = computed(() => startsAt.value?.toLocaleString(undefined, { weekday: "long", day: "numeric", month: "long", hour: "2-digit", minute: "2-digit" }) ?? "")

watch(() => usePage().props.maintenance_notice as MaintenanceNotice | null, show, { immediate: true })

const onNotice = (event: { notice: MaintenanceNotice | null }) => show(event?.notice ?? null)

onMounted(() => window.Echo?.private("app.general").listen(".maintenance-notice", onNotice))
onUnmounted(() => window.Echo?.private("app.general").stopListening(".maintenance-notice", onNotice))
</script>

<template>
    <Dialog
        v-model:visible="isOpen"
        modal
        :closable="false"
        :showHeader="false"
        :style="{ width: '30rem' }"
        :breakpoints="{ '640px': '90vw' }">
        <div class="pt-8 pb-4 text-center">
            <div class="mx-auto flex h-12 w-24 items-center justify-center gap-2 rounded-full bg-amber-50">
                <FontAwesomeIcon icon="fal fa-tools" class="text-amber-500 text-2xl" fixed-width aria-hidden="true" />
                <FontAwesomeIcon icon="fal fa-coffee" class="text-amber-700 text-2xl" fixed-width aria-hidden="true" />
            </div>
            <div class="mt-4 font-semibold text-xl">
                {{ ctrans("Aiku maintenance") }}
            </div>
            <div class="mt-3 text-sm text-gray-600">
                {{ ctrans("We will be updating the servers on :when (your time). Aiku may be unavailable for about :minutes minutes.", { when: startsAtLabel, minutes: notice?.minutes ?? "" }) }}
            </div>
            <div class="mt-2 text-sm text-gray-500">
                {{ ctrans("Please save your work before then. Anything you do during that time may not be saved.") }}
            </div>
            <div class="mt-4 text-sm text-gray-600">
                {{ ctrans("Meanwhile, relax and make yourself a cup of coffee. If Aiku is not back by :back, then you can start to panic.", { back: backByLabel }) }}
            </div>
            <div class="mt-6">
                <Button @click="dismiss" :label="ctrans('Got it')" type="tertiary" full />
            </div>
        </div>
    </Dialog>
</template>
