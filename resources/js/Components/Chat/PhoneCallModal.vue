<script setup lang="ts">
import Dialog from "primevue/dialog"
import PhoneCallForm from "@/Components/Chat/PhoneCallForm.vue"
import { ctrans } from "@/Composables/useTrans"
import { useChatPhoneCall } from "@/Composables/useChatPhoneCall"

const { modalOpen, closeModal } = useChatPhoneCall()
</script>

<template>
    <Dialog v-model:visible="modalOpen" modal :header="ctrans('Phone call')"
        :style="{ width: '92vw', maxWidth: '520px' }" :breakpoints="{ '640px': '95vw' }">
        <PhoneCallForm :open="modalOpen" @close="closeModal" />
    </Dialog>
</template>
