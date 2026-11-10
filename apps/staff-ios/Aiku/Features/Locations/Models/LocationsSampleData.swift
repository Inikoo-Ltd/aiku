import Foundation

enum LocationsSampleData {
    static func date(_ day: Int, _ month: Int, _ year: Int = 2026) -> Date {
        var calendar = Calendar(identifier: .gregorian)
        calendar.timeZone = TimeZone.current
        return calendar.date(from: DateComponents(year: year, month: month, day: day, hour: 12)) ?? Date(timeIntervalSince1970: 0)
    }

    static func bathBomb(_ quantity: Int, type: LocationsSlotType = .storing, roles: Set<LocationsPickingRole> = []) -> LocationsSlot {
        LocationsSlot(
            skoReference: "SKO-00733", name: "Bath bomb – rose 180g", quantity: quantity, type: type, roles: roles,
            unitWeightKg: 0.4, unitVolumeM3: 0.006, unitValue: 9.6
        )
    }

    static func lavenderOil(_ quantity: Int, type: LocationsSlotType = .storing) -> LocationsSlot {
        LocationsSlot(
            skoReference: "SKO-00412", name: "Lavender essential oil 10ml", quantity: quantity, type: type,
            unitWeightKg: 0.2, unitVolumeM3: 0.002, unitValue: 14.4
        )
    }

    static func eucalyptusOil(_ quantity: Int) -> LocationsSlot {
        LocationsSlot(
            skoReference: "SKO-01402", name: "Eucalyptus essential oil 10ml", quantity: quantity, type: .storing,
            replenishment: 24, unitWeightKg: 0.075, unitVolumeM3: 0.001, unitValue: 12.0
        )
    }

    static func incense(_ quantity: Int, type: LocationsSlotType = .storing) -> LocationsSlot {
        LocationsSlot(
            skoReference: "SKO-00953", name: "Incense – sandalwood", quantity: quantity, type: type,
            unitWeightKg: 0.175, unitVolumeM3: 0.0005, unitValue: 6.0
        )
    }

    static func candle(_ quantity: Int, type: LocationsSlotType = .storing) -> LocationsSlot {
        LocationsSlot(
            skoReference: "SKO-01187", name: "Soy candle – vanilla", quantity: quantity, type: type,
            unitWeightKg: 0.6, unitVolumeM3: 0.003, unitValue: 18.0
        )
    }

    static var warehouse: LocationsWarehouse {
        var bathBombPicking = bathBomb(30, type: .picking, roles: [.wholesale])
        bathBombPicking.minStock = 12
        bathBombPicking.maxStock = 48
        bathBombPicking.note = "Top shelf, behind the gift sets"
        var lavenderPicking = lavenderOil(48, type: .picking)
        lavenderPicking.roles = [.wholesale]
        lavenderPicking.minStock = 12
        lavenderPicking.maxStock = 60
        var bathBombDropshipping = bathBomb(24, type: .picking, roles: [.dropshipping])
        bathBombDropshipping.minStock = 6
        bathBombDropshipping.maxStock = 30

        return LocationsWarehouse(
            areas: [
                LocationsArea(code: "A", name: "Picking", pickingOrder: 1, locationCount: 412, emptyCount: 12, valueLabel: "[£ 96k]"),
                LocationsArea(code: "B", name: "Overflow", pickingOrder: 2, locationCount: 280, emptyCount: 44, valueLabel: "[£ 61k]"),
                LocationsArea(code: "C", name: "Bulk", pickingOrder: 3, locationCount: 236, emptyCount: 53, valueLabel: "[£ 48k]"),
                LocationsArea(code: "D", name: "Fulfilment", pickingOrder: 4, locationCount: 160, emptyCount: 31, valueLabel: "[£ 0]")
            ],
            locations: [
                LocationsLocation(
                    code: "A-03-02", areaCode: "A", scopes: [.stocks, .dropshipping], maxWeightKg: 120, maxVolumeM3: 1.2,
                    slots: [bathBombPicking, lavenderPicking, eucalyptusOil(120), incense(8)],
                    auditedOn: date(28, 10)
                ),
                LocationsLocation(
                    code: "A-07-01", areaCode: "A", scopes: [.stocks], maxWeightKg: 120,
                    slots: [candle(20, type: .picking), lavenderOil(30), incense(0)],
                    auditedOn: date(14, 10)
                ),
                LocationsLocation(code: "A-09-04", areaCode: "A", scopes: [.stocks], maxWeightKg: 120, maxVolumeM3: 1.2),
                LocationsLocation(
                    code: "A-12-03", areaCode: "A", scopes: [.stocks], neverRemoveEmptySkos: true, maxWeightKg: 120,
                    slots: [incense(8, type: .picking), lavenderOil(0), candle(6)],
                    auditedOn: date(3, 11)
                ),
                LocationsLocation(
                    code: "B-01-04", areaCode: "B", scopes: [.stocks], maxWeightKg: 200,
                    slots: [bathBomb(6)]
                ),
                LocationsLocation(
                    code: "B-02-01", areaCode: "B", scopes: [.stocks, .dropshipping], maxWeightKg: 200,
                    slots: [bathBombDropshipping],
                    auditedOn: date(21, 10)
                ),
                LocationsLocation(code: "B-09-05", areaCode: "B", status: .broken, scopes: [.stocks]),
                LocationsLocation(
                    code: "C-04-02", areaCode: "C", scopes: [.stocks], maxWeightKg: 500, maxVolumeM3: 3,
                    slots: [bathBomb(60), eucalyptusOil(0)]
                ),
                LocationsLocation(
                    code: "D-01-01", areaCode: "D", scopes: [.fulfilment], isGoodsOutGathering: true
                ),
                LocationsLocation(
                    code: "D-02-01", areaCode: "D", scopes: [.fulfilment],
                    pallets: [LocationsPallet(reference: "acme-p0231", customer: "[Customer A]", state: "Storing")]
                )
            ],
            summary: LocationsWarehouseSummary(
                areaCount: 6, locationCount: 1284, emptyCount: 180, utilisationPercent: 86, toRemoveNow: 37, removedLastWeek: 214
            ),
            autoRemoval: LocationsAutoRemoval(isOn: true, changedBy: "[name]", changedOn: date(2, 11))
        )
    }
}
