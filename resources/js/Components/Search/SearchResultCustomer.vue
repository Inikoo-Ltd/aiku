<script setup lang="ts">
import { FontAwesomeIcon } from "@fortawesome/vue-fontawesome"
import { faUser } from "@fal"
import { library } from "@fortawesome/fontawesome-svg-core"
import { Link } from "@inertiajs/vue3"

library.add(faUser)
import { useFormatTime } from "@/Composables/useFormatTime"

import { Customer } from "@/types/customer"

defineProps<{
    data?: Customer
}>()
</script>

<template>
    <Link as="a" href="" class="relative flex gap-x-2">
        <div class="bg-slate-100 h-10 aspect-square rounded-md flex justify-center items-center">
            <FontAwesomeIcon icon="fal fa-user" class="text-gray-400" fixed-width aria-hidden="true" />
        </div>
        <div>
            <h3 class="text-sm font-semibold leading-5">
                {{ data?.contact_name }} <span class="font-normal text-gray-400">#{{ data?.reference }}</span>
            </h3>
            <ul class="mt-0.5 flex flex-col gap-y-0.5 text-xs font-normal leading-4 text-gray-500">
                <li>Created at: {{ useFormatTime(data?.created_at) }}</li>
                <li>Company: {{ data?.company_name || "-" }}</li>
                <li>Clients: {{ data?.number_current_customer_clients || 0 }}</li>
            </ul>
        </div>
    </Link>
</template>