import AVFoundation
import SwiftUI

private enum GoodsInCameraSheet: Identifiable, Hashable {
    case typeCode
    case check(itemID: Int)
    case chooseLine(code: String, itemIDs: [Int])

    var id: String {
        switch self {
        case .typeCode: "typeCode"
        case .check(let itemID): "check-\(itemID)"
        case .chooseLine(let code, _): "choose-\(code)"
        }
    }
}

private enum GoodsInCameraFeedback: Equatable {
    case found(String)
    case problem(String)
}

struct GoodsInCheckScanScreen: View {
    let reference: String
    let feed: GoodsInDeliveryFeed

    var body: some View {
        GoodsInAccessGate(isAllowed: \.viewGoodsIn) {
            if let delivery = feed.delivery {
                GoodsInCheckScanContent(feed: feed, delivery: delivery)
            } else {
                GoodsInLoadingScreen(
                    title: reference,
                    kicker: "Stock delivery",
                    failedTitle: "\(reference) could not be loaded",
                    problem: feed.loadError,
                    retry: { Task { await feed.load() } }
                )
            }
        }
        .onAppear {
            feed.chosenView = .check
            Task { await feed.load() }
        }
    }
}

private struct GoodsInCheckScanContent: View {
    let feed: GoodsInDeliveryFeed
    let delivery: MobileStockDelivery
    @Environment(AppRouter.self) private var router
    @State private var currentItemID: Int?
    @State private var feedback: GoodsInCameraFeedback?
    @State private var sheet: GoodsInCameraSheet?
    @State private var sheetProblem: String?
    @State private var cameraAllowed: Bool?
    @State private var scannerAttempt = 0
    @State private var isTorchOn = false

    private var current: MobileStockDeliveryItem? {
        currentItemID.flatMap(delivery.item) ?? delivery.items.first(where: \.needsCheck)
    }

    var body: some View {
        VStack(spacing: 0) {
            ScrollView {
                VStack(spacing: 16) {
                    topBar
                    modeSwitch
                    camera
                    Text(hint)
                        .font(.aiku(14))
                        .foregroundStyle(.white)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 16)
                    feedbackView
                        .padding(.horizontal, 16)
                }
                .padding(.vertical, 8)
            }
            panel
        }
        .background(RadialGradient(colors: [Color(hex: 0x374151), Color(hex: 0x111827)], center: UnitPoint(x: 0.5, y: 0.35), startRadius: 10, endRadius: 520).ignoresSafeArea())
        .toolbar(.hidden, for: .navigationBar)
        .accessibilityIdentifier("goodsIn.checkScan")
        .hardwareScanner(isEnabled: sheet == nil) { code in
            Task { await feed.scanLine(code) }
        }
        .task {
            if cameraAllowed == nil {
                cameraAllowed = await CameraPermission.request()
            }
        }
        .onDisappear { setTorch(false) }
        .goodsInEvents(
            feed.event,
            onHandled: { feed.event = nil },
            onSheetProblem: sheet == nil ? nil : { sheetProblem = $0 },
            doneText: doneText,
            onDone: closeSheet
        )
        .onChange(of: feed.scanOutcome) { _, outcome in
            handle(outcome)
        }
        .aikuSheet(item: $sheet) { open in
            sheetContent(open)
        }
    }

    private var hint: String {
        if cameraAllowed == false { return "Camera access is off. Allow it in Settings, or type the code." }
        if cameraAllowed == true, !QRCodeScanner.isAvailable { return "This device can’t scan barcodes. Type the code instead." }
        return "Point at the item barcode"
    }

    @ViewBuilder
    private var camera: some View {
        ZStack {
            if cameraAllowed == true, QRCodeScanner.isAvailable, sheet == nil {
                QRCodeScanner(symbologies: QRCodeScanner.warehouseSymbologies) { code in
                    Task { await feed.scanLine(code) }
                }
                .id(scannerAttempt)
                .clipShape(RoundedRectangle(cornerRadius: 14))
            }
            ScanViewfinder()
        }
        .frame(width: 300, height: 190)
    }

    private var topBar: some View {
        HStack(spacing: 8) {
            roundButton(.close, label: "Close scanner", isActive: false) { router.back() }
            VStack(spacing: 0) {
                Text("Goods in · Checking").font(.aiku(12)).foregroundStyle(Color(hex: 0xD1D5DB))
                Text(delivery.reference).font(.aiku(16, .bold)).foregroundStyle(.white)
            }
            .lineLimit(1)
            .frame(maxWidth: .infinity)
            if cameraAllowed == true, hasTorch {
                roundButton(isTorchOn ? .torchOff : .torchOn, label: isTorchOn ? "Turn torch off" : "Turn torch on", isActive: isTorchOn) {
                    setTorch(!isTorchOn)
                }
            } else {
                Color.clear.frame(width: 44, height: 44)
            }
        }
        .padding(.horizontal, 16)
    }

    private func roundButton(_ icon: AppIcon, label: String, isActive: Bool, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            icon.image
                .font(.system(size: 18, weight: .semibold))
                .foregroundStyle(isActive ? AikuColor.onScan : .white)
                .frame(width: 44, height: 44)
                .background(isActive ? AikuColor.scan : Color(hex: 0x111827, opacity: 0.6), in: Circle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(label)
    }

    private var modeSwitch: some View {
        HStack(spacing: 4) {
            HStack(spacing: 6) {
                AppIcon.camera.image.font(.system(size: 14))
                Text("Camera")
            }
            .foregroundStyle(AikuColor.onScan)
            .padding(.horizontal, 12)
            .frame(height: 34)
            .background(AikuColor.scan, in: Capsule())
            Button { router.back() } label: {
                HStack(spacing: 6) {
                    AppIcon.scannerDevice.image.font(.system(size: 14))
                    Text("Bluetooth scanner")
                }
                .foregroundStyle(.white)
                .padding(.horizontal, 12)
                .frame(height: 34)
                .contentShape(Capsule())
            }
            .buttonStyle(.plain)
        }
        .font(.aiku(13, .semibold))
        .padding(4)
        .background(Color(hex: 0x111827, opacity: 0.6), in: Capsule())
    }

    @ViewBuilder
    private var feedbackView: some View {
        if let code = feed.lookingUpCode {
            ScanFeedbackBanner(tone: .info, title: "Looking up \(code)…")
        } else if case .found(let code) = feedback {
            ScanFeedbackBanner(tone: .success, title: "\(code) found")
                .accessibilityIdentifier("goodsIn.checkScan.found")
        } else if case .problem(let text) = feedback {
            ScanFeedbackBanner(tone: .danger, title: text)
                .accessibilityIdentifier("goodsIn.checkScan.problem")
        }
    }

    private var panel: some View {
        VStack(alignment: .leading, spacing: 12) {
            Capsule()
                .fill(AikuColor.borderStrong)
                .frame(width: 40, height: 4)
                .frame(maxWidth: .infinity)
            if let item = current {
                Text(currentItemID != nil ? "Scanned" : "Next to check")
                    .font(.aiku(12, .semibold))
                    .foregroundStyle(AikuColor.text3)
                HStack(spacing: 12) {
                    GoodsInPhoto(code: item.code, imageURL: item.sko?.imageUrl)
                    VStack(alignment: .leading, spacing: 2) {
                        Text(item.code).font(.aiku(16, .bold)).foregroundStyle(AikuColor.text)
                        Text(item.name).font(.aiku(13)).foregroundStyle(AikuColor.text3).lineLimit(2)
                    }
                    .frame(maxWidth: .infinity, alignment: .leading)
                    VStack(alignment: .trailing, spacing: 0) {
                        (Text(GoodsInText.quantity(item.skoChecked ?? 0, label: item.skoCheckedLabel)).font(.aiku(22, .bold)).foregroundColor(AikuColor.text)
                            + Text(" / \(GoodsInText.quantity(item.skoExpected, label: item.skoExpectedLabel))").font(.aiku(14, .medium)).foregroundColor(AikuColor.text3))
                            .monospacedDigit()
                        Text("checked").font(.aiku(11)).foregroundStyle(AikuColor.text3)
                    }
                }
                .accessibilityElement(children: .combine)
                .accessibilityIdentifier("goodsIn.checkScan.current")
                if item.skoExpected > 0 {
                    ProgressBar(value: min(max((item.skoChecked ?? 0) / item.skoExpected, 0), 1))
                }
            } else {
                Text("Every item on this delivery is checked.")
                    .font(.aiku(14))
                    .foregroundStyle(AikuColor.text2)
            }
            HStack(spacing: 10) {
                AikuButton("Type code", icon: .keyboard, kind: .tertiary, size: .regular) {
                    open(.typeCode)
                }
                .accessibilityIdentifier("goodsIn.checkScan.typeCode")
                if delivery.canCheck, let item = current, currentItemID != nil {
                    AikuButton("Set checked quantity", kind: .secondary, size: .regular) {
                        open(.check(itemID: item.id))
                    }
                    .accessibilityIdentifier("goodsIn.checkScan.setQuantity")
                }
            }
            AikuButton("Done · back to items", icon: .check) { router.back() }
                .accessibilityIdentifier("goodsIn.checkScan.done")
        }
        .padding(.horizontal, 16)
        .padding(.top, 16)
        .padding(.bottom, 20)
        .background(UnevenRoundedRectangle(topLeadingRadius: 16, topTrailingRadius: 16).fill(AikuColor.surface).ignoresSafeArea(edges: .bottom))
    }

    @ViewBuilder
    private func sheetContent(_ open: GoodsInCameraSheet) -> some View {
        switch open {
        case .typeCode:
            GoodsInTypeCodeSheet(title: "Type code", subtitle: "Type the barcode or the SKO code", label: "Barcode or SKO code", actionTitle: "Find") { code in
                sheet = nil
                Task { await feed.scanLine(code) }
            } onCancel: {
                closeSheet()
            }
        case .check(let itemID):
            if let item = delivery.item(itemID) {
                GoodsInCheckedQuantitySheet(
                    item: item,
                    isWorking: feed.isWorking,
                    problem: sheetProblem ?? feed.writeErrors.values.first,
                    onSave: { quantity in Task { await feed.check(itemID: item.id, skoQuantity: quantity) } },
                    onNotReceived: { Task { await feed.notReceived(itemID: item.id) } },
                    onDismiss: closeSheet
                )
            }
        case let .chooseLine(code, itemIDs):
            GoodsInChooseLineSheet(code: code, items: itemIDs.compactMap(delivery.item)) { item in
                sheet = nil
                showLine(item.id)
            }
        }
    }

    private func open(_ next: GoodsInCameraSheet) {
        sheetProblem = nil
        sheet = next
    }

    private func closeSheet() {
        sheet = nil
        sheetProblem = nil
        feed.clearWriteErrors()
    }

    private func showLine(_ itemID: Int) {
        currentItemID = itemID
        feedback = delivery.item(itemID).map { .found($0.code) }
    }

    private func handle(_ outcome: GoodsInLineScanOutcome?) {
        guard let outcome else { return }
        switch outcome {
        case .line(let itemID):
            showLine(itemID)
        case let .choose(code, itemIDs):
            open(.chooseLine(code: code, itemIDs: itemIDs))
        case .notOnDelivery(let code):
            feedback = .problem("\(code) is not on \(delivery.reference)")
        case .failed(_, let message):
            feedback = .problem(message)
        }
        feed.scanOutcome = nil
        Task { @MainActor in
            try? await Task.sleep(for: .milliseconds(1500))
            scannerAttempt += 1
        }
    }

    private func doneText() -> String? {
        guard case let .check(itemID, sent) = feed.lastWrite else { return nil }
        let item = (feed.delivery ?? delivery).item(itemID)
        let code = item?.code ?? ""
        if item?.wasRaised(above: sent) == true {
            return "\(code) raised to \(GoodsInText.quantity(item?.skoChecked ?? 0, label: item?.skoCheckedLabel)): that much is already placed"
        }
        return sent == 0 ? "\(code) set as not received" : "\(code) · \(GoodsInText.number(sent)) checked"
    }

    private var hasTorch: Bool {
        AVCaptureDevice.default(for: .video)?.hasTorch == true
    }

    private func setTorch(_ isOn: Bool) {
        guard let device = AVCaptureDevice.default(for: .video), device.hasTorch, (try? device.lockForConfiguration()) != nil else {
            isTorchOn = false
            return
        }
        device.torchMode = isOn ? .on : .off
        device.unlockForConfiguration()
        isTorchOn = isOn
    }
}
