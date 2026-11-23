import Foundation
import Testing
@testable import Aiku

@MainActor
struct GoodsInTests {
    private func item(_ id: Int, state: String = "received", expected: Double = 10, checked: Double? = nil, placed: Double = 0, toPlace: Double = 0) -> MobileStockDeliveryItem {
        MobileStockDeliveryItem(
            id: id,
            state: state,
            sko: MobileGoodsInSko(id: id, code: "SKO-\(id)", name: "Item \(id)", barcode: "50\(id)"),
            skoExpected: expected,
            skoChecked: checked,
            skoPlaced: placed,
            skoToPlace: toPlace
        )
    }

    private func delivery(_ state: String, items: [MobileStockDeliveryItem], actions: [String] = [], checklist: Bool = false) -> MobileStockDelivery {
        MobileStockDelivery(
            id: 1,
            reference: "SD-1",
            state: state,
            items: items,
            checklist: checklist ? MobileStockDeliveryChecklist(sections: []) : nil,
            availableActions: actions.map { MobileGoodsInAction(key: $0) }
        )
    }

    @Test func beforeReceivingOnlyTheItemsTabShows() {
        let arriving = delivery("dispatched", items: [item(1)])

        #expect(arriving.views == [.items])
        #expect(arriving.defaultView == .items)
        #expect(arriving.stage == .dispatched)
    }

    @Test func inGoodsInTheTabsFollowTheStateAsOnAndroid() {
        #expect(delivery("received", items: [item(1)]).views == [.check, .place, .done])
        #expect(delivery("booked_in", items: [item(1)], checklist: true).views == [.check, .done, .checklist])
        #expect(delivery("placed", items: [item(1)]).views == [.done])
        #expect(delivery("not_received", items: [item(1)]).stage == nil)
    }

    @Test func theDefaultTabIsWhereTheWorkIs() {
        #expect(delivery("received", items: [item(1)]).defaultView == .check)
        #expect(delivery("checked", items: [item(1, checked: 10, toPlace: 10)]).defaultView == .place)
        #expect(delivery("booked_in", items: [item(1, state: "placed", checked: 10, placed: 10)]).defaultView == .done)
    }

    @Test func eachTabListsItsItems() {
        let open = item(1)
        let checked = item(2, checked: 5, toPlace: 5)
        let notReceived = item(3, state: "not_received", checked: 0)
        let placed = item(4, state: "placed", checked: 3, placed: 3)
        let sample = delivery("booking_in", items: [open, checked, notReceived, placed])

        #expect(sample.items(for: .check).map(\.id) == [1, 2, 3])
        #expect(sample.items(for: .place).map(\.id) == [2, 4])
        #expect(sample.items(for: .done).map(\.id) == [4])
        #expect(sample.nextToPlace?.id == 2)
    }

    @Test func linesAreScannedByBarcodeOrCode() {
        let items = [item(1), item(2)]

        #expect(items.matching(code: " 501 ").map(\.id) == [1])
        #expect(items.matching(code: "sko-2").map(\.id) == [2])
        #expect(items.matching(code: "").isEmpty)
    }

    @Test func aCheckRaisedAbovePlacedStockIsNoticed() {
        let raised = item(1, checked: 6)

        #expect(raised.wasRaised(above: 4))
        #expect(!raised.wasRaised(above: 6))
    }

    @Test func checkedQuantitiesShowTheirDifference() {
        #expect(GoodsInDifference.of(checked: 0, expected: 4).chip == Chip("Not received −4", .danger))
        #expect(GoodsInDifference.of(checked: 3, expected: 4).chip == Chip("Under −1", .warn))
        #expect(GoodsInDifference.of(checked: 5.5, expected: 4).chip == Chip("Over +1.5", .orange))
        #expect(GoodsInDifference.of(checked: 4, expected: 4).description == "Matches the expected quantity")
    }

    @Test func checkedRowsCarryTheirChip() {
        #expect(item(1).checkLook.chip == nil)
        #expect(item(1, checked: 10).checkLook.chip == Chip("Checked", .success))
        #expect(item(1, checked: 4).checkLook.chip == Chip("Checked · under", .warn))
        #expect(item(1, checked: 0).checkLook.chip == Chip("Not received", .danger))
    }

    @Test func actionsComeFromTheServer() {
        let disabled = MobileGoodsInAction(key: "place", label: "Place", disabledReason: "Check it first")
        let sample = MobileStockDelivery(id: 1, reference: "SD-1", state: "received", availableActions: [MobileGoodsInAction(key: "check"), disabled])

        #expect(sample.canCheck)
        #expect(!sample.canPlace)
        #expect(sample.availableActions.action("place")?.isDisabled == true)
    }

    @Test func clearingFiltersKeepsTheStateAndSearch() {
        let filter = GoodsInDeliveryFilter(state: .checked, source: .agents, search: "AR", dispatched: .week, sort: .reference)

        #expect(filter.activeFilterCount == 2)
        #expect(filter.isFiltered)
        #expect(filter.cleared() == GoodsInDeliveryFilter(state: .checked, search: "AR"))
        #expect(!GoodsInDeliveryFilter(sort: .items).cleared().isFiltered)
    }

    @Test func filterQueryUsesAndroidKeys() {
        let items = GoodsInDeliveryFilter(state: .bookingIn, source: .partners, search: " AR ", dispatched: .week, sort: .newest).query(page: 2).queryItems

        #expect(items.contains(URLQueryItem(name: "state", value: "booking_in")))
        #expect(items.contains(URLQueryItem(name: "dispatched", value: "week")))
        #expect(items.contains(URLQueryItem(name: "sort", value: "-date")))
        #expect(items.contains(URLQueryItem(name: "q", value: "AR")))
    }

    @Test func quantitiesUseTheServerLabelOrAtMostTwoDecimals() {
        #expect(GoodsInText.quantity(2.75, label: "2 6/8") == "2\u{202F}⁶⁄₈")
        #expect(GoodsInText.quantity(1234.5) == "1234.5")
        #expect(GoodsInText.number(1.256) == "1.26")
    }

    @Test func datesShowDayAndMonth() {
        let utc = TimeZone(identifier: "UTC")!
        #expect(GoodsInText.date("2026-11-11T10:30:02+00:00", timeZone: utc) == "11 Nov")
        #expect(GoodsInText.date("2026-11-11", timeZone: utc) == "11 Nov 2026")
        #expect(GoodsInText.date(nil) == nil)
    }

    @Test func problemsUseTheServerMessageOrTheAndroidText() {
        #expect(GoodsInText.problem(APIError.offline) == GoodsInText.offline)
        #expect(GoodsInText.problem(APIError.rejected(message: "No")) == "No")
        #expect(GoodsInText.problem(APIError.http(statusCode: 403)) == GoodsInText.forbidden)
        #expect(GoodsInText.problem(APIError.http(statusCode: 500)) == GoodsInText.server)
        #expect(GoodsInText.isUnsure(APIError.offline))
        #expect(GoodsInText.isUnsure(APIError.http(statusCode: 503)))
        #expect(!GoodsInText.isUnsure(APIError.http(statusCode: 422)))
    }

    @Test func stateTonesMatchAndroid() {
        #expect(GoodsInText.deliveryTone("received") == .info)
        #expect(GoodsInText.deliveryTone("booking_in") == .lime)
        #expect(GoodsInText.deliveryTone("placed") == .success)
        #expect(GoodsInText.deliveryTone("cancelled") == .danger)
        #expect(GoodsInText.returnTone("returning") == .info)
        #expect(GoodsInText.returnTone("done") == .success)
    }
}
