import Foundation
import Observation

@MainActor
@Observable
final class FulfilmentStore {
    static let shared = FulfilmentStore()

    private(set) var deliveries: [FulfilmentDelivery]
    private(set) var pallets: [FulfilmentPallet]
    private(set) var incidents: [FulfilmentIncident]
    private(set) var returns: [FulfilmentReturn]
    private(set) var audits: [FulfilmentAudit]
    let storedItems: [FulfilmentStoredItem]
    let storageLocations: [String]
    private var nextAuditNumber = 15

    init(
        deliveries: [FulfilmentDelivery] = FulfilmentSampleData.deliveries,
        pallets: [FulfilmentPallet] = FulfilmentSampleData.pallets,
        incidents: [FulfilmentIncident] = FulfilmentSampleData.incidents,
        returns: [FulfilmentReturn] = FulfilmentSampleData.returns,
        audits: [FulfilmentAudit] = FulfilmentSampleData.audits,
        storedItems: [FulfilmentStoredItem] = FulfilmentSampleData.storedItems,
        storageLocations: [String] = FulfilmentSampleData.storageLocations
    ) {
        self.deliveries = deliveries
        self.pallets = pallets
        self.incidents = incidents
        self.returns = returns
        self.audits = audits
        self.storedItems = storedItems
        self.storageLocations = storageLocations
    }


    func delivery(_ reference: String) -> FulfilmentDelivery? {
        deliveries.first { $0.reference == reference } ?? deliveries.first
    }

    func deliveries(in state: FulfilmentDeliveryState) -> [FulfilmentDelivery] {
        deliveries.filter { $0.state == state }
    }

    func deliveryCount(_ state: FulfilmentDeliveryState) -> Int {
        deliveries(in: state).count
    }

    func pallets(of delivery: FulfilmentDelivery) -> [FulfilmentPallet] {
        delivery.palletReferences.compactMap { reference in pallets.first { $0.reference == reference } }
    }

    func deliverySubtitle(_ delivery: FulfilmentDelivery) -> String {
        let items = pallets(of: delivery)
        var parts = [delivery.customer]
        switch delivery.state {
        case .confirmed, .received:
            var byType: [FulfilmentItemType: Int] = [:]
            var order: [FulfilmentItemType] = []
            for item in items {
                if byType[item.type] == nil { order.append(item.type) }
                byType[item.type, default: 0] += 1
            }
            parts.append(order.map { $0.counted(byType[$0] ?? 0) }.joined(separator: ", "))
            if let note = delivery.arrivalNote, delivery.state == .confirmed {
                parts.append(note)
            }
        case .bookingIn:
            let booked = items.filter { $0.state == .bookedIn }.count
            parts.append("\(booked) of \(items.count) pallets booked in")
        case .bookedIn:
            parts.append("\(FulfilmentItemType.pallet.counted(items.filter { $0.state == .storing }.count)) booked in")
        case .notReceived:
            parts.append("not received")
        }
        return parts.joined(separator: " · ")
    }

    func receive(_ reference: String) {
        updateDelivery(reference) { delivery in
            guard delivery.state == .confirmed else { return }
            delivery.state = .received
            setPalletStates(delivery.palletReferences, to: .received)
        }
    }

    func setDeliveryNotReceived(_ reference: String) {
        updateDelivery(reference) { delivery in
            guard delivery.state == .confirmed || delivery.state == .received else { return }
            delivery.state = .notReceived
            setPalletStates(delivery.palletReferences, to: .notReceived)
        }
    }

    func startBookingIn(_ reference: String) {
        updateDelivery(reference) { delivery in
            guard delivery.state == .received else { return }
            delivery.state = .bookingIn
            setPalletStates(delivery.palletReferences, to: .bookingIn)
        }
    }

    func currentBookingPallet(in reference: String) -> FulfilmentPallet? {
        guard let delivery = delivery(reference), delivery.state == .bookingIn else { return nil }
        return pallets(of: delivery).first { $0.state == .bookingIn }
    }

    func suggestedLocation() -> String {
        let used = Set(pallets.compactMap(\.location))
        return storageLocations.first { !used.contains($0) } ?? storageLocations.first ?? "D-02-04"
    }

    func bookIn(_ palletReference: String, at location: String?) {
        updatePallet(palletReference) { pallet in
            guard pallet.state == .bookingIn else { return }
            pallet.state = .bookedIn
            pallet.location = location
        }
    }

    func setPalletNotReceived(_ palletReference: String) {
        updatePallet(palletReference) { pallet in
            guard pallet.state == .bookingIn else { return }
            pallet.state = .notReceived
            pallet.location = nil
        }
    }

    func undoBooking(_ palletReference: String) {
        updatePallet(palletReference) { pallet in
            guard pallet.state == .bookedIn || pallet.state == .notReceived else { return }
            pallet.state = .bookingIn
            pallet.location = nil
        }
    }

    func canSetBookedIn(_ reference: String) -> Bool {
        guard let delivery = delivery(reference), delivery.state == .bookingIn else { return false }
        let items = pallets(of: delivery)
        return items.allSatisfy { $0.state != .bookingIn } && items.contains { $0.state == .bookedIn }
    }

    func setBookedIn(_ reference: String) {
        guard canSetBookedIn(reference) else { return }
        updateDelivery(reference) { delivery in
            delivery.state = .bookedIn
            for palletReference in delivery.palletReferences {
                updatePallet(palletReference) { pallet in
                    if pallet.state == .bookedIn { pallet.state = .storing }
                }
            }
        }
    }

    func storedCount(of reference: String) -> Int {
        guard let delivery = delivery(reference) else { return 0 }
        return pallets(of: delivery).filter { $0.state == .storing }.count
    }


    func pallet(_ reference: String) -> FulfilmentPallet? {
        pallets.first { $0.reference == reference } ?? pallets.first { $0.state == .storing } ?? pallets.first
    }

    func pallets(matching filter: FulfilmentPalletFilter) -> [FulfilmentPallet] {
        pallets.filter { filter.matches($0.state) }
    }

    func palletCount(_ filter: FulfilmentPalletFilter) -> Int {
        pallets(matching: filter).count
    }

    func storedItem(_ code: String) -> FulfilmentStoredItem? {
        storedItems.first { $0.code == code }
    }

    func move(_ palletReference: String, to location: String) {
        updatePallet(palletReference) { pallet in
            guard pallet.state == .storing else { return }
            pallet.location = location
        }
    }

    func reportIncident(
        _ palletReference: String,
        kind: FulfilmentIncidentKind,
        note: String,
        reportedBy: String,
        date: String,
        returnReference: String? = nil
    ) {
        guard pallets.contains(where: { $0.reference == palletReference }) else { return }
        updatePallet(palletReference) { pallet in
            pallet.state = kind.palletState
            pallet.location = nil
        }
        incidents.removeAll { $0.palletReference == palletReference }
        let incident = FulfilmentIncident(
            palletReference: palletReference, kind: kind, note: note.trimmingCharacters(in: .whitespacesAndNewlines),
            reportedBy: reportedBy, date: date, returnReference: returnReference
        )
        incidents.insert(incident, at: 0)
    }

    func incident(for palletReference: String) -> FulfilmentIncident? {
        incidents.first { $0.palletReference == palletReference }
    }

    func setBackToStoring(_ palletReference: String) {
        guard incidents.contains(where: { $0.palletReference == palletReference }) else { return }
        incidents.removeAll { $0.palletReference == palletReference }
        updatePallet(palletReference) { pallet in
            pallet.state = .storing
            pallet.location = nil
        }
    }


    var storedItemSummaries: [FulfilmentStoredItemSummary] {
        storedItems.compactMap { item in
            let holding = pallets.filter { $0.state.holdsStock && $0.quantity(of: item.code) > 0 }
            guard !holding.isEmpty else { return nil }
            let total = holding.reduce(0) { $0 + $1.quantity(of: item.code) }
            return FulfilmentStoredItemSummary(item: item, quantity: total, palletReferences: holding.map(\.reference))
        }
    }


    func audit(forPallet palletReference: String) -> FulfilmentAudit {
        let target = pallet(palletReference)?.reference ?? palletReference
        if let existing = audits.first(where: { $0.palletReference == target || $0.reference == palletReference }) {
            return existing
        }
        let prefix = target.split(separator: "-").first.map(String.init) ?? "audit"
        let reference = "\(prefix)-sia" + String(format: "%03d", nextAuditNumber)
        let lines = (pallet(target)?.items ?? []).map { FulfilmentAuditLine(itemCode: $0.itemCode, expected: $0.quantity) }
        return FulfilmentAudit(reference: reference, palletReference: target, lines: lines)
    }

    private func ensureAudit(_ palletReference: String) -> Int? {
        let current = audit(forPallet: palletReference)
        if let index = audits.firstIndex(where: { $0.reference == current.reference }) {
            return index
        }
        audits.append(current)
        nextAuditNumber += 1
        return audits.count - 1
    }

    func count(_ itemCode: String, by amount: Int, inAuditOf palletReference: String) {
        guard amount > 0 else { return }
        updateAudit(palletReference) { audit in
            guard let index = audit.lines.firstIndex(where: { $0.itemCode == itemCode }) else { return }
            audit.lines[index].counted = (audit.lines[index].counted ?? 0) + amount
            audit.currentItemCode = itemCode
        }
    }

    func countAll(_ itemCode: String, inAuditOf palletReference: String) {
        updateAudit(palletReference) { audit in
            guard let index = audit.lines.firstIndex(where: { $0.itemCode == itemCode }) else { return }
            audit.lines[index].counted = max(audit.lines[index].counted ?? 0, audit.lines[index].expected)
            audit.currentItemCode = itemCode
        }
    }

    @discardableResult
    func scanNextAuditItem(palletReference: String) -> String? {
        let current = audit(forPallet: palletReference)
        guard let code = current.nextItemCode else { return nil }
        count(code, by: 1, inAuditOf: palletReference)
        return code
    }

    @discardableResult
    func addUnlistedItem(toAuditOf palletReference: String) -> String? {
        let current = audit(forPallet: palletReference)
        guard let pallet = pallet(current.palletReference),
              let item = storedItems.first(where: { item in
                  item.customer == pallet.customer && !current.lines.contains { $0.itemCode == item.code }
              }) else { return nil }
        updateAudit(palletReference) { audit in
            audit.lines.append(FulfilmentAuditLine(itemCode: item.code, expected: 0, counted: 1))
            audit.currentItemCode = item.code
        }
        return item.code
    }

    func completeAudit(ofPallet palletReference: String) {
        let current = audit(forPallet: palletReference)
        updatePallet(current.palletReference) { pallet in
            for line in current.lines {
                guard let counted = line.counted else { continue }
                if let index = pallet.items.firstIndex(where: { $0.itemCode == line.itemCode }) {
                    pallet.items[index].quantity = counted
                } else {
                    pallet.items.append(FulfilmentStoredQuantity(itemCode: line.itemCode, quantity: counted))
                }
            }
        }
        audits.removeAll { $0.reference == current.reference }
    }


    func returnRecord(_ reference: String) -> FulfilmentReturn? {
        returns.first { $0.reference == reference } ?? returns.first
    }

    func returns(matching filter: FulfilmentReturnFilter) -> [FulfilmentReturn] {
        returns.filter(filter.matches)
    }

    func returnCount(_ filter: FulfilmentReturnFilter) -> Int {
        returns(matching: filter).count
    }

    func pickPallet(_ palletReference: String, inReturn reference: String) {
        updateReturn(reference) { record in
            guard !record.isDispatched,
                  let index = record.palletLines.firstIndex(where: { $0.palletReference == palletReference && $0.state == .toPick }) else { return }
            record.palletLines[index].state = .picked
        }
    }

    func setNotPicked(
        _ palletReference: String,
        inReturn reference: String,
        kind: FulfilmentIncidentKind,
        note: String,
        reportedBy: String,
        date: String
    ) {
        var changed = false
        updateReturn(reference) { record in
            guard !record.isDispatched,
                  let index = record.palletLines.firstIndex(where: { $0.palletReference == palletReference && $0.state == .toPick }) else { return }
            record.palletLines[index].state = .notPicked
            record.palletLines[index].incident = kind
            changed = true
        }
        guard changed else { return }
        reportIncident(palletReference, kind: kind, note: note, reportedBy: reportedBy, date: date, returnReference: reference)
    }

    func pickItems(_ lineID: String, amount: Int, inReturn reference: String) {
        guard amount > 0 else { return }
        updateReturn(reference) { record in
            guard !record.isDispatched, let index = record.itemLines.firstIndex(where: { $0.id == lineID }) else { return }
            let line = record.itemLines[index]
            record.itemLines[index].picked = min(line.target, line.picked + amount)
        }
    }

    @discardableResult
    func scanNextReturnItem(inReturn reference: String) -> String? {
        guard let line = returnRecord(reference)?.currentItemLine, line.remaining > 0 else { return nil }
        pickItems(line.id, amount: 1, inReturn: reference)
        return line.itemCode
    }

    func alternativePallet(for lineID: String, inReturn reference: String) -> FulfilmentPallet? {
        guard let record = returnRecord(reference), let line = record.itemLines.first(where: { $0.id == lineID }) else { return nil }
        let used = Set(record.itemLines.filter { $0.itemCode == line.itemCode }.map(\.palletReference))
        return pallets.first { $0.state == .storing && !used.contains($0.reference) && $0.quantity(of: line.itemCode) > 0 }
    }

    func pickFromAnotherPallet(_ lineID: String, inReturn reference: String) {
        guard let alternative = alternativePallet(for: lineID, inReturn: reference) else {
            updateReturn(reference) { record in
                guard let index = record.itemLines.firstIndex(where: { $0.id == lineID }) else { return }
                record.itemLines[index].required = record.itemLines[index].picked
            }
            return
        }
        updateReturn(reference) { record in
            guard let index = record.itemLines.firstIndex(where: { $0.id == lineID }) else { return }
            let line = record.itemLines[index]
            let missing = line.required - line.picked
            guard missing > 0 else { return }
            record.itemLines[index].required = line.picked
            let newLine = FulfilmentReturnItemLine(
                itemCode: line.itemCode, palletReference: alternative.reference, location: alternative.location,
                required: missing, available: alternative.quantity(of: line.itemCode)
            )
            record.itemLines.append(newLine)
        }
    }

    func setDeparture(_ departure: FulfilmentDeparture, forReturn reference: String) {
        updateReturn(reference) { record in
            guard record.state == .picked else { return }
            record.departure = departure
        }
    }

    func createShipment(forReturn reference: String) {
        updateReturn(reference) { record in
            guard record.state == .picked, record.departure == .shipment, record.shipment == nil else { return }
            record.shipment = FulfilmentSampleData.carrierShipment
        }
    }

    func canDispatch(_ reference: String) -> Bool {
        guard let record = returnRecord(reference), record.state == .picked else { return false }
        return record.departure == .collection || record.shipment != nil
    }

    func dispatch(_ reference: String) {
        guard canDispatch(reference), let record = returnRecord(reference) else { return }
        updateReturn(record.reference) { $0.isDispatched = true }
        if record.type == .pallets {
            setPalletStates(record.pickedPalletReferences, to: .returned)
        } else {
            for line in record.itemLines where line.picked > 0 {
                updatePallet(line.palletReference) { pallet in
                    guard let index = pallet.items.firstIndex(where: { $0.itemCode == line.itemCode }) else { return }
                    pallet.items[index].quantity = max(0, pallet.items[index].quantity - line.picked)
                }
            }
        }
    }


    private func updateDelivery(_ reference: String, _ change: (inout FulfilmentDelivery) -> Void) {
        guard let index = deliveries.firstIndex(where: { $0.reference == reference }) else { return }
        change(&deliveries[index])
    }

    private func updatePallet(_ reference: String, _ change: (inout FulfilmentPallet) -> Void) {
        guard let index = pallets.firstIndex(where: { $0.reference == reference }) else { return }
        change(&pallets[index])
    }

    private func updateReturn(_ reference: String, _ change: (inout FulfilmentReturn) -> Void) {
        guard let index = returns.firstIndex(where: { $0.reference == reference }) else { return }
        change(&returns[index])
    }

    private func updateAudit(_ palletReference: String, _ change: (inout FulfilmentAudit) -> Void) {
        guard let index = ensureAudit(palletReference) else { return }
        change(&audits[index])
    }

    private func setPalletStates(_ references: [String], to state: FulfilmentPalletState) {
        for reference in references {
            updatePallet(reference) { $0.state = state }
        }
    }
}
