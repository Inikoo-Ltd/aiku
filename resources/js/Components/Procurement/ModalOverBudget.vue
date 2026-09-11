<!--
  - Author: Raul Perusquia <raul@inikoo.com>
  - Created: Fri, 11 Sep 2026 Malaga, Spain
  - Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import Modal from "@/Components/Utils/Modal.vue"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { library } from "@fortawesome/fontawesome-svg-core"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faExclamationTriangle } from "@fas"
import { trans } from "laravel-vue-i18n"

library.add(faExclamationTriangle)

defineProps<{
    message: string | null
    removeHref?: string
}>()

const emit = defineEmits<{
    (e: "back"): void
    (e: "confirm"): void
}>()
</script>

<template>
    <Modal :isOpen="!!message" @onClose="emit('back')" width="w-full max-w-lg" :zIndex="40">
        <div class="rounded-xl border-2 border-red-500 bg-red-50 p-5">
            <div class="flex items-start gap-3">
                <FontAwesomeIcon icon="fas fa-exclamation-triangle" class="mt-0.5 text-2xl text-red-600" fixed-width aria-hidden="true" />
                <div>
                    <h2 class="text-lg font-semibold text-red-800">{{ trans("Over the recommended budget, are you sure?") }}</h2>
                    <p class="mt-2 text-sm text-red-900">{{ message }}</p>
                    <p class="mt-2 text-sm text-red-900">{{ trans("This is a recommendation only. You are in charge.") }}</p>
                </div>
            </div>
            <div class="mt-5 flex flex-wrap justify-end gap-2">
                <Button type="tertiary" :label="trans('Go back')" @click="emit('back')" />
                <a v-if="removeHref" :href="removeHref">
                    <Button type="secondary" icon="fal fa-list" :label="trans('Remove some items')" />
                </a>
                <Button type="negative" :label="trans(`I don't care, add it`)" @click="emit('confirm')" />
            </div>
        </div>
    </Modal>
</template>
