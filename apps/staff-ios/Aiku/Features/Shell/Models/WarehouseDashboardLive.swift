import Foundation
import Observation

struct WarehouseDashboardService: Sendable {
    var dashboard: @Sendable (Int) async throws -> MobileWarehouseDashboard = { _ in throw APIError.unexpectedBody }

    static func live(_ client: APIClient) -> WarehouseDashboardService {
        WarehouseDashboardService(dashboard: { try await client.fetchWarehouseDashboard(warehouseID: $0) })
    }
}

@MainActor
@Observable
final class WarehouseDashboardStore {
    private(set) var dashboard: MobileWarehouseDashboard?
    private(set) var loadedWarehouseID: Int?
    private(set) var isLoading = false
    private(set) var loadError: String?
    @ObservationIgnored private let service: WarehouseDashboardService

    init(service: WarehouseDashboardService = .live(.shared)) {
        self.service = service
    }

    var isShowingSkeleton: Bool { dashboard == nil && loadError == nil }

    func load(warehouseID: Int?) async {
        guard let warehouseID else {
            dashboard = nil
            loadedWarehouseID = nil
            loadError = nil
            return
        }
        if loadedWarehouseID != warehouseID {
            dashboard = nil
            loadError = nil
            loadedWarehouseID = warehouseID
        }
        isLoading = true
        defer { isLoading = false }
        do {
            let fetched = try await service.dashboard(warehouseID)
            guard warehouseID == loadedWarehouseID else { return }
            dashboard = fetched
            loadError = nil
        } catch where error.isCancellation {
            return
        } catch {
            guard warehouseID == loadedWarehouseID else { return }
            loadError = PickingSessionErrorText.message(error)
        }
    }
}

struct WarehouseDashboardTile: Identifiable, Hashable, Sendable {
    let id: String
    let label: String
    let value: Int
    let tone: Tone
    let link: ShellLink
}

struct WarehouseDashboardFigure: Hashable, Sendable {
    let label: String
    let value: String
    var link: ShellLink?
}

enum WarehouseDashboardText {
    static let maxTilesPerRow = 4

    static func columns(for tileCount: Int) -> Int {
        if tileCount <= 0 { return 1 }
        if tileCount <= maxTilesPerRow { return tileCount }
        return (tileCount + 1) / 2
    }

    static func goodsOutSubtitle(_ goodsOut: MobileWarehouseDashboard.GoodsOut) -> String {
        "Dispatching backlog · \(goodsOut.inWarehouse.grouped) in the warehouse"
    }

    static let goodsInSubtitle = "Incoming hub"

    static func inventorySubtitle(_ inventory: MobileWarehouseDashboard.Inventory) -> String {
        let count = inventory.numberCurrentOrgStocks
        return "\(count.grouped) current \(count == 1 ? "SKO" : "SKOs")"
    }

    static func dispatched(_ goodsOut: MobileWarehouseDashboard.GoodsOut) -> WarehouseDashboardFigure? {
        guard let dispatched = goodsOut.dispatchedToday else { return nil }
        let today = "\(dispatched.count.grouped) today"
        let value = dispatched.yesterdayByNow.map { "\(today) · \($0.grouped) yesterday by now" } ?? today
        return WarehouseDashboardFigure(label: "Dispatched", value: value)
    }

    static func returnsToProcess(_ goodsIn: MobileWarehouseDashboard.GoodsIn) -> WarehouseDashboardFigure? {
        guard let count = goodsIn.returnsToProcess else { return nil }
        return WarehouseDashboardFigure(label: "Returns to process", value: count.grouped, link: .goodsIn(.returnsIn(state: .toProcess)))
    }

    static func tiles(_ goodsOut: MobileWarehouseDashboard.GoodsOut) -> [WarehouseDashboardTile] {
        let tiles: [WarehouseDashboardTile?] = [
            WarehouseDashboardTile(id: "unassigned", label: "To do", value: goodsOut.unassigned, tone: .neutral, link: .goodsOut(.deliveryNotesIn(state: .todo))),
            WarehouseDashboardTile(id: "handling", label: "Picking", value: goodsOut.handling, tone: .info, link: .goodsOut(.deliveryNotesIn(state: .picking))),
            WarehouseDashboardTile(id: "handling_blocked", label: "Waiting", value: goodsOut.handlingBlocked, tone: toneWhenAny(goodsOut.handlingBlocked, .warn), link: .goodsOut(.deliveryNotesIn(state: .waiting))),
            goodsOut.picked.map { WarehouseDashboardTile(id: "picked", label: "To pack", value: $0, tone: .info, link: .goodsOut(.deliveryNotesIn(state: .picked))) },
            WarehouseDashboardTile(id: "packed", label: "Packed", value: goodsOut.packed, tone: .info, link: .goodsOut(.deliveryNotesIn(state: .packed)))
        ]
        return tiles.compactMap { $0 }
    }

    static func tiles(_ goodsIn: MobileWarehouseDashboard.GoodsIn) -> [WarehouseDashboardTile] {
        [
            WarehouseDashboardTile(id: "arriving", label: "Arriving", value: goodsIn.arriving, tone: .neutral, link: .goodsIn(.stockDeliveriesIn(state: .dispatched))),
            WarehouseDashboardTile(id: "received", label: "Received", value: goodsIn.received, tone: .info, link: .goodsIn(.stockDeliveriesIn(state: .received))),
            WarehouseDashboardTile(id: "checked", label: "Checked", value: goodsIn.checked, tone: .info, link: .goodsIn(.stockDeliveriesIn(state: .checked))),
            WarehouseDashboardTile(id: "booking_in", label: "Booking in", value: goodsIn.bookingIn, tone: .info, link: .goodsIn(.stockDeliveriesIn(state: .bookingIn)))
        ]
    }

    static func tiles(_ inventory: MobileWarehouseDashboard.Inventory) -> [WarehouseDashboardTile] {
        [
            WarehouseDashboardTile(id: "below_minimum", label: "Below minimum", value: inventory.belowMinimumStock, tone: toneWhenAny(inventory.belowMinimumStock, .warn), link: .inventory(.skosIn(scope: .belowMinimum))),
            WarehouseDashboardTile(id: "negative", label: "Negative stock", value: inventory.negativeStocks, tone: toneWhenAny(inventory.negativeStocks, .danger), link: .inventory(.skosIn(scope: .negative))),
            WarehouseDashboardTile(id: "abnormality", label: "Abnormal", value: inventory.numberAbnormalities, tone: toneWhenAny(inventory.numberAbnormalities, .danger), link: .inventory(.skosIn(scope: .abnormalities)))
        ]
    }

    private static func toneWhenAny(_ count: Int, _ tone: Tone) -> Tone {
        count > 0 ? tone : .neutral
    }
}
