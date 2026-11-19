package io.aiku.droid.ui.fulfilment.inbound

import io.aiku.droid.data.api.FulfilmentInboundActionKey
import io.aiku.droid.data.api.FulfilmentInboundErrorKey
import io.aiku.droid.data.api.FulfilmentLocation
import io.aiku.droid.data.api.GoodsInWriteStatus
import io.aiku.droid.data.api.GoodsOutResult
import io.aiku.droid.data.api.PalletDelivery
import io.aiku.droid.data.api.PalletDeliveryOutcome
import io.aiku.droid.data.api.PalletDeliveryWrite
import io.aiku.droid.data.api.PalletLocationTarget
import io.aiku.droid.ui.goodsin.GoodsInEvent
import io.aiku.droid.ui.goodsout.GoodsOutProblem
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
class PalletDeliveryViewModelTest {

    private val dispatcher = UnconfinedTestDispatcher()
    private val api = FakeFulfilmentInboundApi()

    private val waiting = testPallet(1, actions = actions(FulfilmentInboundActionKey.BOOK_IN, FulfilmentInboundActionKey.NOT_RECEIVED), customerReference = "Winter 4")
    private val booked = testPallet(2, state = "booked_in", location = "D-02-01", actions = actions(FulfilmentInboundActionKey.BOOK_IN))
    private val bookingIn = testDelivery("booking_in", listOf(waiting, booked), finishBlocked = "1 pallet still to book in")

    @Before
    fun useTestMainDispatcher() {
        Dispatchers.setMain(dispatcher)
    }

    @After
    fun restoreMainDispatcher() {
        Dispatchers.resetMain()
    }

    private fun viewModel(delivery: PalletDelivery): PalletDeliveryViewModel {
        api.delivery = GoodsOutResult.Loaded(delivery)
        return PalletDeliveryViewModel(api, FakeSessions(), warehouseId = 1, deliveryId = delivery.id).also { it.onStart() }
    }

    private fun answer(delivery: PalletDelivery, message: String? = null, status: String = GoodsInWriteStatus.DONE): (PalletDeliveryWrite) -> GoodsOutResult<PalletDeliveryOutcome> =
        { GoodsOutResult.Loaded(PalletDeliveryOutcome(delivery = delivery, status = status, message = message)) }

    @Test
    fun `delivery actions show only when the server offers them`() {
        val confirmed = testDelivery("confirmed", listOf(testPallet(1, state = "confirmed")), actions = actions(FulfilmentInboundActionKey.RECEIVE))
        val clerkView = testDelivery("confirmed", listOf(testPallet(1, state = "confirmed")))

        assertTrue(confirmed.can(FulfilmentInboundActionKey.RECEIVE))
        assertFalse(confirmed.can(FulfilmentInboundActionKey.START_BOOKING))
        assertFalse(clerkView.can(FulfilmentInboundActionKey.RECEIVE))
        assertTrue(waiting.can(FulfilmentInboundActionKey.NOT_RECEIVED))
        assertFalse(booked.can(FulfilmentInboundActionKey.NOT_RECEIVED))
        assertFalse(bookingIn.can(FulfilmentInboundActionKey.FINISH_BOOKING))
        assertEquals("1 pallet still to book in", bookingIn.finishBookingBlocked)
    }

    @Test
    fun `pulling to refresh loads the delivery again`() {
        val delivery = viewModel(testDelivery("confirmed", listOf(testPallet(1, state = "confirmed"))))
        api.delivery = GoodsOutResult.Loaded(testDelivery("received", listOf(testPallet(1, state = "received"))))

        delivery.refresh()

        assertEquals("received", delivery.state.value.delivery?.state)
        assertFalse(delivery.state.value.isRefreshing)
    }

    @Test
    fun `receiving keeps the re-read detail and its message`() {
        val delivery = viewModel(testDelivery("confirmed", listOf(testPallet(1, state = "confirmed")), actions = actions(FulfilmentInboundActionKey.RECEIVE)))
        api.deliveryWriteResults = answer(testDelivery("received", listOf(testPallet(1, state = "received")), actions = actions(FulfilmentInboundActionKey.START_BOOKING)))

        delivery.receive()

        assertEquals(listOf<PalletDeliveryWrite>(PalletDeliveryWrite.Receive), api.deliveryWrites)
        assertEquals("received", delivery.state.value.delivery?.state)
        assertTrue(delivery.state.value.delivery!!.can(FulfilmentInboundActionKey.START_BOOKING))
        assertEquals(PalletDeliveryWrite.Receive, (delivery.state.value.event as GoodsInEvent.Done).action)
        assertFalse(delivery.state.value.isWorking)
    }

    @Test
    fun `a refused receive shows the server message`() {
        val delivery = viewModel(testDelivery("confirmed", emptyList(), actions = actions(FulfilmentInboundActionKey.RECEIVE)))
        api.deliveryWriteResults = {
            GoodsOutResult.Invalid(
                "This customer has no rental agreement; set it up on the web first.",
                mapOf("state" to listOf("This customer has no rental agreement; set it up on the web first.")),
            )
        }

        delivery.receive()

        val refused = delivery.state.value.event as GoodsInEvent.Refused
        assertEquals("This customer has no rental agreement; set it up on the web first.", refused.message)
        assertEquals("confirmed", delivery.state.value.delivery?.state)
    }

    @Test
    fun `starting and finishing booking in are one write each`() {
        val delivery = viewModel(testDelivery("received", listOf(testPallet(1, state = "received")), actions = actions(FulfilmentInboundActionKey.START_BOOKING)))
        api.deliveryWriteResults = answer(bookingIn, message = "1 pallet set as not received was reset to booking in.")

        delivery.startBooking()
        val started = delivery.state.value.event as GoodsInEvent.Done
        delivery.eventHandled()
        api.deliveryWriteResults = { GoodsOutResult.Forbidden("You can not edit this fulfilment shop.") }
        delivery.finishBooking()

        assertEquals(listOf(PalletDeliveryWrite.StartBooking, PalletDeliveryWrite.FinishBooking), api.deliveryWrites)
        assertEquals("1 pallet set as not received was reset to booking in.", started.message)
        val refused = delivery.state.value.event as GoodsInEvent.Refused
        assertEquals("You can not edit this fulfilment shop.", refused.message)
        assertEquals(GoodsOutProblem.Forbidden, refused.problem)
    }

    @Test
    fun `scanning a pallet label chooses it and the next scan books it in at that location code`() {
        val delivery = viewModel(bookingIn)
        val bookedNow = testDelivery("booking_in", listOf(waiting.copy(state = "booked_in"), booked))
        api.deliveryWriteResults = answer(bookedNow)

        delivery.scanForBookIn("ACME-P1")
        assertEquals(1L, delivery.state.value.openPalletId)
        assertTrue(api.deliveryWrites.isEmpty())

        delivery.scanForBookIn(" d-02-04 ")

        assertEquals(listOf<PalletDeliveryWrite>(PalletDeliveryWrite.BookIn(1, PalletLocationTarget.Scanned("d-02-04"))), api.deliveryWrites)
        assertNull(delivery.state.value.openPalletId)
        assertTrue(delivery.state.value.event is GoodsInEvent.Done)
    }

    @Test
    fun `a customer reference chooses its pallet and a code that is no pallet needs a pallet first`() {
        val delivery = viewModel(bookingIn)

        delivery.scanForBookIn("D-02-04")
        assertEquals(BookInScanMiss("D-02-04"), delivery.state.value.scanMiss)
        assertTrue(api.deliveryWrites.isEmpty())

        delivery.scanMissHandled()
        delivery.scanForBookIn("winter 4")
        assertEquals(1L, delivery.state.value.openPalletId)
        assertNull(delivery.state.value.scanMiss)
    }

    @Test
    fun `a typed location is booked in by id and a refused location keeps the field error and the pallet open`() {
        val delivery = viewModel(bookingIn)
        api.locations = GoodsOutResult.Loaded(listOf(FulfilmentLocation(id = 330, code = "D-02-04", numberPallets = 0)))
        api.deliveryWriteResults = {
            GoodsOutResult.Invalid("This location does not take pallets.", mapOf(FulfilmentInboundErrorKey.LOCATION_ID to listOf("This location does not take pallets.")))
        }

        delivery.openPallet(1)
        delivery.finder.search("D-02")
        dispatcher.scheduler.advanceTimeBy(SEARCH_WAIT_MILLIS)
        delivery.finder.pick(delivery.finder.state.value.results.single())
        delivery.bookIn(1, delivery.finder.state.value.target!!)

        assertEquals(listOf("D-02"), api.locationSearches)
        assertEquals(listOf<PalletDeliveryWrite>(PalletDeliveryWrite.BookIn(1, PalletLocationTarget.Picked(330, "D-02-04"))), api.deliveryWrites)
        assertEquals(listOf("This location does not take pallets."), delivery.state.value.writeErrors[FulfilmentInboundErrorKey.LOCATION_ID])
        assertEquals(1L, delivery.state.value.openPalletId)
        assertEquals("This location does not take pallets.", (delivery.state.value.event as GoodsInEvent.Refused).message)
    }

    @Test
    fun `re-booking the same location says nothing changed`() {
        val delivery = viewModel(bookingIn)
        api.deliveryWriteResults = answer(bookingIn, message = "Already at D-02-01.", status = GoodsInWriteStatus.NOTHING_CHANGED)

        delivery.openPallet(2)
        delivery.scanForBookIn("D-02-01")

        assertEquals(listOf<PalletDeliveryWrite>(PalletDeliveryWrite.BookIn(2, PalletLocationTarget.Scanned("D-02-01"))), api.deliveryWrites)
        assertTrue((delivery.state.value.event as GoodsInEvent.Done).isNothingChanged)
    }

    @Test
    fun `not received and its undo close the pallet and keep the re-read detail`() {
        val received = testDelivery(
            "received",
            listOf(testPallet(1, state = "received", actions = actions(FulfilmentInboundActionKey.NOT_RECEIVED))),
        )
        val delivery = viewModel(received)
        val afterNotReceived = testDelivery("received", listOf(testPallet(1, state = "not_received", actions = actions(FulfilmentInboundActionKey.UNDO_NOT_RECEIVED))))
        api.deliveryWriteResults = answer(afterNotReceived, message = "Pallet set as not received.")

        delivery.openPallet(1)
        delivery.notReceived(1)

        assertNull(delivery.state.value.openPalletId)
        assertEquals("Pallet set as not received.", (delivery.state.value.event as GoodsInEvent.Done).message)
        assertTrue(delivery.state.value.delivery!!.pallet(1)!!.can(FulfilmentInboundActionKey.UNDO_NOT_RECEIVED))

        delivery.eventHandled()
        api.deliveryWriteResults = answer(received)
        delivery.undoNotReceived(1)

        assertEquals(listOf(PalletDeliveryWrite.NotReceived(1), PalletDeliveryWrite.UndoNotReceived(1)), api.deliveryWrites)
        assertEquals("received", delivery.state.value.delivery!!.pallet(1)!!.state)
    }

    @Test
    fun `a pallet that cannot be booked in does not take a location scan`() {
        val notReceived = testPallet(3, state = "not_received")
        val delivery = viewModel(testDelivery("booking_in", listOf(notReceived)))

        delivery.scanForBookIn("acme-p3")
        delivery.scanForBookIn("D-02-04")

        assertEquals(3L, delivery.state.value.openPalletId)
        assertEquals(BookInScanMiss("D-02-04"), delivery.state.value.scanMiss)
        assertTrue(api.deliveryWrites.isEmpty())
    }

    @Test
    fun `a failed load can be retried`() {
        api.delivery = GoodsOutResult.NotFound("")
        val delivery = PalletDeliveryViewModel(api, FakeSessions(), warehouseId = 1, deliveryId = 4)
        delivery.onStart()

        assertEquals(GoodsOutProblem.NotFound, delivery.state.value.problem)

        api.delivery = GoodsOutResult.Loaded(bookingIn)
        delivery.retry()

        assertEquals("acme-012", delivery.state.value.delivery?.reference)
        assertNull(delivery.state.value.problem)
    }

    private companion object {
        const val SEARCH_WAIT_MILLIS = 301L
    }
}
