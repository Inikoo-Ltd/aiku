<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faMapMarkerAlt, faTimes, faExclamationCircle } from "@fal"
import Button from "@/Components/Elements/Buttons/Button.vue"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { ctrans } from "@/Composables/useTrans"

library.add(faMapMarkerAlt, faTimes, faExclamationCircle)

interface PacketaPoint {
    id: string
    name: string
    type: string
}

interface PacketaWidget {
    pick: (apiKey: string, callback: (point: { id: string | number } | null) => void, options: Record<string, unknown>) => void
}

const props = defineProps<{
    option: {
        api_key: string | null
        country: string
        selected: PacketaPoint | null
        error: string | null
    }
    orderId: number
}>()

const isSaving = ref(false)
const saveError = ref<string | null>(null)

const loadWidget = (): Promise<PacketaWidget> => new Promise((resolve, reject) => {
    const existing = (window as unknown as { Packeta?: { Widget: PacketaWidget } }).Packeta
    if (existing) {
        return resolve(existing.Widget)
    }
    const script = document.createElement("script")
    script.src = "https://widget.packeta.com/v6/www/js/library.js"
    script.onload = () => {
        const packeta = (window as unknown as { Packeta?: { Widget: PacketaWidget } }).Packeta
        packeta ? resolve(packeta.Widget) : reject()
    }
    script.onerror = () => reject()
    document.head.appendChild(script)
})

const save = (pointId: string | null) => {
    router.patch(
        route("retina.models.order.update_packeta_pickup_point", { order: props.orderId }),
        { pickup_point_id: pointId },
        {
            preserveScroll: true,
            only: ["packeta_pickup_point"],
            onStart: () => {
                isSaving.value = true
                saveError.value = null
            },
            onError: (errors: Record<string, string>) => {
                saveError.value = errors.pickup_point_id ?? errors.messages ?? ctrans("The pickup point could not be saved, please try again.")
            },
            onFinish: () => {
                isSaving.value = false
            },
        }
    )
}

const choosePoint = async () => {
    saveError.value = null
    if (!props.option.api_key) {
        return
    }
    let widget: PacketaWidget
    try {
        widget = await loadWidget()
    } catch {
        saveError.value = ctrans("Packeta's map could not be opened, please try again.")
        return
    }
    widget.pick(props.option.api_key, (point) => {
        if (point) {
            save(String(point.id))
        }
    }, {
        country: props.option.country,
        language: (document.documentElement.lang || "en").slice(0, 2),
        vendors: [{ country: props.option.country }, { country: props.option.country, group: "zbox" }],
    })
}
</script>

<template>
    <div>
        <div class="text-sm text-gray-500">
            <FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" />
            {{ ctrans("Packeta pickup point or Z-BOX").replace("Z-BOX", "Z\u2011BOX") }}
        </div>

        <div v-if="option.selected" class="mt-1 rounded-md border border-gray-300 px-3 py-2 text-sm">
            <div class="font-medium text-gray-700">
                <span class="whitespace-nowrap">{{ option.selected.id }}</span> {{ option.selected.name }}
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                <Button v-if="option.api_key" type="tertiary" size="xs" :label="ctrans('Change')" :disabled="isSaving" @click="choosePoint">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <Button type="tertiary" size="xs" :label="ctrans('Send to the delivery address')" :disabled="isSaving" @click="save(null)">
                    <template #icon>
                        <FontAwesomeIcon icon="fal fa-times" class="text-red-500" fixed-width aria-hidden="true" />
                    </template>
                </Button>
                <LoadingIcon v-if="isSaving" />
            </div>
        </div>

        <div v-else class="mt-1 flex items-center gap-2">
            <Button type="tertiary" :label="ctrans('Choose on the map')" :disabled="isSaving" @click="choosePoint">
                <template #icon>
                    <FontAwesomeIcon icon="fal fa-map-marker-alt" fixed-width aria-hidden="true" />
                </template>
            </Button>
            <LoadingIcon v-if="isSaving" />
        </div>

        <div v-if="saveError || option.error" class="mt-2 flex items-start gap-x-1 text-xs text-red-600">
            <FontAwesomeIcon icon="fal fa-exclamation-circle" class="mt-[3px]" fixed-width aria-hidden="true" />
            <div class="leading-5">{{ saveError || option.error }}</div>
        </div>
        <div v-else-if="!option.selected" class="mt-1 text-xs text-gray-500">
            {{ ctrans("Optional. Without a pickup point the parcel goes to the delivery address.") }}
        </div>
    </div>
</template>
