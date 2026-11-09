import Foundation
import Testing
@testable import Aiku

struct WarehouseModelTests {
    @Test func deliveryNoteCountsPickedItemsUpToRequired() {
        let note = SampleData.deliveryNote("DN-24817")!

        #expect(note.itemCount == 14)
        #expect(note.pickedCount == 9)
    }

    @Test func stockDeliveryItemShortfall() {
        let item = StockDeliveryItem(skuReference: "SKU-1", name: "Item", expected: 120, received: 108)

        #expect(item.isComplete == false)
        #expect(item.shortfall == 12)
    }

    @Test func stockDeliveryCountsCheckedItems() {
        #expect(SampleData.stockDelivery("SD-1093")?.checkedCount == 2)
    }

    @Test func skuStockValue() {
        #expect(SampleData.sku("SKU-00733")?.stockValue == Decimal(string: "34.2"))
    }

    @Test func locationWeightUsageIsCapped() {
        let location = SampleData.location("A-03-02")!

        #expect(location.weightUsage == 0.48)
    }

    @Test func staffInitials() {
        #expect(StaffMember(name: "Alex Morgan").initials == "AM")
    }

    @Test func primaryActionFollowsWebLabels() {
        #expect(DeliveryNoteState.unassigned.primaryAction == "I will pick this")
        #expect(DeliveryNoteState.handling.primaryAction == "Set as packed")
        #expect(DeliveryNoteState.dispatched.primaryAction == nil)
    }

    @Test func formatsMoneyAndChanges() {
        #expect(Decimal(string: "1.85")!.poundsFormatted == "£1.85")
        #expect(Decimal(2140).wholePoundsFormatted == "£2,140")
        #expect((-6).signedChange == "−6")
        #expect(120.signedChange == "+120")
    }

    @Test func onlyCorrectScansAreCounted() {
        #expect(ScanFeedback.correct(sku: "SKU-1", progress: "1 of 2").isCounted)
        #expect(ScanFeedback.wrongItem(scanned: "A", expected: "B").isCounted == false)
        #expect(ScanFeedback.unknownBarcode("123").isCounted == false)
    }

    @Test func everyRoutedReferenceInSampleDataResolves() {
        for task in SampleData.assignedTasks {
            switch task.kind {
            case .deliveryNote: #expect(SampleData.deliveryNote(task.reference) != nil)
            case .stockDelivery: #expect(SampleData.stockDelivery(task.reference) != nil)
            case .stockAudit: #expect(SampleData.location(task.reference) != nil)
            }
        }
        for sku in SampleData.skus {
            for location in sku.locations {
                #expect(SampleData.location(location.locationCode) != nil)
            }
        }
    }
}

@MainActor
struct AppRouterTests {
    @Test func selectingTabClearsPath() {
        let router = AppRouter()
        router.open(.sku("SKU-00412"))
        router.open(.location("A-03-02"))

        router.select(.goodsOut)

        #expect(router.path.isEmpty)
        #expect(router.selectedTab == .goodsOut)
    }

    @Test func backPopsOneRoute() {
        let router = AppRouter()
        router.open(.warehouseMenu)
        router.open(.locations)

        router.back()

        #expect(router.path == [.warehouseMenu])
    }

    @Test func backOnRootDoesNothing() {
        let router = AppRouter()
        router.back()

        #expect(router.path.isEmpty)
    }

    @Test func scanPresentsContext() {
        let router = AppRouter()
        router.scan(ScanContext(title: "Goods in", reference: "SD-1093"))

        #expect(router.scanContext?.reference == "SD-1093")
    }
}
