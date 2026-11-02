<!--
  - Author Louis Perez
  - Created on 17-10-2026-20h-40m
  - GitHub: https://github.com/louis-perez
  - Copyright 2026
  -->

<script setup lang="ts">
import { ref } from "vue"
import { router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import { routeType } from "@/types/route"
import { productDevelopmentColor } from "@/Components/Goods/ProductDevelopment/productDevelopmentColors"
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { library } from "@fortawesome/fontawesome-svg-core"
import { faSignature, faLock, faCheckCircle, faCircle } from "@fal"
library.add(faSignature, faLock, faCheckCircle, faCircle)

const props = defineProps<{
    data: {
        open: boolean
        locked_at: string | null
        route: routeType
        rows: { role: string, label: string, icon: string, color: string, signed_by: string | null, signed_at: string | null, as_owner: boolean, nobody_holds: boolean, can_sign: boolean }[]
    }
}>()

const busy = ref<string | null>(null)

function send(role: string, operation: "sign" | "withdraw") {
    router.patch(route(props.data.route.name, props.data.route.parameters), { role, operation }, {
        preserveScroll: true,
        onStart: () => busy.value = role,
        onFinish: () => busy.value = null,
        onError: (errors) => notify({ title: ctrans("Not signed off"), text: Object.values(errors).join(" "), type: "error" }),
    })
}

const formatWhen = (date: string | null) => date ? useFormatTime(date, { formatTime: "d MMM yyyy, HH:mm" }) : ""
</script>

<template>
    <section id="pd-final-approval" class="rounded-lg border border-gray-200 bg-white">
        <header class="flex items-center gap-2 border-b border-gray-100 px-4 py-2.5">
            <FontAwesomeIcon icon="fal fa-signature" class="text-gray-400" fixed-width aria-hidden="true" />
            <h3 class="flex-1 text-sm font-semibold text-gray-800">{{ ctrans("Final approval") }}</h3>
            <span v-if="data.locked_at" class="flex items-center gap-1 rounded bg-green-50 px-1.5 py-0.5 text-xs text-green-700">
                <FontAwesomeIcon icon="fal fa-lock" fixed-width aria-hidden="true" />{{ ctrans("Artwork locked") }}
            </span>
        </header>
        <ul class="divide-y divide-gray-100">
            <li v-for="row in data.rows" :key="row.role" class="flex items-center gap-3 px-4 py-3 text-sm">
                <FontAwesomeIcon :icon="row.signed_by ? 'fal fa-check-circle' : 'fal fa-circle'" :class="row.signed_by ? 'text-green-600' : 'text-amber-500'" fixed-width aria-hidden="true" />
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-1.5 text-gray-800">
                        <FontAwesomeIcon :icon="row.icon" :class="productDevelopmentColor(row.color).icon" fixed-width aria-hidden="true" />
                        {{ row.label }}
                    </div>
                    <div class="text-xs text-gray-500">
                        <template v-if="row.signed_by">{{ ctrans("Signed off by :user, :date", { user: row.signed_by, date: formatWhen(row.signed_at) }) }}<template v-if="row.as_owner"> · {{ ctrans("as owner") }}</template></template>
                        <template v-else-if="row.nobody_holds">{{ ctrans("Nobody has this role: the owner signs it off") }}</template>
                        <template v-else>{{ ctrans("Waiting for sign-off") }}</template>
                    </div>
                </div>
                <template v-if="row.can_sign">
                    <Button v-if="!row.signed_by" type="primary" size="xs" icon="fal fa-signature" :label="ctrans('Sign off')" :loading="busy === row.role" @click="send(row.role, 'sign')" />
                    <Button v-else type="tertiary" size="xs" :label="ctrans('Withdraw')" :loading="busy === row.role" @click="send(row.role, 'withdraw')" />
                </template>
            </li>
        </ul>
        <p v-if="data.open" class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500">{{ ctrans("Once the buyer and compliance have both signed off, the artwork is locked and the development can go to the supplier.") }}</p>
    </section>
</template>
