<script setup lang="ts">
import { ref, watch } from "vue"
import axios from "axios"
import Dialog from "primevue/dialog"
import Select from "primevue/select"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faCheck, faTimes } from "@fal"

library.add(faCheck, faTimes)

interface TargetShop {
    id: number
    code: string
    name: string
}

const visible = defineModel<boolean>("visible", { required: true })

const props = defineProps<{
    organisation: string
    sessionUlid?: string | null
    isEmail?: boolean
}>()

const emit = defineEmits<{
    (e: "moved", data: any): void
}>()

const shops = ref<TargetShop[]>([])
const shopId = ref<number | null>(null)
const isLoading = ref(false)
const isMoving = ref(false)
const error = ref("")

const loadShops = async () => {
    if (!props.sessionUlid) return

    isLoading.value = true
    try {
        const { data } = await axios.get(route("grp.org.chat.agents.sessions.move_to_shop.shops", [props.organisation, props.sessionUlid]))
        shops.value = data?.data ?? []
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? ctrans("Could not load the shops")
    } finally {
        isLoading.value = false
    }
}

const move = async () => {
    if (!shopId.value || isMoving.value || !props.sessionUlid) return

    isMoving.value = true
    error.value = ""
    try {
        const { data } = await axios.patch(route("grp.org.chat.agents.sessions.move_to_shop", [props.organisation, props.sessionUlid]), {
            shop_id: shopId.value,
        })
        emit("moved", data)
        visible.value = false
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? ctrans("Failed to move this conversation")
    } finally {
        isMoving.value = false
    }
}

watch(visible, (isVisible) => {
    if (isVisible) {
        shopId.value = null
        error.value = ""
        loadShops()
    }
})
</script>

<template>
    <Dialog v-model:visible="visible" modal :header="ctrans('Move to another shop')" :style="{ width: '28rem' }" :breakpoints="{ '640px': '95vw' }">
        <div class="flex flex-col gap-3">
            <p class="text-xs leading-snug text-gray-500">
                {{ ctrans("The conversation goes to the waiting queue of the shop you pick, and nobody holds it any more.") }}
            </p>
            <p v-if="isEmail" class="text-xs leading-snug text-gray-500">
                {{ ctrans("Replies still go from the mailbox the customer wrote to, in the same email thread.") }}
            </p>

            <Select v-model="shopId" :options="shops" option-label="name" option-value="id" :loading="isLoading" filter
                :placeholder="ctrans('Choose a shop')" :empty-message="ctrans('No other shop you can move it to')" fluid>
                <template #option="{ option }">
                    <span class="whitespace-nowrap font-mono text-xs text-gray-500">{{ option.code }}</span>
                    <span class="ml-2">{{ option.name }}</span>
                </template>
            </Select>

            <p v-if="error" class="text-xs leading-snug text-red-500">{{ error }}</p>

            <div class="flex items-center justify-end gap-2 pt-1">
                <Button type="tertiary" :label="ctrans('Cancel')" @click="visible = false">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" :label="ctrans('Move')" :loading="isMoving" :disabled="!shopId" @click="move">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-check" class="text-green-600" fixed-width aria-hidden="true" />
                    </template>
                </Button>
            </div>
        </div>
    </Dialog>
</template>
