import Foundation

struct ShellWarehouse: Identifiable, Hashable, Sendable {
    var id: String { code }
    let name: String
    let code: String
    var remoteID: Int? = nil
}

struct ShellOrganisation: Identifiable, Hashable, Sendable {
    let id: String
    let initials: String
    let name: String
    let warehouses: [ShellWarehouse]

    var warehouseCountText: String {
        warehouses.count == 1 ? "1 warehouse" : "\(warehouses.count) warehouses"
    }
}

struct ShellWarehouseChoice: Hashable, Sendable {
    let organisation: String
    let warehouseName: String
    let warehouseCode: String
    var warehouseID: Int? = nil
}

enum ShellWarehouseDirectory {
    static let organisations: [ShellOrganisation] = [
        ShellOrganisation(id: "uk", initials: "UK", name: "AW UK", warehouses: [
            ShellWarehouse(name: "Main Warehouse", code: "WH1"),
            ShellWarehouse(name: "[Warehouse 2]", code: "WH2")
        ]),
        ShellOrganisation(id: "es", initials: "ES", name: "AW Spain", warehouses: [
            ShellWarehouse(name: "Main Warehouse", code: "ES1")
        ]),
        ShellOrganisation(id: "fu", initials: "FU", name: "[Fulfilment organisation]", warehouses: [
            ShellWarehouse(name: "[Fulfilment warehouse]", code: "FU1"),
            ShellWarehouse(name: "[Overflow site]", code: "FU2")
        ])
    ]

    static func choice(organisation: ShellOrganisation, warehouse: ShellWarehouse) -> ShellWarehouseChoice {
        ShellWarehouseChoice(organisation: organisation.name, warehouseName: warehouse.name, warehouseCode: warehouse.code, warehouseID: warehouse.remoteID)
    }

    static func organisations(from warehouses: [MobileWarehouse]) -> [ShellOrganisation] {
        var order: [Int] = []
        var grouped: [Int: (name: String, warehouses: [ShellWarehouse])] = [:]
        for warehouse in warehouses {
            if grouped[warehouse.organisation.id] == nil {
                order.append(warehouse.organisation.id)
                grouped[warehouse.organisation.id] = (warehouse.organisation.name, [])
            }
            grouped[warehouse.organisation.id]?.warehouses.append(
                ShellWarehouse(name: warehouse.name, code: warehouse.code, remoteID: warehouse.id)
            )
        }
        return order.compactMap { id in
            guard let group = grouped[id] else { return nil }
            return ShellOrganisation(id: "\(id)", initials: initials(of: group.name), name: group.name, warehouses: group.warehouses)
        }
    }

    static func initials(of name: String) -> String {
        let letters = name.split(separator: " ").prefix(2).compactMap(\.first)
        return String(letters).uppercased()
    }

    static func confirmTitle(selected: ShellWarehouseChoice, current: ShellWarehouseChoice) -> String {
        selected == current
            ? "Keep \(current.organisation) · \(current.warehouseName)"
            : "Switch to \(selected.organisation) · \(selected.warehouseName)"
    }
}
