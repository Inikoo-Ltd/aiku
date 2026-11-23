package io.aiku.droid.ui.components

import java.time.LocalDate

data class DateRange(val from: LocalDate? = null, val to: LocalDate? = null) {
    val isSet: Boolean get() = from != null || to != null

    fun withFrom(date: LocalDate?): DateRange = DateRange(from = date, to = to?.takeIf { date == null || !it.isBefore(date) })

    fun withTo(date: LocalDate?): DateRange = DateRange(from = from?.takeIf { date == null || !it.isAfter(date) }, to = date)
}

data class ListFilter<S>(val dates: DateRange = DateRange(), val sort: S? = null) {
    val activeCount: Int get() = listOf(dates.isSet, sort != null).count { it }

    val isFiltered: Boolean get() = activeCount > 0
}

data class SortOption<S>(val sort: S?, val label: String)
