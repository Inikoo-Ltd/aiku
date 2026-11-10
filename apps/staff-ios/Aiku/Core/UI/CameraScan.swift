import SwiftUI

struct ScanFeedbackBanner: View {
    var tone: Tone = .success
    let title: String
    var detail: String?
    var note: String?

    init(tone: Tone = .success, title: String, detail: String? = nil, note: String? = nil) {
        self.tone = tone
        self.title = title
        self.detail = detail
        self.note = note
    }

    var body: some View {
        HStack(spacing: 10) {
            icon.image
                .font(.system(size: 17, weight: .semibold))
                .accessibilityHidden(true)
            (Text(title).fontWeight(.bold) + Text(detail.map { " \($0)" } ?? ""))
                .font(.aiku(14))
                .frame(maxWidth: .infinity, alignment: .leading)
            if let note {
                Text(note)
                    .font(.aiku(12))
                    .opacity(0.85)
            }
        }
        .foregroundStyle(.white)
        .padding(.horizontal, 14)
        .padding(.vertical, 12)
        .background(fill, in: RoundedRectangle(cornerRadius: 8))
        .accessibilityElement(children: .combine)
    }

    private var icon: AppIcon {
        switch tone {
        case .success, .lime: .check
        case .danger: .close
        case .warn, .orange: .warning
        case .info, .neutral: .info
        }
    }

    private var fill: Color {
        switch tone {
        case .success, .lime: Color(hex: 0x15803D)
        case .danger: Color(hex: 0xB91C1C)
        case .warn, .orange: Color(hex: 0xB45309)
        case .info, .neutral: Color(hex: 0x374151)
        }
    }
}

struct ScanViewfinder: View {
    var width: CGFloat = 300
    var height: CGFloat = 190
    @State private var laserAtBottom = false

    init(width: CGFloat = 300, height: CGFloat = 190) {
        self.width = width
        self.height = height
    }

    var body: some View {
        ZStack {
            ViewfinderCorners()
                .stroke(Color(hex: 0xFDE047), style: StrokeStyle(lineWidth: 4, lineCap: .round, lineJoin: .round))
            Rectangle()
                .fill(Color(hex: 0xFDE047))
                .frame(height: 2)
                .shadow(color: Color(hex: 0xFDE047, opacity: 0.7), radius: 6)
                .padding(.horizontal, 20)
                .offset(y: laserAtBottom ? height / 2 - 24 : 24 - height / 2)
        }
        .frame(width: width, height: height)
        .onAppear {
            withAnimation(.easeInOut(duration: 2.2).repeatForever(autoreverses: true)) {
                laserAtBottom = true
            }
        }
        .accessibilityHidden(true)
    }
}

private struct ViewfinderCorners: Shape {
    func path(in rect: CGRect) -> Path {
        let arm: CGFloat = 36
        let radius: CGFloat = 10
        var path = Path()
        path.move(to: CGPoint(x: rect.minX, y: rect.minY + arm))
        path.addLine(to: CGPoint(x: rect.minX, y: rect.minY + radius))
        path.addQuadCurve(to: CGPoint(x: rect.minX + radius, y: rect.minY), control: CGPoint(x: rect.minX, y: rect.minY))
        path.addLine(to: CGPoint(x: rect.minX + arm, y: rect.minY))
        path.move(to: CGPoint(x: rect.maxX - arm, y: rect.minY))
        path.addLine(to: CGPoint(x: rect.maxX - radius, y: rect.minY))
        path.addQuadCurve(to: CGPoint(x: rect.maxX, y: rect.minY + radius), control: CGPoint(x: rect.maxX, y: rect.minY))
        path.addLine(to: CGPoint(x: rect.maxX, y: rect.minY + arm))
        path.move(to: CGPoint(x: rect.maxX, y: rect.maxY - arm))
        path.addLine(to: CGPoint(x: rect.maxX, y: rect.maxY - radius))
        path.addQuadCurve(to: CGPoint(x: rect.maxX - radius, y: rect.maxY), control: CGPoint(x: rect.maxX, y: rect.maxY))
        path.addLine(to: CGPoint(x: rect.maxX - arm, y: rect.maxY))
        path.move(to: CGPoint(x: rect.minX + arm, y: rect.maxY))
        path.addLine(to: CGPoint(x: rect.minX + radius, y: rect.maxY))
        path.addQuadCurve(to: CGPoint(x: rect.minX, y: rect.maxY - radius), control: CGPoint(x: rect.minX, y: rect.maxY))
        path.addLine(to: CGPoint(x: rect.minX, y: rect.maxY - arm))
        return path
    }
}

struct CameraScanScreen<Feedback: View, Panel: View>: View {
    let kicker: String
    let title: String
    var hint = "Point at the barcode"
    let onClose: () -> Void
    var onUseScanner: (() -> Void)?
    var onSimulateScan: (() -> Void)?
    @ViewBuilder var feedback: Feedback
    @ViewBuilder var panel: Panel
    @State private var isTorchOn = false

    init(
        kicker: String,
        title: String,
        hint: String = "Point at the barcode",
        onClose: @escaping () -> Void,
        onUseScanner: (() -> Void)? = nil,
        onSimulateScan: (() -> Void)? = nil,
        @ViewBuilder feedback: () -> Feedback,
        @ViewBuilder panel: () -> Panel
    ) {
        self.kicker = kicker
        self.title = title
        self.hint = hint
        self.onClose = onClose
        self.onUseScanner = onUseScanner
        self.onSimulateScan = onSimulateScan
        self.feedback = feedback()
        self.panel = panel()
    }

    var body: some View {
        ZStack {
            RadialGradient(
                colors: [Color(hex: 0x374151), Color(hex: 0x111827)],
                center: UnitPoint(x: 0.5, y: 0.35),
                startRadius: 10,
                endRadius: 520
            )
            .ignoresSafeArea()
            VStack(spacing: 16) {
                topBar
                if let onUseScanner {
                    modeSwitch(onUseScanner)
                }
                Spacer(minLength: 0)
                Button {
                    onSimulateScan?()
                } label: {
                    ScanViewfinder()
                }
                .buttonStyle(.plain)
                .disabled(onSimulateScan == nil)
                .accessibilityLabel("Camera viewfinder")
                .accessibilityIdentifier("scan.viewfinder")
                Text(hint)
                    .font(.aiku(14))
                    .foregroundStyle(.white)
                feedback
                    .padding(.horizontal, 16)
                Spacer(minLength: 0)
                if Panel.self != EmptyView.self {
                    VStack(spacing: 12) {
                        Capsule().fill(AikuColor.borderStrong).frame(width: 40, height: 4)
                        panel
                    }
                    .padding(.horizontal, 16)
                    .padding(.top, 12)
                    .padding(.bottom, 12)
                    .frame(maxWidth: .infinity)
                    .background(
                        UnevenRoundedRectangle(topLeadingRadius: 16, topTrailingRadius: 16)
                            .fill(AikuColor.surface)
                            .ignoresSafeArea(edges: .bottom)
                    )
                }
            }
        }
        .accessibilityIdentifier("scan.camera")
    }

    private var topBar: some View {
        HStack(spacing: 8) {
            circleButton(.close, label: "Close scanner", action: onClose)
            VStack(spacing: 0) {
                Text(kicker)
                    .font(.aiku(12))
                    .foregroundStyle(Color(hex: 0xD1D5DB))
                Text(title)
                    .font(.aiku(16, .bold))
                    .foregroundStyle(.white)
            }
            .lineLimit(1)
            .frame(maxWidth: .infinity)
            circleButton(isTorchOn ? .torchOn : .torchOff, label: isTorchOn ? "Turn torch off" : "Turn torch on") {
                isTorchOn.toggle()
            }
        }
        .padding(.horizontal, 16)
        .padding(.top, 8)
    }

    private func circleButton(_ icon: AppIcon, label: String, action: @escaping () -> Void) -> some View {
        Button(action: action) {
            icon.image
                .font(.system(size: 18, weight: .semibold))
                .foregroundStyle(.white)
                .frame(width: 44, height: 44)
                .background(Color(hex: 0x111827, opacity: 0.6), in: Circle())
        }
        .buttonStyle(.plain)
        .accessibilityLabel(label)
    }

    private func modeSwitch(_ onUseScanner: @escaping () -> Void) -> some View {
        HStack(spacing: 4) {
            HStack(spacing: 6) {
                AppIcon.camera.image.font(.system(size: 14))
                Text("Camera")
            }
            .foregroundStyle(AikuColor.onScan)
            .padding(.horizontal, 12)
            .frame(height: 34)
            .background(AikuColor.scan, in: Capsule())
            Button(action: onUseScanner) {
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
}

extension CameraScanScreen where Feedback == EmptyView {
    init(
        kicker: String,
        title: String,
        hint: String = "Point at the barcode",
        onClose: @escaping () -> Void,
        onUseScanner: (() -> Void)? = nil,
        onSimulateScan: (() -> Void)? = nil,
        @ViewBuilder panel: () -> Panel
    ) {
        self.init(
            kicker: kicker, title: title, hint: hint, onClose: onClose,
            onUseScanner: onUseScanner, onSimulateScan: onSimulateScan,
            feedback: { EmptyView() }, panel: panel
        )
    }
}
