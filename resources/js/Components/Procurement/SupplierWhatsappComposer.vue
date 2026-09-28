<script setup lang="ts">
import { useForm } from "@inertiajs/vue3"
import { faPaperPlane } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import PureInput from "@/Components/Pure/PureInput.vue"
import PureTextarea from "@/Components/Pure/PureTextarea.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    route: routeType
    phone?: string | null
    counterpart?: string | null
    windowOpen?: boolean
    hasTemplate?: boolean
    fixedPhone?: boolean
}>()

const emit = defineEmits<{ (e: "sent"): void }>()

const form = useForm({
    phone: props.phone ?? "",
    body: "",
    counterpart: props.counterpart ?? null,
})

const send = () => {
    form.post(route(props.route.name, props.route.parameters), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset("body")
            emit("sent")
        },
    })
}
</script>

<template>
    <div class="space-y-2">
        <div v-if="!fixedPhone" class="grid grid-cols-[3.5rem_1fr] items-center gap-2 text-sm">
            <label class="text-gray-500">{{ ctrans("To") }}</label>
            <PureInput v-model="form.phone" placeholder="+86 138 0000 0000" />
        </div>

        <p v-if="!windowOpen" class="rounded bg-amber-50 px-3 py-2 text-xs text-amber-800">
            <template v-if="hasTemplate">{{ ctrans("They have not written in the last 24 hours, so this goes inside the approved message template.") }}</template>
            <template v-else>{{ ctrans("They have not written in the last 24 hours. WhatsApp only accepts an approved template then; set one in Procurement settings.") }}</template>
        </p>

        <PureTextarea v-model="form.body" :rows="4" :placeholder="ctrans('Write a WhatsApp message')" />

        <div class="flex items-center gap-2">
            <span v-if="Object.values(form.errors)[0]" class="text-xs text-red-600">{{ Object.values(form.errors)[0] }}</span>
            <Button :label="ctrans('Send')" :icon="faPaperPlane" type="primary" size="xs" class="ml-auto"
                :loading="form.processing" :disabled="!form.body.trim() || !form.phone.trim() || (!windowOpen && !hasTemplate)" @click="send" />
        </div>
    </div>
</template>
