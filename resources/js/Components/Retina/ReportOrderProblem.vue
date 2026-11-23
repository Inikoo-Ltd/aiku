<script setup lang="ts">
import { ref } from "vue"
import Dialog from "primevue/dialog"
import Button from "@/Components/Elements/Buttons/Button.vue"
import CustomerClaimForm from "@/Components/Chat/Customer/CustomerClaimForm.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faBoxOpen } from "@fal"

library.add(faBoxOpen)

const props = defineProps<{ claim: { order_id: number, open: boolean } }>()

const visible = ref(props.claim.open)
</script>

<template>
    <div>
        <Button type="tertiary" icon="fal fa-box-open" :label="ctrans('Report a problem')" @click="visible = true" />
        <Dialog v-model:visible="visible" modal :header="ctrans('Report a problem with this order')" :style="{ width: '36rem', maxWidth: 'calc(100vw - 2rem)' }" :draggable="false">
            <CustomerClaimForm :order-id="props.claim.order_id" :source="props.claim.open ? 'email' : 'account'" />
        </Dialog>
    </div>
</template>
