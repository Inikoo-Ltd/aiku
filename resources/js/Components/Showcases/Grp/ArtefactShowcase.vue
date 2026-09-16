<script setup lang="ts">
import { trans } from "laravel-vue-i18n"
import { ref } from "vue"
import axios from "axios"
import { Link, router } from "@inertiajs/vue3"
import { notify } from "@kyvg/vue3-notification"
import Button from "@/Components/Elements/Buttons/Button.vue"
import { ctrans } from "@/Composables/useTrans"

interface ArtefactShowcaseData {
    code: string
    name: string
    state_label: string
    compliance_status: string
    compliance_label: string
    recommended_batch_size: number | null
    batch_pack: { packed_in: number, batch_in_skos: number, suggested_batch_size: number | null } | null
    update_route: { name: string, parameters: any }
    artefact_department: { slug: string, name: string } | null
    tags: string[]
    trade_unit: { id: number, code: string, name: string } | null
    org_stock: {
        id: number
        code: string
        quantity_in_locations: number | string
        route: { name: string, parameters: any } | null
    } | null
    manufacture_tasks: {
        id: number
        code: string
        name: string
        position: number
        units_per_artefact: number | string
        task_work_cost: number | string
    }[]
}

const props = defineProps<{
    data: ArtefactShowcaseData
}>()

const batchSize = ref<number | string>(props.data.recommended_batch_size ?? '')
const isSavingBatchSize = ref(false)
const onSaveBatchSize = async () => {
    if (!batchSize.value || Number(batchSize.value) < 1) return

    try {
        isSavingBatchSize.value = true
        await axios.patch(
            route(props.data.update_route.name, props.data.update_route.parameters),
            { recommended_batch_size: Number(batchSize.value) }
        )
        notify({ title: trans("Updated"), text: trans("Recommended batch size saved"), type: "success" })
        router.reload()
    } catch (error: any) {
        notify({ title: trans("Something went wrong"), text: error.message, type: "error" })
    } finally {
        isSavingBatchSize.value = false
    }
}
</script>

<template>
    <div class="p-4">
        <section class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" aria-labelledby="artefact-showcase-title">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="artefact-showcase-title" class="text-lg font-semibold">{{ data.name }}</h2>
                    <p class="text-sm text-gray-500 mb-2" :aria-label="ctrans('Artefact code: :code', { code: data.code })">{{ data.code }}</p>
                </div>
            </div>
            <span
                class="inline-block text-xs px-2 py-1 rounded border mb-6"
                role="status"
                :aria-label="ctrans('Compliance status: :status', { status: data.compliance_label })"
                :class="{
                'bg-gray-100 text-gray-600 border-gray-200': data.compliance_status === 'not_configured',
                'bg-green-50 text-green-700 border-green-200': data.compliance_status === 'ok',
                'bg-amber-50 text-amber-700 border-amber-200': data.compliance_status === 'expiring',
                'bg-red-50 text-red-700 border-red-200': data.compliance_status === 'problem',
            }">{{ data.compliance_label }}</span>

            <div
                v-if="data.recommended_batch_size"
                class="mb-6 flex items-baseline gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3"
                role="group"
                aria-labelledby="artefact-recommended-batch-size-label">
                <span id="artefact-recommended-batch-size-label" class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Recommended batch size') }}</span>
                <span class="text-2xl font-semibold leading-none" aria-describedby="artefact-recommended-batch-size-label">{{ data.recommended_batch_size }}</span>
                <span v-if="data.batch_pack" class="text-xs text-gray-500">
                    {{ trans('is') }} {{ data.batch_pack.batch_in_skos }} {{ trans('SKOs of') }} {{ data.batch_pack.packed_in }}
                    <template v-if="data.batch_pack.suggested_batch_size">
                        &middot; {{ trans('whole SKOs at') }} {{ data.batch_pack.suggested_batch_size }}
                    </template>
                </span>
            </div>
            <div
                v-else
                class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3"
                role="alert"
                aria-labelledby="artefact-missing-batch-size-title"
                aria-describedby="artefact-missing-batch-size-description">
                <div id="artefact-missing-batch-size-title" class="text-sm font-semibold text-red-700">{{ trans('No recommended batch size') }}</div>
                <div id="artefact-missing-batch-size-description" class="text-xs text-red-600 mb-2">{{ trans('Set how many units are usually made in one go, so job orders can be prefilled.') }}</div>
                <div class="flex items-center gap-2">
                    <input
                        v-model="batchSize"
                        type="number"
                        min="1"
                        name="recommended_batch_size"
                        class="w-32 rounded border border-red-200 px-2 py-1 text-sm"
                        :placeholder="trans('Units')"
                        :aria-label="ctrans('Recommended batch size in units')"
                        aria-describedby="artefact-missing-batch-size-description"
                        aria-required="true"
                        @keyup.enter="onSaveBatchSize" />
                    <Button
                        type="save"
                        :label="trans('Save')"
                        :aria-label="ctrans('Save recommended batch size')"
                        :aria-busy="isSavingBatchSize"
                        :loading="isSavingBatchSize"
                        :disabled="!batchSize"
                        @click="onSaveBatchSize" />
                </div>
            </div>

            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4" :aria-label="ctrans('Artefact details')">
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('State') }}</dt>
                    <dd class="text-sm">{{ data.state_label }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Family') }}</dt>
                    <dd class="text-sm">{{ data.artefact_department?.name || '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Tags') }}</dt>
                    <dd class="text-sm">
                        <ul v-if="data.tags?.length" class="flex flex-wrap gap-1" :aria-label="ctrans('Tags')">
                            <li v-for="tag in data.tags" :key="tag" class="px-1.5 py-0.5 rounded bg-gray-100 text-xs">#{{ tag }}</li>
                        </ul>
                        <span v-else>-</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Trade unit') }}</dt>
                    <dd class="text-sm">{{ data.trade_unit ? `${data.trade_unit.code} - ${data.trade_unit.name}` : '-' }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Stock (SKU)') }}</dt>
                    <dd class="text-sm">
                        <Link
                            v-if="data.org_stock?.route"
                            :href="route(data.org_stock.route.name, data.org_stock.route.parameters)"
                            class="primaryLink"
                            :aria-label="ctrans('Open stock :code in the warehouse', { code: data.org_stock.code })">
                            {{ data.org_stock.code }}
                        </Link>
                        <span v-else>{{ data.org_stock ? data.org_stock.code : '-' }}</span>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-gray-500 uppercase tracking-wide">{{ trans('Quantity in locations') }}</dt>
                    <dd class="text-sm">{{ data.org_stock ? data.org_stock.quantity_in_locations : '-' }}</dd>
                </div>
            </dl>

            <section class="mt-8" v-if="data.manufacture_tasks.length" aria-labelledby="artefact-recipe-steps-title">
                <h3 id="artefact-recipe-steps-title" class="text-sm font-semibold mb-3">{{ trans('Recipe steps') }}</h3>
                <table class="w-full text-sm" aria-labelledby="artefact-recipe-steps-title">
                    <thead>
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th scope="col" class="pb-2">{{ trans('Position') }}</th>
                            <th scope="col" class="pb-2">{{ trans('Task') }}</th>
                            <th scope="col" class="pb-2">{{ trans('Units per artefact') }}</th>
                            <th scope="col" class="pb-2">{{ trans('Work cost') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="task in data.manufacture_tasks" :key="task.id" class="border-t border-gray-100">
                            <td class="py-2">{{ task.position }}</td>
                            <td class="py-2">{{ task.code }} - {{ task.name }}</td>
                            <td class="py-2">{{ task.units_per_artefact }}</td>
                            <td class="py-2">{{ task.task_work_cost }}</td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </section>
    </div>
</template>
