package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentInboundErrorKey
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.Pallet
import io.aiku.droid.data.api.PalletAuditStart
import io.aiku.droid.data.api.PalletIncidentState
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.data.api.PalletOutcome
import io.aiku.droid.data.api.PalletWrite
import io.aiku.droid.ui.goodsin.GoodsInEvent
import io.aiku.droid.ui.inventory.FakeSessions
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.ExperimentalCoroutinesApi
import kotlinx.coroutines.test.UnconfinedTestDispatcher
import kotlinx.coroutines.test.resetMain
import kotlinx.coroutines.test.setMain
import org.junit.After
import org.junit.Assert.assertEquals
import org.junit.Assert.assertFalse
import org.junit.Assert.assertNull
import org.junit.Assert.assertTrue
import org.junit.Before
import org.junit.Test

@OptIn(ExperimentalCoroutinesApi::class)
class PalletViewModelTest {

    private val api = FakeFulfilmentInboundApi()
    private val storing = testPallet(
        11,
        state = "storing",
        status = "storing",
        location = "D-03-01",
        actions = actions(FulfilmentInboundActionKey.MOVE, FulfilmentInboundActionKey.DAMAGED, FulfilmentInboundActionKey.LOST, FulfilmentInboundActionKey.AUDIT),
    )

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(UnconfinedTestDispatcher())
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun viewModel(pallet: Pallet): PalletViewModel {
        api.pallet = GoodsOutResult.Loaded(pallet)
        return PalletViewModel(api, FakeSessions(), warehouseId = 1, palletId = pallet.id).also { it.onStart() }
    }

    private fun answer(pallet: Pallet, message: String? = null, status: String = GoodsInWriteStatus.DONE): (PalletWrite) -> GoodsOutResult<PalletOutcome> =
        { GoodsOutResult.Loaded(PalletOutcome(pallet = pallet, status = status, message = message)) }

    @Test
    fun `pulling to refresh loads the pallet again`() {
        val pallet = viewModel(storing)
        api.pallet = GoodsOutResult.Loaded(storing.copy(state = "damaged"))

        pallet.refresh()

        assertEquals("damaged", pallet.state.value.pallet?.state)
        assertFalse(pallet.state.value.isRefreshing)
    }

    @Test
    fun `only the incidents the server offers can be reported`() {
        val onlyLost = storing.copy(availableActions = actions(FulfilmentInboundActionKey.LOST))
        val readOnly = storing.copy(availableActions = emptyList())

        assertEquals(listOf(PalletIncidentState.Damaged, PalletIncidentState.Lost), storing.incidentStates())
        assertEquals(listOf(PalletIncidentState.Lost), onlyLost.incidentStates())
        assertTrue(readOnly.incidentStates().isEmpty())
    }

    @Test
    fun `moving sends the scanned code or the picked location id and keeps the re-read pallet`() {
        val pallet = viewModel(storing)
        api.palletWriteResults = answer(storing.copy(location = storing.location?.copy(code = "D-03-02")))

        pallet.move(PalletLocationTarget.Scanned("D-03-02"))
        pallet.eventHandled()
        api.palletWriteResults = answer(storing, message = "Already there.", status = GoodsInWriteStatus.NOTHING_CHANGED)
        pallet.move(PalletLocationTarget.Picked(9, "D-03-01"))

        assertEquals(
            listOf(PalletWrite.Move(PalletLocationTarget.Scanned("D-03-02")), PalletWrite.Move(PalletLocationTarget.Picked(9, "D-03-01"))),
            api.palletWrites,
        )
        val done = pallet.state.value.event as GoodsInEvent.Done
        assertTrue(done.isNothingChanged)
        assertEquals("Already there.", done.message)
    }

    @Test
    fun `a refused move keeps the location error`() {
        val pallet = viewModel(storing)
        api.palletWriteResults = {
            GoodsOutResult.Invalid("This location is not operational.", mapOf(FulfilmentInboundErrorKey.LOCATION_CODE to listOf("This location is not operational.")))
        }

        pallet.move(PalletLocationTarget.Scanned("X-1"))

        assertEquals(listOf("This location is not operational."), pallet.state.value.writeErrors[FulfilmentInboundErrorKey.LOCATION_CODE])
        assertEquals("This location is not operational.", (pallet.state.value.event as GoodsInEvent.Refused).message)
        assertEquals("D-03-01", pallet.state.value.pallet?.locationCode)

        pallet.clearWriteErrors()
        assertTrue(pallet.state.value.writeErrors.isEmpty())
    }

    @Test
    fun `an incident sends its state and the trimmed note, or no note`() {
        val pallet = viewModel(storing)
        api.palletWriteResults = answer(storing.copy(state = "damaged", status = "incident", location = null), message = "Pallet set as damaged.")

        pallet.reportIncident(PalletIncidentState.Damaged, "  Forklift hit the corner ")
        pallet.eventHandled()
        pallet.reportIncident(PalletIncidentState.Lost, "   ")

        assertEquals(
            listOf(PalletWrite.Incident(PalletIncidentState.Damaged, "Forklift hit the corner"), PalletWrite.Incident(PalletIncidentState.Lost, null)),
            api.palletWrites,
        )
        assertEquals("Pallet set as damaged.", (pallet.state.value.event as GoodsInEvent.Done).message)
        assertNull(pallet.state.value.pallet?.locationCode)
    }

    @Test
    fun `starting an audit hands over the audit id once`() {
        val pallet = viewModel(storing)
        api.auditStart = GoodsOutResult.Loaded(PalletAuditStart(auditId = 12, message = "Audit resumed."))

        pallet.startAudit()

        assertEquals(12L, pallet.state.value.startedAudit?.auditId)
        assertEquals("Audit resumed.", (pallet.state.value.event as GoodsInEvent.Done).message)

        pallet.auditOpened()
        assertNull(pallet.state.value.startedAudit)
        assertEquals(1, api.auditStarts)
    }

    @Test
    fun `a refused audit shows the server message and opens nothing`() {
        val pallet = viewModel(storing)
        api.auditStart = GoodsOutResult.Invalid("This customer does not store items.", mapOf("state" to listOf("This customer does not store items.")))

        pallet.startAudit()

        assertNull(pallet.state.value.startedAudit)
        assertEquals("This customer does not store items.", (pallet.state.value.event as GoodsInEvent.Refused).message)
    }
}
