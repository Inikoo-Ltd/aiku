import Foundation

enum GoodsInRemote {
    static func putLabel(_ type: String) -> String {
        switch type {
        case "sow": "Returned"
        case "damaged": "Damaged"
        case "not-sow": "Not returned"
        default: type
        }
    }

    static func state(_ remote: String) -> GoodsInDeliveryState {
        switch remote {
        case "dispatched": .arriving
        case "received": .received
        case "checked": .checked
        case "booking_in": .bookingIn
        case "booked_in": .bookedIn
        case "placed": .placed
        case "not_received": .notReceived
        case "cancelled": .cancelled
        default: .received
        }
    }

    static func remoteState(_ state: GoodsInDeliveryState) -> String? {
        switch state {
        case .arriving: "dispatched"
        case .received: "received"
        case .checked: "checked"
        case .bookingIn: "booking_in"
        case .bookedIn: "booked_in"
        case .placed: "placed"
        case .notReceived: "not_received"
        case .cancelled: "cancelled"
        case .withdrawn: nil
        }
    }

    static func phase(_ state: GoodsInDeliveryState) -> GoodsInDeliveryPhase {
        switch state {
        case .arriving: .dispatched
        case .placed: .placed
        case .cancelled: .cancelled
        case .withdrawn: .withdrawn
        case .received, .checked, .bookingIn, .bookedIn, .notReceived: .inGoodsIn
        }
    }

    static func source(_ supplier: MobileGoodsInParty?, fallback: String?) -> GoodsInSource {
        switch supplier?.type {
        case "OrgAgent": return .agent
        case "OrgPartner": return .partner
        case "Production": return .production
        default:
            switch fallback {
            case "agents": return .agent
            case "partners": return .partner
            default: return .supplier
            }
        }
    }

    static func remoteSource(_ source: GoodsInSource) -> String {
        switch source {
        case .supplier, .production: "suppliers"
        case .agent: "agents"
        case .partner: "partners"
        }
    }

    static func remoteDispatched(_ window: GoodsInDispatchWindow) -> String {
        switch window {
        case .today: "today"
        case .thisWeek: "week"
        case .older: "older"
        }
    }

    static func remoteSort(_ sort: GoodsInDeliverySort) -> String? {
        switch sort {
        case .dispatchedDate: "-dispatched_at"
        case .reference: "reference"
        case .supplier: nil
        case .items: "-number_items"
        }
    }

    static func returnState(_ remote: String) -> GoodsInReturnState {
        switch remote {
        case "returning": .returning
        case "returned": .returned
        case "done", "cancelled": .done
        default: .received
        }
    }

    static func skos(_ value: OptionalFlexibleDouble?) -> Double {
        GoodsInQuantity.clean(value?.value ?? 0)
    }

    static func packedIn(_ value: OptionalFlexibleDouble?) -> Int {
        max(Int((value?.value ?? 1).rounded()), 1)
    }

    static func date(_ iso: String?) -> Date? {
        InventoryLive.date(iso)
    }
}

extension GoodsInDelivery {
    init(row: MobileStockDeliveryRow) {
        let state = GoodsInRemote.state(row.state)
        self.init(
            reference: row.reference,
            source: GoodsInRemote.source(row.supplier, fallback: row.source),
            sourceName: row.supplier?.name ?? row.supplier?.code ?? "",
            phase: GoodsInRemote.phase(state),
            dispatchedAt: GoodsInRemote.date(row.dispatchedAt) ?? .distantPast,
            receivedLabel: GoodsInRemote.date(row.receivedAt).map(GoodsInDates.dayMonth),
            items: []
        )
        remoteID = row.id
        remoteState = state
        remoteItemCount = row.numberItems
        remoteCheckedCount = row.numberChecked
        remotePlacedCount = row.numberPlaced
        isDetailLoaded = false
        orderer = [row.supplier?.name, row.supplier?.code].compactMap { $0 }.joined(separator: " · ")
        purchaseOrders = ""
        estimatedReceiving = GoodsInRemote.date(row.estimatedReceivingDate).map { "Estimated receiving \(GoodsInDates.dayMonth($0))" } ?? ""
    }

    init(detail: MobileStockDeliveryDetail) {
        let state = GoodsInRemote.state(detail.state)
        self.init(
            reference: detail.reference,
            source: GoodsInRemote.source(detail.supplier, fallback: detail.source),
            sourceName: detail.supplier?.name ?? detail.supplier?.code ?? "",
            phase: GoodsInRemote.phase(state),
            dispatchedAt: GoodsInRemote.date(detail.dispatchedAt) ?? .distantPast,
            receivedLabel: GoodsInRemote.date(detail.receivedAt).map(GoodsInDates.dayMonth),
            items: detail.items.map(GoodsInItem.init(remote:))
        )
        remoteID = detail.id
        remoteState = state
        remoteItemCount = detail.items.count
        remoteActions = Set((detail.availableActions ?? []).map(\.key))
        isDetailLoaded = true
        orderer = [detail.supplier?.name, detail.supplier?.code].compactMap { $0 }.joined(separator: " · ")
        let orders = (detail.purchaseOrders ?? []).map(\.reference)
        purchaseOrders = orders.isEmpty ? "" : "Purchase orders \(orders.joined(separator: ", "))"
        estimatedReceiving = GoodsInRemote.date(detail.estimatedReceivingDate).map { "Estimated receiving \(GoodsInDates.dayMonth($0))" } ?? ""
        deliveryFacts = Self.facts(detail.delivery, agentReference: detail.agentReference)
        checks = (detail.checklist?.sections ?? []).flatMap(\.checks).map(GoodsInDeliveryCheck.init(remote:))
        incidentTypes = (detail.checklist?.incidentTypes ?? []).map { GoodsInIncidentType(id: $0.value, label: $0.label) }
    }

    private static func facts(_ delivery: MobileStockDeliveryDetail.Delivery?, agentReference: String?) -> [GoodsInFact] {
        let pairs: [(String, String?)] = [
            ("Agent reference", agentReference),
            ("Type", delivery?.typeLabel),
            ("Container", delivery?.containerSize),
            ("Incoterm", delivery?.incoterm),
            ("Port of export", delivery?.portOfExport),
            ("Port of import", delivery?.portOfImport),
            ("Shipper reference", delivery?.shipperReference),
            ("Tracking", delivery?.trackingNumber),
            ("Container number", delivery?.containerNumber),
            ("Shipping agent", delivery?.shippingAgent),
        ]
        return pairs.compactMap { label, value in
            guard let value, !value.isEmpty else { return nil }
            return GoodsInFact(label, value)
        }
    }
}

extension GoodsInItem {
    init(remote: MobileStockDeliveryItem) {
        let code = remote.sko?.code ?? "#\(remote.id)"
        let picking = (remote.locations ?? []).filter { $0.type == "picking" }
        let options = (remote.locations ?? []).map { location in
            GoodsInLocationOption(
                code: location.location.code,
                detail: [location.type?.capitalized, "\(GoodsInQuantity.text(GoodsInRemote.skos(location.quantity), packedIn: GoodsInRemote.packedIn(remote.sko?.packedIn))) in location"].compactMap { $0 }.joined(separator: " · ")
            )
        }
        self.init(
            sko: code,
            name: remote.sko?.name ?? "",
            delivered: GoodsInRemote.skos(remote.skoExpected),
            checked: remote.skoChecked?.value.map(GoodsInQuantity.clean),
            placements: (remote.placements ?? []).compactMap { placement in
                guard let location = placement.location else { return nil }
                return GoodsInPlacement(location: location.code, quantity: GoodsInRemote.skos(placement.quantity))
            },
            pickingLocation: picking.first?.location.code,
            pickingSummary: picking.first.map { "\($0.location.code) for \($0.isDefaultDropshipping == true && $0.isDefaultWholesale != true ? "Dropshipping" : "Wholesale")" },
            locationOptions: options,
            suggestedLocation: options.first,
            isNewSko: remote.isNewOrgStock ?? false
        )
        remoteID = remote.id
        packedIn = GoodsInRemote.packedIn(remote.sko?.packedIn)
        locationLinks = Dictionary((remote.locations ?? []).map { ($0.location.code, $0.locationOrgStockId) }, uniquingKeysWith: { first, _ in first })
    }
}

extension GoodsInDeliveryCheck {
    init(remote: MobileStockDeliveryChecklist.Check) {
        let result: GoodsInCheckResult = switch remote.result {
        case "passed": .passed
        case "failed": .failed
        default: .toDo
        }
        let detail = [remote.checkedBy?.name, GoodsInRemote.date(remote.checkedAt).map(GoodsInDates.dayMonth)].compactMap { $0 }.joined(separator: " · ")
        self.init(id: remote.key, title: remote.label, result: result, detail: result == .toDo ? (remote.detail ?? "Pass or fail") : detail)
    }
}

extension GoodsInReturn {
    init(row: MobileReturnRow) {
        self.init(reference: row.reference, customer: row.customer?.name ?? "", items: [])
        remoteID = row.id
        remoteState = GoodsInRemote.returnState(row.state)
        remoteItemCount = row.numberItems
        remoteHandledCount = row.numberHandled
        isDetailLoaded = false
    }

    init(detail: MobileReturnDetail) {
        self.init(reference: detail.reference, customer: detail.customer?.name ?? "", items: detail.items.map(GoodsInReturnItem.init(remote:)))
        remoteID = detail.id
        remoteState = GoodsInRemote.returnState(detail.state)
        remoteItemCount = detail.items.count
        remoteActions = Set((detail.availableActions ?? []).map(\.key))
        isDetailLoaded = true
    }
}

extension GoodsInReturnItem {
    init(remote: MobileReturnItem) {
        let home = (remote.locations ?? []).first { $0.type == "picking" } ?? remote.locations?.first
        let outcome: GoodsInReturnOutcome? = {
            if GoodsInRemote.skos(remote.returned) > GoodsInQuantity.tolerance {
                return .toLocation(remote.puts?.first { $0.type == "sow" }?.location?.code ?? home?.location.code ?? "")
            }
            if GoodsInRemote.skos(remote.damaged) > GoodsInQuantity.tolerance { return .damaged }
            if GoodsInRemote.skos(remote.notReturned) > GoodsInQuantity.tolerance { return .notReturned }
            return nil
        }()
        self.init(
            sko: remote.sko?.code ?? "#\(remote.id)",
            name: remote.sko?.name ?? "",
            quantity: GoodsInRemote.skos(remote.expected),
            homeLocation: home?.location.code ?? "",
            outcome: remote.isHandled == true || (remote.remaining?.value ?? 1) <= 0 ? outcome : nil
        )
        remoteID = remote.id
        homeLinkID = home?.locationOrgStockId
        packedIn = GoodsInRemote.packedIn(remote.sko?.packedIn)
        let code = sko
        let pack = packedIn
        puts = (remote.puts ?? []).map { put in
            GoodsInReturnPut(
                id: put.id,
                sko: code,
                kind: put.type,
                label: put.typeLabel ?? GoodsInRemote.putLabel(put.type),
                quantity: GoodsInRemote.skos(put.quantity),
                location: put.location?.code,
                packedIn: pack
            )
        }
    }
}
