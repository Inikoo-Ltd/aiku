<script setup lang="ts">
import { reactive, watch } from 'vue'
import { get } from 'lodash-es'
import DatePicker from 'primevue/datepicker'
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome'
import { faChevronCircleDown, faChevronCircleUp } from '@far'
import { trans } from 'laravel-vue-i18n'

const props = defineProps<{
  form:any
  fieldName:string
  fieldData?:any
}>()

const parseTime = (val:string|null)=>{
  if(!val) return null
  const [h,m] = val.split(':')
  const d = new Date()
  d.setHours(+h)
  d.setMinutes(+m)
  d.setSeconds(0)
  d.setMilliseconds(0)
  return d
}

const formatTime = (val:Date|null)=>{
  if(!val) return null
  const h = String(val.getHours()).padStart(2,'0')
  const m = String(val.getMinutes()).padStart(2,'0')
  return `${h}:${m}`
}


const dayMap:any = {
  monday:1,
  tuesday:2,
  wednesday:3,
  thursday:4,
  friday:5,
  saturday:6,
  sunday:7
}

const weekTimes:any = reactive({
  sunday:{in:null,out:null},
  monday:{in:null,out:null},
  tuesday:{in:null,out:null},
  wednesday:{in:null,out:null},
  thursday:{in:null,out:null},
  friday:{in:null,out:null},
  saturday:{in:null,out:null}
})

const weekdays = ['monday','tuesday','wednesday','thursday','friday']
const weekends = ['saturday','sunday']

const ui = reactive({
  openWeekday:true,
  openWeekend:true
})

const group:any = reactive({
  weekday:{closed:false,in:null,out:null},
  weekend:{closed:false,in:null,out:null}
})

if(props.fieldData?.initial_value){
  const init = props.fieldData.initial_value

  Object.keys(init).forEach((day:any)=>{
    if(!weekTimes[day]) return
    weekTimes[day].in  = parseTime(init[day]?.s)
    weekTimes[day].out = parseTime(init[day]?.e)
  })
}


watch(()=>[group.weekday.closed,group.weekday.in,group.weekday.out],()=>{
  if(!group.weekday.closed) return
  weekdays.forEach(d=>{
    weekTimes[d].in = group.weekday.in
    weekTimes[d].out = group.weekday.out
  })
})

watch(()=>[group.weekend.closed,group.weekend.in,group.weekend.out],()=>{
  if(!group.weekend.closed) return
  weekends.forEach(d=>{
    weekTimes[d].in = group.weekend.in
    weekTimes[d].out = group.weekend.out
  })
})


const hasAnyTime = (days:string[])=>{
  return days.some(d=>{
    return weekTimes[d].in || weekTimes[d].out
  })
}


const buildPayload = ()=>{
  const result:any = {
    metadata:{
      group_weekdays: hasAnyTime(weekdays),
      group_weekend: hasAnyTime(weekends)
    },
    data:{}
  }

  Object.keys(weekTimes).forEach((day:any)=>{
    const num = dayMap[day]

    const s = weekTimes[day].in
    const e = weekTimes[day].out

    // skip jika kosong
    if(!s && !e) return

    result.data[num] = {
      s,
      e,
      b:{}
    }
  })

  return result
}


watch(()=>weekTimes,()=>{
  props.form[props.fieldName] = buildPayload()
},{deep:true, immediate:true})
</script>


<template>
<div class="space-y-3">

  <div class="border rounded-xl overflow-hidden bg-white">

    <!-- HEADER -->
    <div class="grid grid-cols-3 px-3 py-2 bg-gray-50 text-[11px] font-semibold text-gray-500 border-b">
      <div>{{ trans('Day') }}</div>
      <div>{{ trans('Start') }}</div>
      <div>{{ trans('End') }}</div>
    </div>

    <!-- LOOP -->
    <template v-for="section in [
      { key:'weekday', label: trans('Weekdays'), days: weekdays },
      { key:'weekend', label: trans('Weekend'), days: weekends }
    ]" :key="section.key">

      <div :class="section.key==='weekday' ? 'border-b' : ''">

        <!-- group row -->
        <div class="grid grid-cols-3 items-center px-3 py-2 bg-gray-50/70 border-t">

          <div class="flex items-center gap-2 font-semibold text-sm">
            <div
              @click="ui[section.key==='weekday'?'openWeekday':'openWeekend'] =
                      !ui[section.key==='weekday'?'openWeekday':'openWeekend']">
              {{ section.label }}
            </div>

            <div
              @click="group[section.key].closed = !group[section.key].closed"
              class="text-gray-400 hover:text-gray-700">
              <FontAwesomeIcon :icon="group[section.key].closed ? faChevronCircleDown : faChevronCircleUp"/>
            </div>
          </div>

        <div class="py-1 pr-2">
            <DatePicker
              v-if="group[section.key].closed"
              v-model="group[section.key].in"
              timeOnly fluid
              :placeholder="trans('Start')"
              inputClass="text-sm py-1"
              :showClear="true"
            />
          </div>

          <div>
            <DatePicker
              v-if="group[section.key].closed"
              v-model="group[section.key].out"
              timeOnly fluid
              :placeholder="trans('End')"
              inputClass="text-sm py-1"
              :showClear="true"
            />
          </div>
        </div>

        <!-- day rows -->
        <div v-if="(section.key==='weekday'?ui.openWeekday:ui.openWeekend) && !group[section.key].closed">
          <div v-for="d in section.days" :key="d"
               class="grid grid-cols-3 items-center border-t px-3">

            <div class="py-2 text-sm capitalize">
              {{ trans(d.charAt(0).toUpperCase()+d.slice(1)) }}
            </div>

            <div class="py-1 pr-2">
              <DatePicker
                v-model="weekTimes[d].in"
                timeOnly fluid
                :placeholder="trans('Start')"
                inputClass="text-sm py-1"
                :showClear="true"
              />
            </div>

            <div class="py-1">
              <DatePicker
                v-model="weekTimes[d].out"
                timeOnly fluid
                :placeholder="trans('End')"
                inputClass="text-sm py-1"
                :showClear="true"
              />
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
