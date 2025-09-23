<script setup lang="ts">
import { Link, router } from "@inertiajs/vue3"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { Action } from "@/types/Action"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faPencil, faTrashAlt, faFileInvoice, faPlus } from "@fal"
import { ref } from "vue"
import { notify } from "@kyvg/vue3-notification"
import { trans } from "laravel-vue-i18n"

library.add( faPencil, faTrashAlt, faFileInvoice, faPlus )

const props = defineProps<{
    action: Action
    dataToSubmit?: any
}>()

const isLoading = ref(false)

const handleClick = (action: Action) => {
    const href = action.route?.name ? route(action.route?.name, action.route?.parameters) : action.route?.name ? route(action.route?.name, action.route?.parameters) : '#'
    const method = action.route?.method || 'get'
    const data = action.route?.method !== 'get' ? action.route?.body: props.dataToSubmit


    router[method](
        href,
        data,
        {
            onBefore: () => {
                isLoading.value = true
            },
            onSuccess: () => {
                null
            },
            onFinish: () => {
                if(action.fullLoading) return
                isLoading.value = false
            },
            onError: (error: {} | string) => {
                if(action.fullLoading) {
                    isLoading.value = false
                }
                notify({
                    title: trans('Something went wrong.'),
                    text: typeof error === 'string' ? error : Object.values(error || {}).join(', '),
                    type: 'error',
                })
            }
        })
};

</script>

<template>
    <!-- Button Group -->
    <div v-if="action.type === 'buttonGroup' && action.buttonGroup?.length"
        class="first:rounded-l last:rounded-r overflow-hidden ring-1 ring-gray-300 flex">
        <slot v-for="(button, index) in action.buttonGroup" :name="'button' + index">
            <Link
                :href="button.route?.name ? route(button.route?.name, button.route?.parameters) : action.route?.name ? route(action.route?.name, action.route?.parameters) : '#'"
                class=""
                :method="button.route?.method || 'get'"
                as="a"
                :target="button.target"
            >
                <Button
                    :style="button.style"
                    :label="button.label"
                    :size="button.size"
                    :icon="button.icon"
                    :disabled="button.disabled"
                    :iconRight="button.iconRight"
                    :key="`ActionButton${button.label}${button.style}`"
                    :tooltip="button.tooltip"
                    class="rounded-none text-sm border-none focus:ring-transparent focus:ring-offset-transparent focus:ring-0">
                </Button>
            </Link>
        </slot>
    </div>

    <!-- Button -->
    <template v-else-if="action.route">
        <!-- Button: to download PDF (open in new tab) -->
        <a v-if="action.target" :href="route(action.route?.name, action.route?.parameters)" :target="action.target">
            <Button
                :style="action.style"
                :label="action.label"
                :icon="action.icon"
                :iconRight="action.iconRight"
                :key="`ActionButton${action?.key}${action.style}`"
                :tooltip="action.tooltip"
                :loading="isLoading"
            />
        </a>

        <Button v-else
            @click="handleClick(action)"
            :style="action.style"
            :type="action.type"
            :label="action.label"
            :icon="action.icon"
            :size="action.size"
            :disabled="action.disabled"
            :iconRight="action.iconRight"
            :key="`ActionButton${action?.key}${action.style}`"
            :tooltip="action.tooltip"
            :loading="isLoading"
            :full="action.full"
            :injectStyle="action.class"
            class="text-nowrap"
        />
    </template>

    <Button v-else
        v-bind="action"
        :key="`ActionButton${action?.key}${action.style}`"
        :loading="isLoading"
    />


</template>
