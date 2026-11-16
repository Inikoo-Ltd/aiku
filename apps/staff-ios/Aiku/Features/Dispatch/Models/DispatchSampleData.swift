import Foundation

enum DispatchSampleData {
    static let shippers: [DispatchShipper] = [
        DispatchShipper(name: "DPD (GB)", integration: "API", shipmentsToday: 48),
        DispatchShipper(name: "APC (GB)", integration: "API", shipmentsToday: 21),
        DispatchShipper(name: "Packeta (SK)", integration: "API", shipmentsToday: 0)
    ]
}
