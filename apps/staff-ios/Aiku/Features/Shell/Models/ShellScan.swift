import Foundation
import Observation

enum ShellScanKind: String, Hashable, Sendable {
    case location
    case sko
    case deliveryNote
    case stockDelivery

    var article: String {
        switch self {
        case .location: "a location"
        case .sko: "a SKO"
        case .deliveryNote: "a delivery note"
        case .stockDelivery: "a stock delivery"
        }
    }

    var icon: AppIcon {
        switch self {
        case .location: .locations
        case .sko: .sko
        case .deliveryNote: .deliveryNote
        case .stockDelivery: .stockDelivery
        }
    }

    var openTitle: String {
        switch self {
        case .location: "Open location"
        case .sko: "Open SKO"
        case .deliveryNote: "Open delivery note"
        case .stockDelivery: "Open stock delivery"
        }
    }

    var fallbackDetail: String {
        switch self {
        case .location: "Location"
        case .sko: "SKO"
        case .deliveryNote: "Delivery note"
        case .stockDelivery: "Stock delivery"
        }
    }
}

struct ShellScanRow: Identifiable, Hashable, Sendable {
    var id: String { title }
    let title: String
    let detail: String
    let link: ShellLink
}

struct ShellScanTarget: Hashable, Sendable {
    let code: String
    let kind: ShellScanKind
    let detail: String
    let link: ShellLink?
    var rows: [ShellScanRow] = []
}

enum ShellScanOutcome: Hashable, Sendable {
    case found(ShellScanTarget)
    case notFound(code: String)
    case failed(code: String, message: String)
    case needsWarehouse
    case sessionExpired
}

enum ShellScanCatalog {
    static let capabilities: [(icon: AppIcon, text: String)] = [
        (.sko, "SKO barcode → SKO and its locations"),
        (.locations, "Location label → Location detail"),
        (.deliveryNote, "Delivery note or tracking number → Delivery note detail"),
        (.stockDelivery, "Stock delivery reference → Stock delivery")
    ]

    static func target(from result: MobileScanResult) -> ShellScanTarget? {
        switch result.scanType {
        case .sko:
            return result.sko.map(skoTarget)
        case .location:
            return result.location.map(locationTarget)
        case .deliveryNote:
            return result.deliveryNote.map(deliveryNoteTarget)
        case .stockDelivery:
            return result.stockDelivery.map(stockDeliveryTarget)
        case .unknown:
            return nil
        }
    }

    private static func skoTarget(_ sko: MobileScannedSko) -> ShellScanTarget {
        let name = sko.name?.trimmingCharacters(in: .whitespacesAndNewlines)
        let stock = sko.quantityInLocations.map { "\($0.trimmed) in locations" }
        let detail = [name?.isEmpty == false ? name : ShellScanKind.sko.fallbackDetail, stock].compactMap { $0 }.joined(separator: " · ")
        let rows = sko.locations.map { location in
            ShellScanRow(
                title: location.code,
                detail: location.quantity.map { "\($0.trimmed) here" } ?? "Location",
                link: .locations(.location(code: location.code))
            )
        }
        return ShellScanTarget(code: sko.code, kind: .sko, detail: detail, link: .inventory(.skoID(id: sko.id)), rows: rows)
    }

    private static func locationTarget(_ location: MobileScannedLocation) -> ShellScanTarget {
        let area = location.warehouseArea.flatMap { area in
            [area.name, area.code].compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }.first { !$0.isEmpty }
        }
        let skos = location.numberOrgStocks.map { count in count == 1 ? "1 SKO" : "\(Int(count).grouped) SKOs" }
        let parts = [area.map { "Area \($0)" }, skos].compactMap { $0 }
        return ShellScanTarget(
            code: location.code,
            kind: .location,
            detail: parts.isEmpty ? ShellScanKind.location.fallbackDetail : parts.joined(separator: " · "),
            link: .locations(.location(code: location.code))
        )
    }

    private static func deliveryNoteTarget(_ note: MobileScannedDeliveryNote) -> ShellScanTarget {
        let items = note.numberItems.map { count in count == 1 ? "1 item" : "\(Int(count).grouped) items" }
        let parts = [note.stateLabel, note.customerName, items].compactMap { $0?.trimmingCharacters(in: .whitespacesAndNewlines) }.filter { !$0.isEmpty }
        return ShellScanTarget(
            code: note.reference,
            kind: .deliveryNote,
            detail: parts.isEmpty ? ShellScanKind.deliveryNote.fallbackDetail : parts.joined(separator: " · "),
            link: .goodsOut(.deliveryNoteID(id: note.id))
        )
    }

    private static func stockDeliveryTarget(_ delivery: MobileScannedStockDelivery) -> ShellScanTarget {
        ShellScanTarget(
            code: delivery.reference,
            kind: .stockDelivery,
            detail: delivery.stateLabel ?? ShellScanKind.stockDelivery.fallbackDetail,
            link: .goodsIn(.scannedStockDelivery(id: delivery.id, reference: delivery.reference))
        )
    }

    static func problem(_ error: Error) -> String {
        switch error as? APIError {
        case .offline: "Scanning needs internet. Check Wi-Fi and try again."
        case .http(403): "You can’t look codes up in this warehouse."
        default: error.localizedDescription
        }
    }
}

@MainActor
@Observable
final class ShellScanLookup {
    typealias Service = @Sendable (Int, String) async throws -> MobileScanResult

    private(set) var outcome: ShellScanOutcome?
    private(set) var lookingUpCode: String?

    @ObservationIgnored private let service: Service

    init(service: @escaping Service) {
        self.service = service
    }

    static func live(_ client: APIClient) -> ShellScanLookup {
        ShellScanLookup { try await client.scan(warehouseID: $0, code: $1) }
    }

    var isLookingUp: Bool { lookingUpCode != nil }

    @discardableResult
    func lookUp(_ rawCode: String, warehouseID: Int?) async -> ShellScanOutcome? {
        let code = rawCode.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !code.isEmpty, !isLookingUp else { return nil }
        guard let warehouseID else {
            outcome = .needsWarehouse
            return outcome
        }
        lookingUpCode = code
        defer { lookingUpCode = nil }
        let result: ShellScanOutcome
        do {
            let response = try await service(warehouseID, code)
            result = ShellScanCatalog.target(from: response).map(ShellScanOutcome.found) ?? .notFound(code: response.code.isEmpty ? code : response.code)
        } catch APIError.unauthorized {
            result = .sessionExpired
        } catch APIError.http(statusCode: 404) {
            result = .notFound(code: code)
        } catch where error.isCancellation {
            return nil
        } catch {
            result = .failed(code: code, message: ShellScanCatalog.problem(error))
        }
        outcome = result
        return result
    }

    func clear() {
        outcome = nil
    }
}
