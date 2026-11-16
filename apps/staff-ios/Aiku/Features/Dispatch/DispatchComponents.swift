import SwiftUI
import VisionKit

struct DispatchScanCard: View {
    let title: String
    let detail: String
    let highlight: String
    var footnote: String?
    var identifier = "dispatch.scan"
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            VStack(alignment: .leading, spacing: 6) {
                HStack(spacing: 8) {
                    AppIcon.scan.image.font(.system(size: 18, weight: .medium))
                    Text(title).font(.aiku(14, .bold))
                }
                Text(detail).font(.aiku(13))
                Text(highlight)
                    .font(.aiku(22, .bold))
                    .monospacedDigit()
                    .lineLimit(2)
                    .minimumScaleFactor(0.7)
                if let footnote {
                    Text(footnote).font(.aiku(13))
                }
            }
            .multilineTextAlignment(.leading)
            .foregroundStyle(AikuColor.onScan)
            .padding(14)
            .frame(maxWidth: .infinity, alignment: .leading)
            .background(AikuColor.scan, in: RoundedRectangle(cornerRadius: 12))
            .contentShape(RoundedRectangle(cornerRadius: 12))
        }
        .buttonStyle(.plain)
        .accessibilityHint("Opens the camera to scan")
        .accessibilityIdentifier(identifier)
    }
}

struct DispatchScanPanel: View {
    let title: String
    var subtitle: String?
    let detail: String
    let allTitle: String
    let verb: String
    let maximum: Int
    var isWorking = false
    let onAll: () -> Void
    let onAmount: (Int) -> Void
    var onClose: (() -> Void)?
    @State private var amount = 1

    var body: some View {
        Card(padding: 14, spacing: 10) {
            HStack(alignment: .top, spacing: 10) {
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    if let subtitle, !subtitle.isEmpty {
                        Text(subtitle)
                            .font(.aiku(13))
                            .foregroundStyle(AikuColor.text3)
                    }
                    Text(detail)
                        .font(.aiku(13, .bold))
                        .foregroundStyle(AikuColor.warn)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
                if let onClose {
                    IconButton(.close, label: "Close", action: onClose)
                }
            }
            AikuButton(allTitle, icon: .check, isLoading: isWorking, action: onAll)
                .accessibilityIdentifier("dispatch.scanPanel.all")
            HStack(spacing: 8) {
                QuantityStepper(value: $amount, range: 1...max(1, maximum))
                AikuButton("\(verb) \(amount)", kind: .tertiary, fullWidth: false) {
                    onAmount(amount)
                    amount = 1
                }
                .disabled(isWorking)
            }
        }
        .onChange(of: maximum) { _, newValue in
            amount = min(amount, max(1, newValue))
        }
    }
}

struct DispatchCountLabel: View {
    let done: String
    let total: String
    let tone: Tone

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 3) {
            Text(done)
                .font(.aiku(16, .bold))
                .foregroundStyle(tone == .neutral ? AikuColor.text : tone.foreground)
            Text("/ \(total)")
                .font(.aiku(13, .medium))
                .foregroundStyle(AikuColor.text3)
        }
        .monospacedDigit()
        .fixedSize()
        .accessibilityElement(children: .ignore)
        .accessibilityLabel("\(done) of \(total)")
    }
}

struct DispatchNote: View {
    let text: String

    init(_ text: String) {
        self.text = text
    }

    var body: some View {
        HelperText(text)
            .padding(.horizontal, 4)
    }
}

struct DispatchLoadProblem: View {
    let title: String
    let message: String
    let retry: () async -> Void

    var body: some View {
        InlineAlert(title: title, message: message) {
            LinkButton("Try again") {
                Task { await retry() }
            }
            .padding(.top, 4)
        }
    }
}

struct DispatchSkeletonList: View {
    var rows = 4

    var body: some View {
        Card {
            ForEach(0..<rows, id: \.self) { index in
                if index > 0 { RowDivider() }
                SkeletonRow()
            }
        }
        .accessibilityLabel("Loading")
    }
}

struct DispatchNoWarehouse: View {
    var body: some View {
        EmptyState(icon: .warehouse, title: "No warehouse selected", message: "Choose a warehouse in your profile to see its goods out.")
    }
}

struct DispatchBarcodeEntry: View {
    let placeholder: String
    let onScanCamera: () -> Void
    let onSubmit: (String) -> Void
    @State private var code = ""

    var body: some View {
        SearchField(text: $code, placeholder: placeholder, onScan: onScanCamera) {
            let submitted = code
            code = ""
            onSubmit(submitted)
        }
        .accessibilityIdentifier("dispatch.barcode")
    }
}

struct DispatchScanLog: View {
    let entries: [PickingSessionScanLogEntry]

    var body: some View {
        if !entries.isEmpty {
            SectionHeading("Scans")
            CardList(Array(entries.prefix(5))) { entry in
                ListRow(
                    entry.barcode,
                    subtitle: entry.message,
                    icon: entry.result == .done ? .checkCircle : .warning,
                    iconTone: entry.result == .done ? .success : .danger
                )
            }
        }
    }
}

struct DispatchSessionByReference<Content: View>: View {
    let reference: String
    let title: String
    @ViewBuilder let content: (Int) -> Content
    @State private var sessionID: Int?
    @State private var problem: String?
    @State private var isMissing = false
    @State private var warehouseStore = WarehouseStore.shared
    private let store = PickingSessionsStore.shared

    var body: some View {
        if let sessionID {
            content(sessionID)
        } else {
            AppScreen(title: title, kicker: reference) {
                if let problem {
                    DispatchLoadProblem(title: "Couldn’t load this picking session", message: problem) { await resolve() }
                } else if isMissing {
                    EmptyState(icon: .trolley, title: "Picking session not found")
                } else {
                    DispatchSkeletonList(rows: 3)
                }
            }
            .task(id: warehouseStore.selectedID) { await resolve() }
        }
    }

    private func resolve() async {
        problem = nil
        isMissing = false
        guard let warehouseID = warehouseStore.selectedID else {
            isMissing = true
            return
        }
        if store.warehouseID != warehouseID {
            await store.load(warehouseID: warehouseID)
        }
        do {
            if let found = try await store.sessionID(reference: reference) {
                sessionID = found
            } else {
                isMissing = true
            }
        } catch where error.isCancellation {
        } catch {
            problem = PickingSessionErrorText.message(error)
        }
    }
}

struct DispatchCameraScan<Panel: View>: View {
    let kicker: String
    let title: String
    var feedback: (text: String, tone: Tone)?
    let onClose: () -> Void
    let onCode: (String) -> Void
    @ViewBuilder var panel: Panel
    @State private var hasCamera = DispatchBarcodeCamera.isAvailable

    init(
        kicker: String,
        title: String,
        feedback: (text: String, tone: Tone)? = nil,
        onClose: @escaping () -> Void,
        onCode: @escaping (String) -> Void,
        @ViewBuilder panel: () -> Panel
    ) {
        self.kicker = kicker
        self.title = title
        self.feedback = feedback
        self.onClose = onClose
        self.onCode = onCode
        self.panel = panel()
    }

    var body: some View {
        if hasCamera {
            ZStack {
                DispatchBarcodeCamera(onCode: onCode)
                    .ignoresSafeArea()
                VStack(spacing: 12) {
                    topBar
                    Spacer(minLength: 0)
                    if let feedback {
                        ScanFeedbackBanner(tone: feedback.tone, title: feedback.text)
                            .padding(.horizontal, 16)
                    }
                    panelArea
                }
            }
            .task { hasCamera = await CameraPermission.request() }
            .accessibilityIdentifier("dispatch.camera")
        } else {
            CameraScanScreen(
                kicker: kicker,
                title: title,
                hint: "The camera can’t scan here. Close it and type or scan the barcode in the field.",
                onClose: onClose
            ) {
                if let feedback {
                    ScanFeedbackBanner(tone: feedback.tone, title: feedback.text)
                }
            } panel: {
                panel
                AikuButton("Done", kind: .tertiary, action: onClose)
                    .accessibilityIdentifier("dispatch.scan.done")
            }
        }
    }

    private var topBar: some View {
        HStack(spacing: 8) {
            Button(action: onClose) {
                AppIcon.close.image
                    .font(.system(size: 18, weight: .semibold))
                    .foregroundStyle(.white)
                    .frame(width: 44, height: 44)
                    .background(AikuColor.scrim, in: Circle())
            }
            .buttonStyle(.plain)
            .accessibilityLabel("Close scanner")
            VStack(spacing: 0) {
                Text(kicker).font(.aiku(12))
                Text(title).font(.aiku(16, .bold))
            }
            .foregroundStyle(.white)
            .lineLimit(1)
            .padding(.horizontal, 12)
            .padding(.vertical, 4)
            .background(AikuColor.scrim, in: Capsule())
            .frame(maxWidth: .infinity)
            Color.clear.frame(width: 44, height: 44)
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }

    private var panelArea: some View {
        VStack(spacing: 12) {
            Capsule().fill(AikuColor.borderStrong).frame(width: 40, height: 4)
            panel
            AikuButton("Done", kind: .tertiary, action: onClose)
                .accessibilityIdentifier("dispatch.scan.done")
        }
        .padding(.horizontal, 16)
        .padding(.vertical, 12)
        .frame(maxWidth: .infinity)
        .background(
            UnevenRoundedRectangle(topLeadingRadius: 16, topTrailingRadius: 16)
                .fill(AikuColor.surface)
                .ignoresSafeArea(edges: .bottom)
        )
    }
}

struct DispatchBarcodeCamera: UIViewControllerRepresentable {
    static let repeatDelay: TimeInterval = 1.5

    let onCode: (String) -> Void

    static var isAvailable: Bool {
        DataScannerViewController.isSupported && DataScannerViewController.isAvailable
    }

    func makeUIViewController(context: Context) -> DataScannerViewController {
        let scanner = DataScannerViewController(
            recognizedDataTypes: [.barcode()],
            qualityLevel: .balanced,
            recognizesMultipleItems: false,
            isHighFrameRateTrackingEnabled: false,
            isHighlightingEnabled: true
        )
        scanner.delegate = context.coordinator
        try? scanner.startScanning()
        return scanner
    }

    func updateUIViewController(_ controller: DataScannerViewController, context: Context) {
        context.coordinator.onCode = onCode
    }

    static func dismantleUIViewController(_ controller: DataScannerViewController, coordinator: Coordinator) {
        controller.stopScanning()
    }

    func makeCoordinator() -> Coordinator {
        Coordinator(onCode: onCode)
    }

    final class Coordinator: NSObject, DataScannerViewControllerDelegate {
        var onCode: (String) -> Void
        private var lastCode: String?
        private var lastScannedAt = Date.distantPast

        init(onCode: @escaping (String) -> Void) {
            self.onCode = onCode
        }

        func dataScanner(_ dataScanner: DataScannerViewController, didAdd addedItems: [RecognizedItem], allItems: [RecognizedItem]) {
            for item in addedItems {
                guard case .barcode(let barcode) = item, let value = barcode.payloadStringValue, !value.isEmpty else { continue }
                let now = Date()
                if value == lastCode, now.timeIntervalSince(lastScannedAt) < DispatchBarcodeCamera.repeatDelay { return }
                lastCode = value
                lastScannedAt = now
                onCode(value)
                return
            }
        }
    }
}
