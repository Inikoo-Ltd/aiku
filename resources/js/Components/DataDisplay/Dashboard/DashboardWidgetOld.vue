<script setup lang="ts">
import { getComponentWidget } from "@/Composables/Listing/DashboardWidgetsList"

const props = defineProps<{
	widgetsData: {
		column_count?: number
		components: {
			type: string // 'basic'
			col_span?: number
			row_span?: number
			visual?: {}
			data: {}
		}[]
	}
}>()


</script>

<template>
	<div
		class="grid-container"
		:style="{
			'grid-template-columns': `repeat(${widgetsData.column_count || 1}, minmax(0, 1fr))`,
		}">
		<template
            v-for="(component, index) in props.widgetsData?.components"
            :key="'widget' + index"
        >
            <component
                :is="getComponentWidget(component.type)"
                :widget="component.data"
                :visual="component.visual"
                :style="{
                    'grid-column': `span ${component.col_span || 1} / span ${component.col_span || 1}`,
                    'grid-row': `span ${component.row_span || 1} / span ${component.row_span || 1}`,
                }"
            />
        </template>
	</div>

	<!-- <div class="col-span-12">
        <div class="grid grid-cols-2 w-full gap-3" :style="{
        'grid-template-columns': `repeat(${widgetsData.column_count || 1}, minmax(0, 1fr))`
    }">
        <component
            v-for="(component, index) in props.widgetsData.components"
            :is="getComponentWidget(component.type)"
            :widgetData="component.data"
            :visual="component.visual"
            :style="{
                'grid-column': `span ${component.col_span || 1} / span ${component.col_span || 1}`,
                'grid-row': `span ${component.row_span || 1} / span ${component.row_span || 1}`
            }"
        />
    </div>

		<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
			<DashboardCard
				v-for="(org, index) in props.groupStats.organisations.filter(
					(org) => org.type !== 'agent'
				)"
				:key="index"
				:value="
					useLocaleStore().currencyFormat(
						groupStats.currency.code,
						org.interval_percentages?.sales?.[selectedDateOption]?.amount || 0
					)
				"
				:description="`Sales for ${org.name}`"
				:showRedBorder="
					isNegative(org.interval_percentages?.sales?.[selectedDateOption]?.amount || 0)
				"
				:showIcon="
					isNegative(org.interval_percentages?.sales?.[selectedDateOption]?.amount || 0)
				" />
		</div>
	</div> -->
</template>

<style scoped>
.grid-container {
  display: grid;
  gap: 0.75rem;
  grid-template-columns: repeat(var(--column-count, 2), minmax(0, 1fr));
  grid-auto-rows: minmax(0, auto);
}

.widget-item {
  height: 500px;
}

@media (max-width: 768px) {
  .grid-container {
    display: flex;
    flex-direction: column;
    gap: 1rem;
  }

  .widget-item {
    width: 100%;
  }
}
</style>