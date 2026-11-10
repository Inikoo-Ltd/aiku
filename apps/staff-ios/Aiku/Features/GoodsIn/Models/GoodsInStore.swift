import Foundation
import Observation

@MainActor
@Observable
final class GoodsInStore {
    static let shared = GoodsInStore()

    var deliveries: [GoodsInDelivery]
    var returns: [GoodsInReturn]
    var filter = GoodsInDeliveryFilter()
    var listScope: GoodsInListScope = .state(.received)

    init(deliveries: [GoodsInDelivery] = GoodsInSampleData.deliveries(), returns: [GoodsInReturn] = GoodsInSampleData.returns()) {
        self.deliveries = deliveries
        self.returns = returns
    }

    var visibleDeliveries: [GoodsInDelivery] {
        deliveries.filter { $0.state != .withdrawn }
    }

    func delivery(_ reference: String) -> GoodsInDelivery {
        deliveries.first { $0.reference == reference } ?? deliveries[0]
    }

    func goodsReturn(_ reference: String) -> GoodsInReturn {
        returns.first { $0.reference == reference } ?? returns[0]
    }

    func count(in scope: GoodsInListScope) -> Int {
        visibleDeliveries.filter(scope.contains).count
    }

    func listedDeliveries(now: Date = .now) -> [GoodsInDelivery] {
        filter.apply(to: visibleDeliveries.filter(listScope.contains), now: now)
    }

    func otherActiveDeliveries(now: Date = .now) -> [GoodsInDelivery] {
        guard case .state = listScope, filter.activeCount == 0 else { return [] }
        let others = visibleDeliveries.filter { $0.state.isActiveWork && !listScope.contains($0) }
        return filter.sorted(others)
    }

    func filteredCount(_ draft: GoodsInDeliveryFilter, now: Date = .now) -> Int {
        draft.apply(to: visibleDeliveries, now: now).count
    }

    func applyFilter(_ draft: GoodsInDeliveryFilter) {
        filter = draft
        if draft.activeCount > 0 {
            listScope = .all
        }
    }

    func backlogCounts(for sources: Set<GoodsInSource>, now: Date = .now) -> GoodsInBacklogCounts {
        GoodsInBacklogCounts.count(deliveries.filter { sources.contains($0.source) }, now: now)
    }

    var returnsBacklog: GoodsInBacklogCounts {
        var counts = GoodsInBacklogCounts()
        for item in returns {
            switch item.state {
            case .received, .returning: counts.received += 1
            case .returned, .done: counts.bookedInLastWeek += 1
            }
        }
        return counts
    }

    func nextDeliveryNeedingWork(after reference: String) -> GoodsInDelivery? {
        let order: [GoodsInDeliveryState] = [.bookingIn, .checked, .received, .arriving]
        for state in order {
            if let match = deliveries.first(where: { $0.reference != reference && $0.state == state }) {
                return match
            }
        }
        return nil
    }

    private func update(_ reference: String, _ change: (inout GoodsInDelivery) -> Void) {
        guard let index = deliveries.firstIndex(where: { $0.reference == reference }) else { return }
        change(&deliveries[index])
    }

    private func updateReturn(_ reference: String, _ change: (inout GoodsInReturn) -> Void) {
        guard let index = returns.firstIndex(where: { $0.reference == reference }) else { return }
        change(&returns[index])
    }

    func markAsReceived(_ reference: String, now: Date = .now) {
        update(reference) { $0.markAsReceived(label: "today \(GoodsInDates.time(now))") }
    }

    func unmarkAsReceived(_ reference: String) {
        update(reference) { $0.unmarkAsReceived() }
    }

    func unmarkAsDispatched(_ reference: String) {
        update(reference) { $0.unmarkAsDispatched() }
    }

    func cancel(_ reference: String) {
        update(reference) { $0.cancel() }
    }

    func setAsPlaced(_ reference: String) {
        update(reference) { $0.setAsPlaced() }
    }

    func startBookingIn(_ reference: String) {
        update(reference) { $0.startBookingIn() }
    }

    func setChecked(_ reference: String, sko: String, quantity: Int) {
        update(reference) { $0.setChecked(sko, quantity: quantity) }
    }

    func registerScan(_ reference: String, code: String) -> GoodsInScanResult {
        var result = GoodsInScanResult.notOnDelivery(code: code)
        update(reference) { result = $0.registerScan(code) }
        return result
    }

    @discardableResult
    func place(_ reference: String, sko: String, quantity: Int, in location: String, setAsPicking: Bool = false, now: Date = .now) -> Int {
        var placed = 0
        update(reference) { placed = $0.place(sko, quantity: quantity, in: location, setAsPicking: setAsPicking, now: now) }
        return placed
    }

    func recordCheck(_ reference: String, checkID: String, passed: Bool, note: String, by name: String, now: Date = .now) {
        update(reference) { $0.recordCheck(checkID, passed: passed, note: note, by: name, at: GoodsInDates.time(now)) }
    }

    func processReturnItem(_ reference: String, sko: String, outcome: GoodsInReturnOutcome) {
        updateReturn(reference) { $0.process(sko, outcome: outcome) }
    }

    func setReturnAsReturned(_ reference: String) {
        updateReturn(reference) { $0.setAsReturned() }
    }
}
