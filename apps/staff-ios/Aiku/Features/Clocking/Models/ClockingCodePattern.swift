import Foundation

struct ClockingBar: Identifiable, Hashable, Sendable {
    let id: Int
    let width: Int
    let isDark: Bool
}

enum ClockingCodePattern {
    static let qrSize = 11

    static let generated: (bars: [ClockingBar], qrCells: [Bool]) = {
        var seed = 7
        func random() -> Double {
            seed = (seed * 9301 + 49297) % 233280
            return Double(seed) / 233280
        }
        let bars = (0..<44).map { index in
            ClockingBar(id: index, width: random() > 0.55 ? 4 : 2, isDark: index % 2 == 0)
        }
        func isFinder(_ row: Int, _ column: Int) -> Bool {
            let origins = [(0, 0), (0, 8), (8, 0)]
            return origins.contains { row >= $0.0 && row < $0.0 + 3 && column >= $0.1 && column < $0.1 + 3 }
        }
        let cells = (0..<(qrSize * qrSize)).map { index in
            isFinder(index / qrSize, index % qrSize) || random() > 0.52
        }
        return (bars, cells)
    }()

    static var bars: [ClockingBar] { generated.bars }
    static var qrCells: [Bool] { generated.qrCells }
}
