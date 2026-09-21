<script setup lang="ts">
import { computed, ref, watch } from "vue"
import axios from "axios"
import ConfirmPopup from "primevue/confirmpopup"
import { useConfirm } from "primevue/useconfirm"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faPhone, faPhoneSlash, faTrash } from "@fas"
import PureMultiselect from "@/Components/Pure/PureMultiselect.vue"
import PureMultiselectInfiniteScroll from "@/Components/Pure/PureMultiselectInfiniteScroll.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"
import { useChatPhoneCall } from "@/Composables/useChatPhoneCall"
import { notify } from "@kyvg/vue3-notification"

// The same call, reached either from the inbox as a modal or from the floating pill as a popup,
// so the two are one component rather than two that drift apart.
const props = defineProps<{
    open: boolean
    compact?: boolean
}>()

const emits = defineEmits<{
    (e: "close"): void
}>()

const {
    state,
    busy,
    formattedElapsed,
    minutesLeft,
    isRunningOut,
    preferredShopId,
    start,
    end,
    cancel,
} = useChatPhoneCall()

const confirm = useConfirm()

const shopId = ref<number | null>(null)
const contactType = ref<"customer" | "guest">("customer")
const customerId = ref<number | null>(null)
const guestSessionId = ref<number | null>(null)
const guests = ref<any[]>([])
const guestsLoading = ref(false)
const contactName = ref("")
const notes = ref("")
const error = ref("")

const activeCall = computed(() => state.call)

const shopOptions = computed(() =>
    state.shops.map((shop) => ({ label: shop.name, value: shop.id }))
)

const guestOptions = computed(() =>
    guests.value.map((guest) => ({
        label: guest.email ? `${guest.name} (${guest.email})` : guest.name,
        value: guest.id,
    }))
)

const customerFetchRoute = computed(() => ({
    name: "grp.chat.phone_calls.customers",
    parameters: { shop: shopId.value },
}))

const canSubmit = computed(() => {
    if (busy.value || notes.value.trim() === "") {
        return false
    }

    return contactType.value === "customer" ? !!customerId.value : true
})

const loadGuests = async () => {
    guestsLoading.value = true

    try {
        const { data } = await axios.get(route("grp.chat.phone_calls.guests"), {
            params: { shop_id: shopId.value },
        })
        guests.value = data?.data ?? []
    } catch (e) {
        guests.value = []
    } finally {
        guestsLoading.value = false
    }
}

const onStart = async () => {
    error.value = ""

    try {
        await start(shopId.value ?? preferredShopId.value ?? null)
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? ctrans("The phone call could not be started.")
    }
}

const onEnd = async () => {
    error.value = ""

    try {
        await end({
            notes: notes.value.trim(),
            contact_type: contactType.value,
            customer_id: contactType.value === "customer" ? customerId.value : null,
            chat_session_id: contactType.value === "guest" ? guestSessionId.value : null,
            contact_name: contactName.value.trim() || null,
            shop_id: shopId.value,
        })

        notify({
            title: ctrans("Phone call saved"),
            text: ctrans("The notes are on the conversation and in the phone calls list."),
            type: "success",
        })

        emits("close")
    } catch (e: any) {
        error.value =
            e?.response?.data?.message ?? ctrans("The phone call could not be saved.")
    }
}

const onCancel = (event: Event) => {
    confirm.require({
        target: event.currentTarget as HTMLElement,
        message: ctrans("Throw this call away? The time spent on it is not kept and nothing is written down."),
        icon: "fal fa-exclamation-triangle",
        acceptLabel: ctrans("Throw it away"),
        rejectLabel: ctrans("Keep the call"),
        acceptClass: "p-button-danger",
        accept: async () => {
            await cancel()
            emits("close")
        },
    })
}

const resetForm = () => {
    contactType.value = "customer"
    customerId.value = null
    guestSessionId.value = null
    contactName.value = ""
    notes.value = ""
    error.value = ""
}

watch(
    () => props.open,
    (isOpen) => {
        if (!isOpen) {
            return
        }

        shopId.value = activeCall.value?.shop_id ?? preferredShopId.value ?? null

        if (!activeCall.value) {
            resetForm()
        }
    },
    { immediate: true }
)

watch(
    () => [props.open, contactType.value, shopId.value],
    () => {
        if (props.open && contactType.value === "guest") {
            loadGuests()
        }
    }
)

watch(shopId, () => {
    customerId.value = null
    guestSessionId.value = null
})

watch(contactType, () => {
    error.value = ""
})
</script>

<template>
    <div>
        <ConfirmPopup />

        <!-- Nothing running: one button, because pressing it is the only thing to do before
             the telephone is actually answered. -->
        <div v-if="!activeCall" class="flex flex-col gap-4 py-2">
            <p class="text-sm text-gray-500 leading-snug">
                {{ ctrans("Start the timer when you pick up the phone. Your colleagues will see you are on a call, and no new conversation is handed to you until it ends.") }}
            </p>

            <div v-if="shopOptions.length > 1" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Shop") }}</label>
                <PureMultiselect v-model="shopId" :options="shopOptions"
                    :placeholder="ctrans('Which shop is this call for?')" />
            </div>

            <button type="button" :disabled="busy" @click="onStart"
                class="w-full flex items-center justify-center gap-3 rounded-xl px-4 text-base font-semibold text-white transition-colors"
                :class="[
                    compact ? 'py-3.5' : 'py-5',
                    busy ? 'bg-gray-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700',
                ]">
                <LoadingIcon v-if="busy" />
                <FontAwesomeIcon v-else :icon="faPhone" />
                {{ ctrans("Start Phone Call") }}
            </button>

            <p v-if="error" class="text-xs text-red-500 leading-snug">{{ error }}</p>
        </div>

        <!-- Running: the timer, who it is with, and what came out of it. -->
        <div v-else class="flex flex-col gap-4 py-1">
            <div class="flex items-center justify-between rounded-xl px-4 py-3"
                :class="isRunningOut ? 'bg-amber-50 border border-amber-200' : 'bg-emerald-50 border border-emerald-200'">
                <div class="flex items-center gap-3">
                    <span class="relative flex h-3 w-3">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full opacity-75"
                            :class="isRunningOut ? 'bg-amber-400' : 'bg-emerald-400'" />
                        <span class="relative inline-flex h-3 w-3 rounded-full"
                            :class="isRunningOut ? 'bg-amber-500' : 'bg-emerald-500'" />
                    </span>
                    <div class="leading-tight">
                        <div class="text-sm font-medium" :class="isRunningOut ? 'text-amber-800' : 'text-emerald-800'">
                            {{ ctrans("On the phone") }}
                        </div>
                        <div v-if="isRunningOut" class="text-[11px] text-amber-700">
                            {{ ctrans("Closes automatically in :minutes min", { minutes: String(Math.max(0, minutesLeft)) }) }}
                        </div>
                    </div>
                </div>
                <div class="font-semibold tabular-nums" :class="[
                    compact ? 'text-xl' : 'text-2xl',
                    isRunningOut ? 'text-amber-800' : 'text-emerald-800',
                ]">
                    {{ formattedElapsed }}
                </div>
            </div>

            <div v-if="shopOptions.length > 1" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Shop") }}</label>
                <PureMultiselect v-model="shopId" :options="shopOptions"
                    :placeholder="ctrans('Which shop is this call for?')" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Who did you speak to?") }}</label>
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="option in [
                        { value: 'customer', label: ctrans('Customer') },
                        { value: 'guest', label: ctrans('Guest') },
                    ]" :key="option.value" type="button" @click="contactType = option.value as 'customer' | 'guest'"
                        class="rounded-lg border px-3 py-2 text-sm transition-colors"
                        :class="contactType === option.value
                            ? 'border-emerald-500 bg-emerald-50 text-emerald-700 font-medium'
                            : 'border-gray-300 text-gray-600 hover:bg-gray-50'">
                        {{ option.label }}
                    </button>
                </div>
            </div>

            <div v-if="contactType === 'customer'" class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">{{ ctrans("Customer") }}</label>
                <p v-if="!shopId" class="text-xs text-gray-400">
                    {{ ctrans("Choose a shop first to search its customers.") }}
                </p>
                <PureMultiselectInfiniteScroll v-else :key="shopId" v-model="customerId"
                    :fetchRoute="customerFetchRoute" valueProp="id" labelProp="name"
                    labelAdditionalProp="reference" :placeholder="ctrans('Search a customer')"
                    :noOptionsText="ctrans('No customer found')">
                    <template #option="{ option }">
                        <div>
                            {{ option.name }}
                            <span class="text-sm text-gray-400">
                                ({{ option.reference }}<template v-if="option.phone"> · {{ option.phone }}</template>)
                            </span>
                        </div>
                    </template>
                </PureMultiselectInfiniteScroll>
            </div>

            <div v-else class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">
                    {{ ctrans("Guest") }}
                    <span class="font-normal text-gray-400">{{ ctrans("(optional)") }}</span>
                </label>
                <PureMultiselect v-model="guestSessionId" :options="guestOptions" :isLoading="guestsLoading"
                    searchable :required="false"
                    :placeholder="ctrans('Pick a guest who has written in, or leave this empty')" />
                <input v-model="contactName" type="text"
                    :placeholder="ctrans('Or type a name yourself')"
                    class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-1.5 text-sm text-gray-700" />
            </div>

            <div class="flex flex-col gap-1">
                <label class="text-xs font-medium text-gray-600">
                    {{ ctrans("What came out of the call?") }}
                    <span class="text-red-500">*</span>
                </label>
                <textarea v-model="notes" :rows="compact ? 3 : 4"
                    :placeholder="ctrans('What was asked, what you agreed, what happens next')"
                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-700" />
                <p class="text-[11px] text-gray-400">
                    {{ ctrans("The call cannot be filed without this: it is what the next agent reads.") }}
                </p>
            </div>

            <p v-if="error" class="text-xs text-red-500 leading-snug">{{ error }}</p>

            <div class="flex items-center justify-between gap-2 pt-1">
                <button type="button" :disabled="busy" @click="onCancel"
                    class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">
                    <FontAwesomeIcon :icon="faTrash" class="text-xs" />
                    {{ ctrans("Cancel call") }}
                </button>

                <div class="flex items-center gap-2">
                    <button type="button" class="rounded-lg px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-100"
                        @click="emits('close')">
                        {{ ctrans("Keep talking") }}
                    </button>
                    <button type="button" :disabled="!canSubmit" @click="onEnd"
                        v-tooltip="!canSubmit && !notes.trim() ? ctrans('Write what came out of the call first') : undefined"
                        class="flex items-center gap-2 rounded-lg px-3 py-1.5 text-sm text-white transition-colors"
                        :class="!canSubmit ? 'bg-gray-300 cursor-not-allowed' : 'bg-emerald-600 hover:bg-emerald-700'">
                        <LoadingIcon v-if="busy" />
                        <FontAwesomeIcon v-else :icon="faPhoneSlash" class="text-xs" />
                        {{ ctrans("End call") }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
