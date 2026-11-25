<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Wed, 25 Nov 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { router } from "@inertiajs/vue3"
import { ref } from "vue"
import Dialog from "primevue/dialog"
import Textarea from "primevue/textarea"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faBan, faTimes } from "@fal"
import { ctrans } from "@/Composables/useTrans"

const props = defineProps<{
    id: number
    stockCode: string
}>()

const visible = ref(false)
const reason = ref("")
const error = ref<string | null>(null)
const isSubmitting = ref(false)

function open() {
    reason.value = ""
    error.value = null
    visible.value = true
}

function submit() {
    router.post(
        route("grp.org.productions.show.to_produce.items.cant_be_done", [route().params["organisation"], route().params["production"]]),
        { ids: [props.id], reason: reason.value.trim() },
        {
            preserveScroll: true,
            onStart: () => { isSubmitting.value = true },
            onFinish: () => { isSubmitting.value = false },
            onSuccess: () => { visible.value = false },
            onError: (errors) => { error.value = Object.values(errors)[0] ?? ctrans("Something went wrong") },
        }
    )
}
</script>

<template>
    <span>
        <Button type="tertiary" size="xs" :label="ctrans(`Can't be done`)" @click="open">
            <template #icon>
                <FontAwesomeIcon :icon="faBan" class="text-amber-500" fixed-width aria-hidden="true" />
            </template>
        </Button>
        <Dialog v-model:visible="visible" modal dismissableMask :header="`${stockCode} · ${ctrans(`Can't be done`)}`" :style="{ width: '26rem' }">
            <div class="flex flex-col gap-2 text-sm">
                <label class="flex flex-col gap-1">
                    <span class="text-gray-500">{{ ctrans("Why can't it be done? The buyer is told.") }} <span class="text-red-500">*</span></span>
                    <Textarea v-model="reason" required maxlength="1000" rows="3" autofocus class="w-full text-sm" />
                </label>
                <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
                <div class="flex w-full gap-2">
                    <Button type="tertiary" class="shrink-0" :label="ctrans('Cancel')" @click="visible = false">
                        <template #icon>
                            <FontAwesomeIcon :icon="faTimes" class="text-red-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                    <Button type="tertiary" full :label="ctrans(`Can't be done`)" :loading="isSubmitting" :disabled="!reason.trim()" @click="submit">
                        <template #icon>
                            <FontAwesomeIcon :icon="faBan" class="text-amber-500" fixed-width aria-hidden="true" />
                        </template>
                    </Button>
                </div>
            </div>
        </Dialog>
    </span>
</template>
