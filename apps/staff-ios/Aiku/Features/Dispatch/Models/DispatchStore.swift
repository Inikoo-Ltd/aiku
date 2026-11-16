import Foundation
import Observation

@MainActor
@Observable
final class DispatchStore {
    static let shared = DispatchStore(service: .live(.shared))

    let shippers: [DispatchShipper]
    private(set) var reports: [DispatchProblemReport] = []
    private(set) var trolleys: [MobileSessionTrolley] = []
    private(set) var bays: [MobileSessionPickedBay] = []
    private(set) var trolleysState = DispatchListState()
    private(set) var baysState = DispatchListState()

    @ObservationIgnored private let service: PickingSessionService

    init(service: PickingSessionService, shippers: [DispatchShipper] = DispatchSampleData.shippers) {
        self.service = service
        self.shippers = shippers
    }

    func loadTrolleys(warehouseID: Int) async {
        if trolleysState.warehouseID != warehouseID { trolleys = [] }
        trolleysState.begin(warehouseID)
        do {
            trolleys = try await service.trolleys(warehouseID, false)
            trolleysState.finish()
        } catch where error.isCancellation {
            trolleysState.isLoading = false
        } catch {
            trolleysState.fail(PickingSessionErrorText.message(error, offline: "Trolleys need internet. Check Wi-Fi and try again."))
        }
    }

    func loadBays(warehouseID: Int) async {
        if baysState.warehouseID != warehouseID { bays = [] }
        baysState.begin(warehouseID)
        do {
            bays = try await service.pickedBays(warehouseID)
            baysState.finish()
        } catch where error.isCancellation {
            baysState.isLoading = false
        } catch {
            baysState.fail(PickingSessionErrorText.message(error, offline: "Picked bays need internet. Check Wi-Fi and try again."))
        }
    }

    func submit(_ report: DispatchProblemReport) {
        reports.append(report)
    }
}

struct DispatchListState: Equatable, Sendable {
    var warehouseID: Int?
    var isLoading = false
    var hasLoaded = false
    var error: String?

    mutating func begin(_ warehouseID: Int) {
        if self.warehouseID != warehouseID { hasLoaded = false }
        self.warehouseID = warehouseID
        isLoading = true
        error = nil
    }

    mutating func finish() {
        isLoading = false
        hasLoaded = true
    }

    mutating func fail(_ message: String) {
        isLoading = false
        error = message
    }
}
