<script setup lang="ts">
import { reactive, watch } from 'vue'
import { get } from 'lodash-es'
import DatePicker from 'primevue/datepicker'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleDown, faChevronCircleUp, faPlus, faTrash } from '@far'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
  form: any
  fieldName: string
  fieldData?: any
}>()


const parseTime = (val: any) => {
    if (!val) return null

    if (val instanceof Date && !isNaN(val.getTime())) return val

    if (typeof val === 'string') {
        const timePart = val.includes('T') ? val.split('T')[1] : val
        const clean = timePart.replace(/Z|[+-]\d{2}:\d{2}$/, '')
        const parts = clean.split(':')

        if (parts.length >= 2) {
            const h = Number(parts[0])
            const m = Number(parts[1])
            if (isNaN(h) || isNaN(m)) return null

            const d = new Date()
            d.setHours(h, m, 0, 0)
            return d
        }
    }

    return null
}




const formatTime = (val: Date | null) => {
    if (!val) return null

    const h = String(val.getHours()).padStart(2, '0')
    const m = String(val.getMinutes()).padStart(2, '0')

    return `${h}:${m}`
}



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


const weekTimes: any = reactive({
  sunday: { in: null, out: null, breaks: [] },
  monday: { in: null, out: null, breaks: [] },
  tuesday: { in: null, out: null, breaks: [] },
  wednesday: { in: null, out: null, breaks: [] },
  thursday: { in: null, out: null, breaks: [] },
  friday: { in: null, out: null, breaks: [] },
  saturday: { in: null, out: null, breaks: [] }
})

const parseBreaks = (val: any) => {
  if (!Array.isArray(val)) return []

  return val.map((brk: any) => ({
    in: parseTime(brk?.s),
    out: parseTime(brk?.e),
    label: brk?.n ?? ''
  }))
}

const cloneBreaks = (breaks: any[]) => {
  return breaks.map((brk: any) => ({
    in: brk.in ? new Date(brk.in) : null,
    out: brk.out ? new Date(brk.out) : null,
    label: brk.label
  }))
}

const addBreak = (target: any) => {
  target.breaks.push({ in: null, out: null, label: '' })
}

const removeBreak = (target: any, index: number) => {
  target.breaks.splice(index, 1)
}

const weekdays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']
const weekends = ['saturday', 'sunday']

const ui = reactive({
  openWeekday: true,
  openWeekend: true
})

const group: any = reactive({
  weekday: { closed: false, in: null, out: null, breaks: [] },
  weekend: { closed: false, in: null, out: null, breaks: [] }
})

let hydrating = false
let selfUpdate = false


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
    weekTimes[d].breaks = []
  })

  // hydrate
  Object.keys(val.data).forEach((num: any) => {
    const day = reverseDayMap[num]
    if (!day) return

    weekTimes[day].in = parseTime(val.data[num]?.s)
    weekTimes[day].out = parseTime(val.data[num]?.e)
    weekTimes[day].breaks = parseBreaks(val.data[num]?.b)
  })

  setTimeout(() => hydrating = false)
}

initFromForm(props.form[props.fieldName])


watch(
  () => props.form[props.fieldName],
  (v) => {
    if (selfUpdate) {
      selfUpdate = false
      return
    }
    initFromForm(v)
  },
  { deep: true }
)


watch(() => [group.weekday.closed, group.weekday.in, group.weekday.out, group.weekday.breaks], () => {
  if (!group.weekday.closed) return
  weekdays.forEach(d => {
    weekTimes[d].in = group.weekday.in
    weekTimes[d].out = group.weekday.out
    weekTimes[d].breaks = cloneBreaks(group.weekday.breaks)
  })
}, { deep: true })

watch(() => [group.weekend.closed, group.weekend.in, group.weekend.out, group.weekend.breaks], () => {
  if (!group.weekend.closed) return
  weekends.forEach(d => {
    weekTimes[d].in = group.weekend.in
    weekTimes[d].out = group.weekend.out
    weekTimes[d].breaks = cloneBreaks(group.weekend.breaks)
  })
}, { deep: true })


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

    const b = weekTimes[day].breaks
      .filter((brk: any) => brk.in || brk.out)
      .map((brk: any) => ({
        s: formatTime(brk.in),
        e: formatTime(brk.out),
        n: brk.label ?? ''
      }))

    if (!s && !e && !b.length) return

    result.data[num] = {
      s,
      e,
      b
    }
  })

  return result
}


watch(() => weekTimes, () => {
  if (hydrating) return
  selfUpdate = true
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

          <template v-if="group[section.key].closed">
            <div v-for="(brk, i) in group[section.key].breaks" :key="`${section.key}-break-${i}`"
              class="grid grid-cols-3 items-center border-t border-dashed px-3 bg-gray-50/40">

              <div class="py-1 pr-2 pl-5">
                <input v-model="brk.label" type="text" :placeholder="trans('Break label')" :aria-label="trans('Break label')"
                  class="w-full text-sm py-1 px-2 border border-gray-300 rounded" />
              </div>

              <div class="py-1 pr-2">
                <DatePicker v-model="brk.in" timeOnly fluid :placeholder="trans('Break start')"
                  inputClass="text-sm py-1" :showClear="true" />
              </div>

              <div class="py-1 flex items-center gap-2">
                <DatePicker v-model="brk.out" timeOnly fluid :placeholder="trans('Break end')"
                  inputClass="text-sm py-1" :showClear="true" />

                <div @click="removeBreak(group[section.key], i)"
                  class="cursor-pointer text-gray-400 hover:text-red-600">
                  <FontAwesomeIcon :icon="faTrash" />
                </div>
              </div>
            </div>

            <div class="border-t px-3 py-2 pl-5">
              <div @click="addBreak(group[section.key])"
                class="inline-flex items-center gap-1 text-xs cursor-pointer text-gray-500 hover:text-gray-800">
                <FontAwesomeIcon :icon="faPlus" />
                {{ trans('Add break') }}
              </div>
            </div>
          </template>

          <div v-if="(section.key === 'weekday' ? ui.openWeekday : ui.openWeekend) && !group[section.key].closed">
            <div v-for="d in section.days" :key="d">
              <div class="grid grid-cols-3 items-center border-t px-3">

                <div class="py-2 text-sm capitalize">
                  {{ trans(d.charAt(0).toUpperCase() + d.slice(1)) }}
                </div>

                <div class="py-1 pr-2">
                  <DatePicker v-model="weekTimes[d].in" timeOnly fluid :minuteStep="1" :hourStep="1"
                      :placeholder="trans('Start')" inputClass="text-sm py-1" :showClear="true" />

                </div>

                <div class="py-1">
                  <DatePicker v-model="weekTimes[d].out" timeOnly fluid :placeholder="trans('End')"
                    inputClass="text-sm py-1" :showClear="true" />
                </div>

              </div>

              <div v-for="(brk, i) in weekTimes[d].breaks" :key="`${d}-break-${i}`"
                class="grid grid-cols-3 items-center border-t border-dashed px-3 bg-gray-50/40">

                <div class="py-1 pr-2 pl-5">
                  <input v-model="brk.label" type="text" :placeholder="trans('Break label')" :aria-label="trans('Break label')"
                    class="w-full text-sm py-1 px-2 border border-gray-300 rounded" />
                </div>

                <div class="py-1 pr-2">
                  <DatePicker v-model="brk.in" timeOnly fluid :placeholder="trans('Break start')"
                    inputClass="text-sm py-1" :showClear="true" />
                </div>

                <div class="py-1 flex items-center gap-2">
                  <DatePicker v-model="brk.out" timeOnly fluid :placeholder="trans('Break end')"
                    inputClass="text-sm py-1" :showClear="true" />

                  <div @click="removeBreak(weekTimes[d], i)"
                    class="cursor-pointer text-gray-400 hover:text-red-600">
                    <FontAwesomeIcon :icon="faTrash" />
                  </div>
                </div>
              </div>

              <div class="px-3 py-1 pl-5">
                <div @click="addBreak(weekTimes[d])"
                  class="inline-flex items-center gap-1 text-xs cursor-pointer text-gray-500 hover:text-gray-800">
                  <FontAwesomeIcon :icon="faPlus" />
                  {{ trans('Add break') }}
                </div>
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
