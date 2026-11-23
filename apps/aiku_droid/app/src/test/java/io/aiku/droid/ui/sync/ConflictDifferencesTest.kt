package io.aiku.droid.ui.sync

import io.aiku.droid.data.api.OfflineCountedRow
import io.aiku.droid.data.api.OfflineOperationEntry
import io.aiku.droid.data.api.OfflineOperationLabels
import org.junit.Assert.assertEquals
import org.junit.Test

class ConflictDifferencesTest {

    @Test
    fun `a refused pick compares what you did with what Aiku recorded`() {
        val pick = OfflineOperationEntry(operation = "pick", requestedQuantity = 1.0, labels = OfflineOperationLabels(locationCode = "20A19"))

        val rows = conflictDifferences(pick)

        assertEquals(listOf(ConflictDifference(title = null, subtitle = "20A19", yours = 1.0, aiku = 0.0)), rows)
        assertEquals(-1.0, rows.single().difference)
    }

    @Test
    fun `a partly applied action shows the part Aiku kept`() {
        val move = OfflineOperationEntry(operation = "move_stock", requestedQuantity = 5.0, appliedQuantity = 3.0)

        assertEquals(-2.0, conflictDifferences(move).single().difference)
    }

    @Test
    fun `a location audit lists each SKO with its count and what Aiku has`() {
        val audit = OfflineOperationEntry(
            operation = "audit_location",
            countedRows = listOf(
                OfflineCountedRow(locationCode = "57B2", skoCode = "BBUG-06", counted = 9.0, quantity = 9.0),
                OfflineCountedRow(locationCode = "57B2", skoCode = "BBUG-07", counted = 4.0, quantity = 6.0),
            ),
        )

        assertEquals(
            listOf(
                ConflictDifference(title = "BBUG-06", subtitle = "57B2", yours = 9.0, aiku = 9.0),
                ConflictDifference(title = "BBUG-07", subtitle = "57B2", yours = 4.0, aiku = 6.0),
            ),
            conflictDifferences(audit),
        )
        assertEquals(listOf(0.0, 2.0), conflictDifferences(audit).map { it.difference })
    }

    @Test
    fun `an SKO audit across locations is listed by location`() {
        val audit = OfflineOperationEntry(
            operation = "audit_org_stock",
            countedRows = listOf(
                OfflineCountedRow(locationCode = "A-01", skoCode = "BBUG-06", counted = 3.0, quantity = 2.0),
                OfflineCountedRow(locationCode = "B-02", skoCode = "BBUG-06", counted = 1.0, quantity = 1.0),
            ),
        )

        assertEquals(listOf("A-01", "B-02"), conflictDifferences(audit).map { it.title })
        assertEquals(listOf(null, null), conflictDifferences(audit).map { it.subtitle })
    }
}
