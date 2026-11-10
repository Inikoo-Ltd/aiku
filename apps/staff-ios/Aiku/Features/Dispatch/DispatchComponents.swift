import SwiftUI

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
    let detail: String
    let allTitle: String
    let verb: String
    let maximum: Int
    var photoLabel: String?
    let onAll: () -> Void
    let onAmount: (Int) -> Void
    @State private var amount = 1

    var body: some View {
        Card(padding: 14, spacing: 10) {
            HStack(spacing: 10) {
                if let photoLabel {
                    ProductThumb(size: 48, label: photoLabel)
                }
                VStack(alignment: .leading, spacing: 2) {
                    Text(title)
                        .font(.aiku(15, .bold))
                        .foregroundStyle(AikuColor.text)
                    Text(detail)
                        .font(.aiku(13, .bold))
                        .foregroundStyle(AikuColor.warn)
                }
                .frame(maxWidth: .infinity, alignment: .leading)
            }
            if maximum > 0 {
                AikuButton(allTitle, icon: .check, action: onAll)
                    .accessibilityIdentifier("dispatch.scanPanel.all")
                HStack(spacing: 8) {
                    QuantityStepper(value: $amount, range: 1...max(1, maximum))
                    AikuButton("\(verb) \(amount)", kind: .tertiary, fullWidth: false) {
                        onAmount(amount)
                        amount = 1
                    }
                }
            }
        }
        .onChange(of: maximum) { _, newValue in
            amount = min(amount, max(1, newValue))
        }
    }
}

struct DispatchCountLabel: View {
    let done: Int
    let total: Int
    let tone: Tone

    var body: some View {
        HStack(alignment: .firstTextBaseline, spacing: 3) {
            Text("\(done)")
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

struct DispatchCameraScan<Panel: View>: View {
    let kicker: String
    let title: String
    var feedback: String?
    let onClose: () -> Void
    let onScan: () -> Void
    @ViewBuilder var panel: Panel

    init(
        kicker: String,
        title: String,
        feedback: String? = nil,
        onClose: @escaping () -> Void,
        onScan: @escaping () -> Void,
        @ViewBuilder panel: () -> Panel
    ) {
        self.kicker = kicker
        self.title = title
        self.feedback = feedback
        self.onClose = onClose
        self.onScan = onScan
        self.panel = panel()
    }

    var body: some View {
        CameraScanScreen(kicker: kicker, title: title, onClose: onClose, onUseScanner: onClose, onSimulateScan: onScan) {
            if let feedback {
                ScanFeedbackBanner(tone: .success, title: feedback)
            }
        } panel: {
            panel
            AikuButton("Done", kind: .tertiary, action: onClose)
                .accessibilityIdentifier("dispatch.scan.done")
        }
    }
}
