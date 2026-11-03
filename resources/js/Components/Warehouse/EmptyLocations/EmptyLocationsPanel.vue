<!--
  -  Author: Raul Perusquia <raul@inikoo.com>
  -  Created: Mon, 02 Nov 2026, Kuala Lumpur, Malaysia
  -  Copyright (c) 2026, Raul A Perusquia Flores
  -->

<script setup lang="ts">
import { ref } from "vue"
import { Link, router } from "@inertiajs/vue3"
import ToggleSwitch from "primevue/toggleswitch"
import { ctrans } from "@/Composables/useTrans"
import { useFormatTime } from "@/Composables/useFormatTime"
import LoadingIcon from "@/Components/Utils/LoadingIcon.vue"
import { routeType } from "@/types/route"
import { RouteParams } from "@/types/route-params"

const props = defineProps<{
    data: {
        enabled: boolean
        can_edit: boolean
        update_route: routeType
        last_change: { by: string | null, at: string } | null
        kept_locations: { slug: string, code: string }[]
        to_remove: number
        max_per_run: number
        removed_last_7_days: number
    }
}>()

const routeParams = route().params as RouteParams
const enabled = ref(props.data.enabled)
const saving = ref(false)
const error = ref<string | null>(null)

function save(value: boolean) {
    error.value = null
    saving.value = true
    router.patch(
        route(props.data.update_route.name, props.data.update_route.parameters),
        { auto_disassociate_empty_locations: value },
        {
            preserveScroll: true,
            onSuccess: () => enabled.value = value,
            onError: (errors) => {
                enabled.value = !value
                error.value = Object.values(errors)[0] ?? ctrans("Could not save")
            },
            onFinish: () => saving.value = false,
        }
    )
}

function locationRoute(slug: string) {
    return route("grp.org.warehouses.show.infrastructure.locations.show", [routeParams.organisation, routeParams.warehouse, slug])
}
</script>

<template>
    <div class="px-4 py-4 grid grid-cols-1 gap-4 lg:grid-cols-3 border-b border-gray-200">
        <div class="lg:col-span-2 space-y-2">
            <div class="flex items-center gap-3">
                <ToggleSwitch :modelValue="enabled" :disabled="!data.can_edit || saving" @update:modelValue="save" />
                <span class="font-semibold">{{ ctrans("Remove empty locations automatically") }}</span>
                <LoadingIcon v-if="saving" />
            </div>
            <p v-if="error" class="text-xs text-red-600">{{ error }}</p>
            <p class="text-sm text-gray-500">
                {{ ctrans("Every hour, an SKO that has run out in a location loses that location, recorded in its stock history. Active picking locations (trolley or basket), the SKO's current pick location, SKOs with goods waiting to be placed and anything moved in the last hour are kept.") }}
            </p>
            <p v-if="data.last_change" class="text-xs text-gray-500">
                {{ ctrans(enabled ? "Turned on by :user, :date" : "Turned off by :user, :date", { user: data.last_change.by ?? "-", date: useFormatTime(new Date(data.last_change.at), { formatTime: "short-datetime" }) }) }}
            </p>
        </div>

        <dl class="grid grid-cols-2 gap-3 text-sm">
            <div class="rounded border border-gray-200 px-3 py-2">
                <dt class="text-gray-500 whitespace-nowrap">{{ ctrans("To remove now") }}</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ data.to_remove }}</dd>
                <dd v-if="data.to_remove > data.max_per_run" class="text-xs text-gray-500">{{ ctrans(":count per hour", { count: data.max_per_run }) }}</dd>
            </div>
            <div class="rounded border border-gray-200 px-3 py-2">
                <dt class="text-gray-500 whitespace-nowrap">{{ ctrans("Removed, last 7 days") }}</dt>
                <dd class="text-lg font-semibold tabular-nums">{{ data.removed_last_7_days }}</dd>
            </div>
        </dl>

        <div class="lg:col-span-3 text-sm">
            <span class="text-gray-500">{{ ctrans("Locations that never lose empty SKOs") }}:</span>
            <template v-if="data.kept_locations.length">
                <Link v-for="location in data.kept_locations" :key="location.slug" :href="locationRoute(location.slug)"
                    class="primaryLink whitespace-nowrap ml-2">{{ location.code }}</Link>
            </template>
            <span v-else class="ml-2 text-gray-400">{{ ctrans("none, set it on the location page") }}</span>
        </div>
    </div>
</template>
