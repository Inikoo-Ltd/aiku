<!--
  - Author Louis Perez
  - Created on 02-11-2026-15h-00m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBan, faArrowLeft, faExclamationTriangle } from "@fal"
library.add(faBan, faArrowLeft, faExclamationTriangle)

const props = defineProps<{
    form: any
    fieldName: string
    fieldData: {
        route: routeType
    }
}>()

const visible = ref(false)
const reason = ref("")
const error = ref<string | null>(null)
const saving = ref(false)

function open() {
    reason.value = ""
    error.value = null
    visible.value = true
}

function confirmCancel() {
    router.patch(route(props.fieldData.route.name, props.fieldData.route.parameters), { reason: reason.value.trim() || null }, {
        onStart: () => saving.value = true,
        onFinish: () => saving.value = false,
        onError: (errors) => error.value = Object.values(errors)[0] as string,
    })
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex gap-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-xs text-amber-800">
            <FontAwesomeIcon icon="fal fa-exclamation-triangle" class="mt-0.5 text-amber-500" fixed-width aria-hidden="true" />
            <div class="space-y-1">
                <p>{{ ctrans("The development stops, its open tasks are cancelled and nobody can change it any more. This can't be undone.") }}</p>
                <p>{{ ctrans("Its trade units and its experiments in Production stay as they are.") }}</p>
            </div>
        </div>
        <Button type="tertiary" :label="ctrans('Cancel development')" @click="open">
            <template #icon><FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" /></template>
        </Button>

        <Dialog v-model:visible="visible" modal :header="ctrans('Cancel this development?')" :style="{ width: '28rem' }" :breakpoints="{ '640px': '94vw' }">
            <div class="space-y-1 text-sm">
                <label class="block text-xs text-gray-500">{{ ctrans("Reason (optional)") }}</label>
                <Textarea v-model="reason" rows="3" autoResize class="w-full text-sm" :invalid="!!error" />
                <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            </div>
            <template #footer>
                <Button type="tertiary" size="xs" :label="ctrans('Keep it')" @click="visible = false">
                    <template #icon><FontAwesomeIcon icon="fal fa-arrow-left" class="text-gray-500" fixed-width aria-hidden="true" /></template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Cancel development')" :loading="saving" @click="confirmCancel">
                    <template #icon><FontAwesomeIcon icon="fal fa-ban" class="text-red-500" fixed-width aria-hidden="true" /></template>
                </Button>
            </template>
        </Dialog>
    </div>
</template>
