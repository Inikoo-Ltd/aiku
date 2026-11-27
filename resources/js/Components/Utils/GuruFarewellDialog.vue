<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Sun, 15 Nov 2026, Kuala Lumpur, Malaysia
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import axios from "axios"
import { onMounted, ref } from "vue"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck } from "@fal"

const open = ref(false)

onMounted(async () => {
    try {
        const { data } = await axios.get(route("grp.tasks.guru_farewell"))
        open.value = !!data.farewell
    } catch {
        // shortcut: a failed read only delays the farewell to the next page load
    }
})

const close = () => {
    open.value = false
    axios.post(route("grp.tasks.guru_farewell.seen")).catch(() => {})
}
</script>

<template>
    <Dialog
        :visible="open"
        modal
        :closable="false"
        :header="ctrans('Merlin will no longer need you')"
        :style="{ width: '34rem' }"
        :breakpoints="{ '640px': '95vw' }">
        <div class="space-y-3 text-sm text-gray-700">
            <p>{{ ctrans("From today, Merlin no longer needs gurus. Questions it cannot answer go straight to customer services, and Merlin learns from their replies or finds other ways to get the knowledge and facts it needs.") }}</p>
            <p>{{ ctrans("Since 20 November, Merlin sent you 166 customer questions and only 20% were answered. The other customers waited a day, and most were then told \"Our team could not answer this question\".") }}</p>
            <p class="font-medium text-gray-900">{{ ctrans("Nothing more is needed from you.") }}</p>
        </div>
        <template #footer>
            <Button type="primary" full :label="ctrans('OK')" @click="close">
                <template #icon>
                    <FontAwesomeIcon :icon="faCheck" fixed-width aria-hidden="true" />
                </template>
            </Button>
        </template>
    </Dialog>
</template>
