<!--
  - Author: stewicca <stewicalf@gmail.com>
  - Copyright (c) 2026, Steven Wicca Alfredo
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { route } from "ziggy-js"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { routeType } from "@/types/route"

const props = defineProps<{
    subscription: { is_subscribed: boolean, route: routeType }
}>()

const isSaving = ref(false)

const toggle = () => {
    router.post(route(props.subscription.route.name, props.subscription.route.parameters), {}, {
        preserveScroll: true,
        onStart: () => isSaving.value = true,
        onFinish: () => isSaving.value = false,
    })
}
</script>

<template>
    <Button
        type="tertiary"
        :icon="subscription.is_subscribed ? 'fal fa-check' : 'fal fa-envelope'"
        :label="subscription.is_subscribed ? ctrans('Weekly report: on') : ctrans('Weekly report by email')"
        :loading="isSaving"
        v-tooltip="subscription.is_subscribed ? ctrans('You get the SEO report every Monday. Click to stop.') : ctrans('Get the SEO report by email every Monday: traffic, Google clicks, keywords up and down, site health, backlinks')"
        @click="toggle" />
</template>
