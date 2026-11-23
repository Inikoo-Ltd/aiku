import Foundation
import Testing
@testable import Aiku

private actor ScanCalls {
    var requests: [(Int, String)] = []

    func record(_ warehouseID: Int, _ code: String) { requests.append((warehouseID, code)) }
}

@MainActor
private func lookup(_ result: Result<MobileScanResult, APIError>, calls: ScanCalls = ScanCalls()) -> ShellScanLookup {
    ShellScanLookup { warehouseID, code in
        await calls.record(warehouseID, code)
        return try result.get()
    }
}

@MainActor
struct ScanTests {
    @Test func endpointAsksTheSelectedWarehouse() {
        let endpoint = Endpoint.mobileScan(warehouseID: 4, code: "A-03-02")

        #expect(endpoint.path == "mobile/warehouses/4/scan")
        #expect(endpoint.queryItems == [URLQueryItem(name: "code", value: "A-03-02")])
    }

    @Test func decodesEachKindOfResult() throws {
        let decoder = JSONDecoder()
        decoder.keyDecodingStrategy = .convertFromSnakeCase
        let sko = try decoder.decode(DataEnvelope<MobileScanResult>.self, from: Data(#"{"data":{"code":"5060","type":"sko","sko":{"id":9,"code":"BB-01","name":"Bath bomb","state":"active","barcode":"5060","unit_barcode":null,"packed_in":6,"quantity_in_locations":12.5,"image_url":null,"locations":[{"code":"A-03-02","quantity":12.5}],"matched_on":"barcode"},"location":null,"delivery_note":null}}"#.utf8)).value
        #expect(sko.scanType == .sko)
        #expect(sko.sko?.locations.first?.quantity == 12.5)

        let note = try decoder.decode(DataEnvelope<MobileScanResult>.self, from: Data(#"{"data":{"code":"AR1","type":"delivery_note","sko":null,"location":null,"delivery_note":{"id":77,"reference":"AR1","state":"queued","state_label":"In queue","customer_name":"Acme","number_items":3,"shop":{"id":1,"name":"AW","type":"b2b"}}}}"#.utf8)).value
        #expect(note.deliveryNote?.id == 77)

        let location = try decoder.decode(DataEnvelope<MobileScanResult>.self, from: Data(#"{"data":{"code":"a-03-02","type":"location","sko":null,"location":{"id":5,"code":"A-03-02","slug":"a-03-02","warehouse_area":{"id":1,"code":"A","name":"Area A"},"number_org_stocks":6},"delivery_note":null}}"#.utf8)).value
        #expect(location.location?.warehouseArea?.name == "Area A")
    }

    @Test func resultsOpenTheRightScreen() async {
        let note = await lookup(.success(MobileScanResult(code: "AR1", type: "delivery_note", deliveryNote: .init(id: 77, reference: "AR1", stateLabel: "In queue", customerName: "Acme", numberItems: 3)))).lookUp("ar1", warehouseID: 1)
        #expect(note == .found(ShellScanTarget(code: "AR1", kind: .deliveryNote, detail: "In queue · Acme · 3 items", link: .goodsOut(.deliveryNoteID(id: 77)))))

        let location = await lookup(.success(MobileScanResult(code: "A-03-02", type: "location", location: .init(id: 5, code: "A-03-02", warehouseArea: .init(id: 1, code: "A", name: nil), numberOrgStocks: 6)))).lookUp("A-03-02", warehouseID: 1)
        #expect(location == .found(ShellScanTarget(code: "A-03-02", kind: .location, detail: "Area A · 6 SKOs", link: .locations(.location(code: "A-03-02")))))

        let sko = await lookup(.success(MobileScanResult(code: "5060", type: "sko", sko: .init(id: 9, code: "BB-01", name: "Bath bomb", quantityInLocations: 12, locations: [.init(code: "A-03-02", quantity: 12)])))).lookUp("5060", warehouseID: 1)
        guard case .found(let target) = sko else {
            Issue.record("Expected a SKO")
            return
        }
        #expect(target.link == .inventory(.skoID(id: 9)))
        #expect(target.detail == "Bath bomb · 12 in locations")
        #expect(target.rows == [ShellScanRow(title: "A-03-02", detail: "12 here", link: .locations(.location(code: "A-03-02")))])
    }

    @Test func unknownCodesAreNotFound() async {
        let calls = ScanCalls()
        let store = lookup(.success(MobileScanResult(code: "5060123456789", type: "unknown")), calls: calls)
        let outcome = await store.lookUp("  5060123456789 ", warehouseID: 3)

        #expect(outcome == .notFound(code: "5060123456789"))
        #expect(await calls.requests.first?.0 == 3)
        #expect(await calls.requests.first?.1 == "5060123456789")
        #expect(await lookup(.failure(.http(statusCode: 404))).lookUp("X", warehouseID: 1) == .notFound(code: "X"))
    }

    @Test func aScanNeedsAWarehouseAndInternet() async {
        let calls = ScanCalls()
        #expect(await lookup(.failure(.offline), calls: calls).lookUp("A-03-02", warehouseID: nil) == .needsWarehouse)
        #expect(await calls.requests.isEmpty)
        #expect(await lookup(.failure(.offline)).lookUp("A-03-02", warehouseID: 1) == .failed(code: "A-03-02", message: "Scanning needs internet. Check Wi-Fi and try again."))
        #expect(await lookup(.failure(.rejected(message: "No access"))).lookUp("A", warehouseID: 1) == .failed(code: "A", message: "No access"))
        #expect(await lookup(.failure(.unauthorized)).lookUp("A", warehouseID: 1) == .sessionExpired)
    }
}

@MainActor
private final class RecordingScanSignals: ScanSignals {
    var played: [String] = []

    func successTone() { played.append("successTone") }
    func errorTone() { played.append("errorTone") }
    func successVibration() { played.append("successVibration") }
    func errorVibration() { played.append("errorVibration") }
}

@MainActor
struct ScanFeedbackTests {
    private func freshDefaults() -> UserDefaults {
        let name = "scan-feedback-\(UUID().uuidString)"
        let defaults = UserDefaults(suiteName: name)!
        defaults.removePersistentDomain(forName: name)
        return defaults
    }

    @Test func anAcceptedScanBeepsAndVibratesLikeAndroid() {
        let signals = RecordingScanSignals()
        let feedback = ScanFeedback(defaults: freshDefaults(), signals: signals)

        feedback.accepted()

        #expect(signals.played == ["successTone", "successVibration"])
    }

    @Test func aRejectedScanPlaysTheLowToneAndTheErrorVibrationLikeAndroid() {
        let signals = RecordingScanSignals()
        let feedback = ScanFeedback(defaults: freshDefaults(), signals: signals)

        feedback.rejected()

        #expect(signals.played == ["errorTone", "errorVibration"])
    }

    @Test func soundAndVibrationFollowTheProfileSwitches() {
        let settings = freshDefaults()
        let signals = RecordingScanSignals()
        let feedback = ScanFeedback(defaults: settings, signals: signals)

        settings.set(false, forKey: ScanSettingsKey.sound)
        feedback.accepted()
        feedback.rejected()
        #expect(signals.played == ["successVibration", "errorVibration"])

        signals.played = []
        settings.set(true, forKey: ScanSettingsKey.sound)
        settings.set(false, forKey: ScanSettingsKey.vibrate)
        feedback.accepted()
        feedback.rejected()
        #expect(signals.played == ["successTone", "errorTone"])
    }

    @Test func theTonesAreShortWavesAndTheLowOneLastsLonger() {
        let success = ScanTone.success
        let error = ScanTone.error

        #expect(success.prefix(4) == Data("RIFF".utf8))
        #expect(success.subdata(in: 8..<12) == Data("WAVE".utf8))
        #expect(success.count == 44 + Int(Double(22_050) * 0.15) * 2)
        #expect(error.count == 44 + Int(Double(22_050) * 0.4) * 2)
    }

    @Test func aScannedCodeThatIsNotALocationIsAMissButALostConnectionIsNot() async {
        let missing = GoodsInLocationFinder(warehouseID: 1, service: GoodsInService(scan: { _, _ in throw APIError.http(statusCode: 404) }))
        await missing.scan("X-1")
        #expect(missing.notALocation == "X-1")
        #expect(missing.scanMisses == 1)

        let offline = GoodsInLocationFinder(warehouseID: 1, service: GoodsInService(scan: { _, _ in throw APIError.offline }))
        await offline.scan("X-1")
        #expect(offline.notALocation == nil)
        #expect(offline.scanMisses == 0)
    }

    @Test func onlyAQrCodeAikuRefusesIsARejectedClockingScan() {
        #expect(ClockingStore.isRefusal(APIError.validation(message: "This QR code is not valid.", code: nil)))
        #expect(ClockingStore.isRefusal(APIError.rejected(message: "This QR code has expired.")))
        #expect(!ClockingStore.isRefusal(APIError.http(statusCode: 500)))
        #expect(!ClockingStore.isRefusal(APIError.offline))
    }
}
