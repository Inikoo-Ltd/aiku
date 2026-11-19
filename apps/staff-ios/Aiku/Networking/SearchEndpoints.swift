import Foundation

enum MobileSearchType: String, Sendable, Hashable, CaseIterable {
    case skos
    case skoFamilies = "sko_families"
    case locations
    case deliveryNotes = "delivery_notes"
    case stockDeliveries = "stock_deliveries"
    case returns
    case pallets
    case palletDeliveries = "pallet_deliveries"
    case palletReturns = "pallet_returns"

    static let allKey = "all"

    var rowType: String {
        switch self {
        case .skos: "sko"
        case .skoFamilies: "sko_family"
        case .locations: "location"
        case .deliveryNotes: "delivery_note"
        case .stockDeliveries: "stock_delivery"
        case .returns: "return"
        case .pallets: "pallet"
        case .palletDeliveries: "pallet_delivery"
        case .palletReturns: "pallet_return"
        }
    }

    init?(rowType: String) {
        guard let match = Self.allCases.first(where: { $0.rowType == rowType }) else { return nil }
        self = match
    }
}

struct MobileSearchRow: Sendable, Equatable, Identifiable {
    let type: MobileSearchType
    let id: Int
    var code = ""
    var title = ""
    var subtitle = ""
    var state = ""
    var stateLabel = ""
    var quantity: Double?
    var imageUrl: String?
    var typeGroup: String?

    var reference: String { code.isEmpty ? title : code }

    func isSameRecord(_ other: MobileSearchRow) -> Bool {
        type == other.type && id == other.id
    }
}

struct MobileSearchGroup: Sendable, Equatable, Identifiable {
    let type: MobileSearchType
    var label = ""
    var hasMore = false
    var rows: [MobileSearchRow] = []

    var id: MobileSearchType { type }
}

struct MobileSearchResults: Decodable, Sendable, Equatable {
    var query = ""
    var types: [MobileSearchType] = []
    var exact: MobileSearchRow?
    var groups: [MobileSearchGroup] = []

    private enum CodingKeys: String, CodingKey {
        case query, types, exact, groups
    }

    init(query: String = "", types: [MobileSearchType] = [], exact: MobileSearchRow? = nil, groups: [MobileSearchGroup] = []) {
        self.query = query
        self.types = types
        self.exact = exact
        self.groups = groups
    }

    init(from decoder: Decoder) throws {
        let container = try decoder.container(keyedBy: CodingKeys.self)
        query = (try? container.decodeIfPresent(String.self, forKey: .query)) ?? ""
        let typeKeys = (try? container.decodeIfPresent([String?].self, forKey: .types)) ?? []
        var knownTypes: [MobileSearchType] = []
        for key in typeKeys {
            guard let type = key.flatMap(MobileSearchType.init(rawValue:)), !knownTypes.contains(type) else { continue }
            knownTypes.append(type)
        }
        types = knownTypes
        exact = (try? container.decodeIfPresent(WireRow.self, forKey: .exact))?.row
        let wireGroups = (try? container.decodeIfPresent([WireGroup?].self, forKey: .groups)) ?? []
        groups = wireGroups.compactMap { $0?.group }
    }

    private struct WireGroup: Decodable {
        var type: String?
        var label: String?
        var hasMore = false
        var rows: [WireRow?] = []

        private enum CodingKeys: String, CodingKey {
            case type, label, hasMore, rows
        }

        init(from decoder: Decoder) throws {
            guard let container = try? decoder.container(keyedBy: CodingKeys.self) else { return }
            type = try? container.decodeIfPresent(String.self, forKey: .type)
            label = try? container.decodeIfPresent(String.self, forKey: .label)
            hasMore = (try? container.decodeIfPresent(Bool.self, forKey: .hasMore)) ?? false
            rows = (try? container.decodeIfPresent([WireRow?].self, forKey: .rows)) ?? []
        }

        var group: MobileSearchGroup? {
            guard let type = type.flatMap(MobileSearchType.init(rawValue:)) else { return nil }
            let known = rows.compactMap { $0?.row }.filter { $0.type == type }
            guard !known.isEmpty else { return nil }
            return MobileSearchGroup(type: type, label: label.searchTrimmed ?? "", hasMore: hasMore, rows: known)
        }
    }

    private struct WireRow: Decodable {
        var type: String?
        var id: Int?
        var code: String?
        var title: String?
        var subtitle: String?
        var state: String?
        var stateLabel: String?
        var quantity: Double?
        var imageUrl: String?
        var typeGroup: String?

        private enum CodingKeys: String, CodingKey {
            case type, id, code, title, subtitle, state, stateLabel, quantity, imageUrl, typeGroup
        }

        init(from decoder: Decoder) throws {
            guard let container = try? decoder.container(keyedBy: CodingKeys.self) else { return }
            type = try? container.decodeIfPresent(String.self, forKey: .type)
            id = container.searchInt(.id)
            code = try? container.decodeIfPresent(String.self, forKey: .code)
            title = try? container.decodeIfPresent(String.self, forKey: .title)
            subtitle = try? container.decodeIfPresent(String.self, forKey: .subtitle)
            state = try? container.decodeIfPresent(String.self, forKey: .state)
            stateLabel = try? container.decodeIfPresent(String.self, forKey: .stateLabel)
            quantity = container.searchDouble(.quantity)
            imageUrl = try? container.decodeIfPresent(String.self, forKey: .imageUrl)
            typeGroup = try? container.decodeIfPresent(String.self, forKey: .typeGroup)
        }

        var row: MobileSearchRow? {
            guard let type = type.flatMap(MobileSearchType.init(rowType:)), let id else { return nil }
            let code = code.searchTrimmed ?? ""
            return MobileSearchRow(
                type: type,
                id: id,
                code: code,
                title: title.searchTrimmed ?? code,
                subtitle: subtitle.searchTrimmed ?? "",
                state: state.searchTrimmed ?? "",
                stateLabel: stateLabel.searchTrimmed ?? "",
                quantity: quantity,
                imageUrl: imageUrl.searchTrimmed,
                typeGroup: typeGroup.searchTrimmed
            )
        }
    }
}

private extension KeyedDecodingContainer {
    func searchInt(_ key: Key) -> Int? {
        if let value = try? decodeIfPresent(Int.self, forKey: key) {
            return value
        }
        if let value = try? decodeIfPresent(Double.self, forKey: key) {
            return Int(value)
        }
        if let text = try? decodeIfPresent(String.self, forKey: key) {
            return Int(text.trimmingCharacters(in: .whitespaces))
        }
        return nil
    }

    func searchDouble(_ key: Key) -> Double? {
        if let value = try? decodeIfPresent(Double.self, forKey: key) {
            return value
        }
        if let text = try? decodeIfPresent(String.self, forKey: key) {
            return Double(text.trimmingCharacters(in: .whitespaces))
        }
        return nil
    }
}

private extension Optional where Wrapped == String {
    var searchTrimmed: String? {
        guard let text = self?.trimmingCharacters(in: .whitespacesAndNewlines), !text.isEmpty else { return nil }
        return text
    }
}

struct SearchService: Sendable {
    var search: @Sendable (_ warehouseID: Int, _ query: String, _ type: MobileSearchType?, _ page: Int) async throws -> MobileSearchResults

    static func live(_ client: APIClient) -> SearchService {
        SearchService(search: { try await client.fetchSearch(warehouseID: $0, query: $1, type: $2, page: $3) })
    }
}

extension Endpoint {
    static func mobileSearch(warehouseID: Int, query: String, type: MobileSearchType?, page: Int) -> Endpoint {
        var queryItems = [
            URLQueryItem(name: "q", value: query),
            URLQueryItem(name: "type", value: type?.rawValue ?? MobileSearchType.allKey)
        ]
        if type != nil {
            queryItems.append(URLQueryItem(name: "page", value: String(page)))
        }
        return Endpoint(path: "mobile/warehouses/\(warehouseID)/search", queryItems: queryItems)
    }
}

extension APIClient {
    func fetchSearch(warehouseID: Int, query: String, type: MobileSearchType?, page: Int) async throws -> MobileSearchResults {
        try await send(.mobileSearch(warehouseID: warehouseID, query: query, type: type, page: page), as: DataEnvelope<MobileSearchResults>.self).value
    }
}
