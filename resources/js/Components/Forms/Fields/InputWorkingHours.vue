<script setup lang="ts">
import { reactive, watch } from 'vue'
import { get } from 'lodash-es'
import DatePicker from 'primevue/datepicker'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleDown, faChevronCircleUp } from '@far'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
  form: any
  fieldName: string
  fieldData?: any
}>()

/*
|--------------------------------------------------------------------------
| TIME HELPERS
|--------------------------------------------------------------------------
*/
const parseTime = (val: any) => {
  if (!val) return null

  if (val instanceof Date) return val

  if (typeof val === 'string' && val.includes('T')) {
    const d = new Date(val)
    if (!isNaN(d.getTime())) return d
  }

  if (typeof val === 'string' && val.includes(':')) {
    const parts = val.split(':')
    if (parts.length < 2) return null

    const h = Number(parts[0])
    const m = Number(parts[1])
    if (isNaN(h) || isNaN(m)) return null

    const d = new Date()
    d.setHours(h)
    d.setMinutes(m)
    d.setSeconds(0)
    d.setMilliseconds(0)
    return d
  }

  return null
}

const formatTime = (val: Date | null) => {
  if (!val) return null
  const h = String(val.getHours()).padStart(2, '0')
  const m = String(val.getMinutes()).padStart(2, '0')
  return `${h}:${m}`
}

/*
|--------------------------------------------------------------------------
| MAPS
|--------------------------------------------------------------------------
*/
const dayMap: any = {
  monday: 1,
  tuesday: 2,
  wednesday: 3,
  thursday: 4,
  friday: 5,
  saturday: 6,
  sunday: 7
}

const reverseDayMap: any = {
  1: 'monday',
  2: 'tuesday',
  3: 'wednesday',
  4: 'thursday',
  5: 'friday',
  6: 'saturday',
  7: 'sunday'
}

/*
|--------------------------------------------------------------------------
| STATE
|--------------------------------------------------------------------------
*/
const weekTimes: any = reactive({
  sunday: { in: null, out: null },
  monday: { in: null, out: null },
  tuesday: { in: null, out: null },
  wednesday: { in: null, out: null },
  thursday: { in: null, out: null },
  friday: { in: null, out: null },
  saturday: { in: null, out: null }
})

const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
const weekends = ['saturday', 'sunday']

const ui = reactive({
  openWeekday: true,
  openWeekend: true
})

const group: any = reactive({
  weekday: { closed: false, in: null, out: null },
  weekend: { closed: false, in: null, out: null }
})

let hydrating = false

/*
|--------------------------------------------------------------------------
| INIT / RESET / EDIT HYDRATE
|--------------------------------------------------------------------------
*/
const initFromForm = (val: any) => {
  hydrating = true

  // create default if null
  if (!val || typeof val !== 'object') {
    val = {
      metadata: {
        group_weekdays: false,
        group_weekend: false
      },
      data: {}
    }
    props.form[props.fieldName] = val
  }

  if (!val.data) val.data = {}

  // reset UI first
  Object.keys(weekTimes).forEach(d => {
    weekTimes[d].in = null
    weekTimes[d].out = null
  })

  // hydrate
  Object.keys(val.data).forEach((num: any) => {
    const day = reverseDayMap[num]
    if (!day) return

    weekTimes[day].in = parseTime(val.data[num]?.s)
    weekTimes[day].out = parseTime(val.data[num]?.e)
  })

  setTimeout(() => hydrating = false)
}

initFromForm(props.form[props.fieldName])


watch(
  () => props.form[props.fieldName],
  (v) => {
    initFromForm(v)
  },
  { deep: true }
)


watch(() => [group.weekday.closed, group.weekday.in, group.weekday.out], () => {
  if (!group.weekday.closed) return
  weekdays.forEach(d => {
    weekTimes[d].in = group.weekday.in
    weekTimes[d].out = group.weekday.out
  })
})

watch(() => [group.weekend.closed, group.weekend.in, group.weekend.out], () => {
  if (!group.weekend.closed) return
  weekends.forEach(d => {
    weekTimes[d].in = group.weekend.in
    weekTimes[d].out = group.weekend.out
  })
})


const hasAnyTime = (days: string[]) => {
  return days.some(d => {
    return weekTimes[d].in || weekTimes[d].out
  })
}

const buildPayload = () => {
  const result: any = {
    metadata: {
      group_weekdays: hasAnyTime(weekdays),
      group_weekend: hasAnyTime(weekends)
    },
    data: {}
  }

  Object.keys(weekTimes).forEach((day: any) => {
    const num = dayMap[day]

    const s = formatTime(weekTimes[day].in)
    const e = formatTime(weekTimes[day].out)

    if (!s && !e) return

    result.data[num] = {
      s,
      e,
      b: {}
    }
  })

  return result
}


watch(() => weekTimes, () => {
  if (hydrating) return
  props.form[props.fieldName] = buildPayload()
}, { deep: true })
</script>

<template>
  <div class="space-y-3">

    <div class="border rounded-xl overflow-hidden bg-white">

      <div class="grid grid-cols-3 px-3 py-2 bg-gray-50 text-[11px] font-semibold text-gray-500 border-b">
        <div>{{ trans('Day') }}</div>
        <div>{{ trans('Start') }}</div>
        <div>{{ trans('End') }}</div>
      </div>

      <template v-for="section in [
        { key: 'weekday', label: trans('Weekdays'), days: weekdays },
        { key: 'weekend', label: trans('Weekend'), days: weekends }
      ]" :key="section.key">

        <div :class="section.key === 'weekday' ? 'border-b' : ''">

          <div class="grid grid-cols-3 items-center px-3 py-2 bg-gray-50/70 border-t">

            <div class="flex items-center gap-2 font-semibold text-sm">
              <div @click="ui[section.key === 'weekday' ? 'openWeekday' : 'openWeekend'] =
                !ui[section.key === 'weekday' ? 'openWeekday' : 'openWeekend']">
                {{ section.label }}
              </div>

              <div @click="group[section.key].closed = !group[section.key].closed"
                class="text-gray-400 hover:text-gray-700">
                <FontAwesomeIcon :icon="group[section.key].closed ? faChevronCircleDown : faChevronCircleUp" />
              </div>
            </div>

            <div class="py-1 pr-2">
              <DatePicker v-if="group[section.key].closed" v-model="group[section.key].in" timeOnly fluid
                :placeholder="trans('Start')" inputClass="text-sm py-1" :showClear="true" />
            </div>

            <div>
              <DatePicker v-if="group[section.key].closed" v-model="group[section.key].out" timeOnly fluid
                :placeholder="trans('End')" inputClass="text-sm py-1" :showClear="true" />
            </div>
          </div>

          <div v-if="(section.key === 'weekday' ? ui.openWeekday : ui.openWeekend) && !group[section.key].closed">
            <div v-for="d in section.days" :key="d" class="grid grid-cols-3 items-center border-t px-3">

              <div class="py-2 text-sm capitalize">
                {{ trans(d.charAt(0).toUpperCase() + d.slice(1)) }}
              </div>

              <div class="py-1 pr-2">
                <DatePicker v-model="weekTimes[d].in" timeOnly fluid :placeholder="trans('Start')"
                  inputClass="text-sm py-1" :showClear="true" />
              </div>

              <div class="py-1">
                <DatePicker v-model="weekTimes[d].out" timeOnly fluid :placeholder="trans('End')"
                  inputClass="text-sm py-1" :showClear="true" />
              </div>

            </div>
          </div>

        </div>
      </template>

    </div>

    <p v-if="get(form, ['errors', fieldName])" class="text-sm text-red-600">
      {{ form.errors[fieldName] }}
    </p>

  </div>
</template>
