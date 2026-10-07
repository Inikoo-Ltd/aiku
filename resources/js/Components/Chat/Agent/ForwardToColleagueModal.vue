<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faShare } from "@fortawesome/free-solid-svg-icons"
import { notify } from "@kyvg/vue3-notification"
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import StaffTaskCollaborators from "@/Components/Tasks/StaffTaskCollaborators.vue"

interface Person {
    id: number
    name: string
    avatar: any
}

const props = defineProps<{
    isOpen: boolean
    organisation: string
    sessionUlid?: string | null
}>()

const emit = defineEmits(["close", "forwarded"])

const people = ref<Person[]>([])
const note = ref("")
const alsoEmail = ref(false)
const isSending = ref(false)

const close = () => emit("close")

const confirmForward = async () => {
    if (people.value.length === 0 || isSending.value) return

    isSending.value = true
    try {
        const { data } = await axios.post(
            route("grp.org.chat.agents.sessions.forward", [props.organisation, props.sessionUlid]),
            {
                user_ids: people.value.map((person) => person.id),
                note: note.value,
                also_email: alsoEmail.value,
            }
        )

        notify({
            title: ctrans("Forwarded"),
            text: ctrans("Sent to :names.", { names: people.value.map((person) => person.name).join(", ") }),
            type: "success",
        })

        const notEmailed: string[] = data?.data?.not_emailed ?? []
        if (notEmailed.length) {
            notify({
                title: ctrans("Not emailed"),
                text: ctrans(":names has no work email in Aiku, so only got it in the staff chat.", { names: notEmailed.join(", ") }),
                type: "warning",
            })
        }

        emit("forwarded", data)
        close()
    } catch (e: any) {
        notify({
            title: ctrans("Error"),
            text: e?.response?.data?.message ?? ctrans("Failed to forward this conversation"),
            type: "error",
        })
    } finally {
        isSending.value = false
    }
}

watch(
    () => props.isOpen,
    (open) => {
        if (open) {
            people.value = []
            note.value = ""
            alsoEmail.value = false
        }
    }
)
</script>

<template>
    <Modal :isOpen="isOpen" @onClose="close" width="w-full max-w-md">
        <div class="p-5 flex flex-col gap-4">
            <div class="flex items-center gap-2">
                <FontAwesomeIcon :icon="faShare" class="text-teal-600" fixed-width />
                <h3 class="text-base font-semibold text-gray-800">{{ ctrans("Forward to a colleague") }}</h3>
            </div>

            <div class="flex flex-col gap-1.5">
                <span class="text-xs text-gray-500">{{ ctrans("Who should see this") }}</span>
                <StaffTaskCollaborators v-model="people" />
            </div>

            <textarea
                v-model="note"
                rows="3"
                :placeholder="ctrans('Why are you sending it to them?')"
                class="w-full text-sm border border-gray-300 rounded-md px-2 py-1.5 focus:outline-none focus:ring-1 focus:ring-[--app-accent]"
            />

            <!-- They answer inside Aiku either way. The mail is only for whoever does not sit in
                 the inbox all day, which is the whole reason this option exists. -->
            <label class="flex items-start gap-2 text-sm text-gray-700 cursor-pointer">
                <input v-model="alsoEmail" type="checkbox" class="mt-0.5" />
                <span>
                    {{ ctrans("Also send it to their email") }}
                    <span class="block text-xs text-gray-400">
                        {{ ctrans("They still answer the customer in Aiku, not by replying to that mail.") }}
                    </span>
                </span>
            </label>

            <div class="flex justify-end gap-2 pt-2 border-t">
                <Button :label="ctrans('Cancel')" type="cancel" @click="close" />
                <Button
                    :label="isSending ? ctrans('Sending…') : ctrans('Forward')"
                    type="primary"
                    :disabled="people.length === 0 || isSending"
                    @click="confirmForward"
                />
            </div>
        </div>
    </Modal>
</template>
