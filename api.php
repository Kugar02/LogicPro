<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

$input = file_get_contents('php://input');
$records = json_decode($input, true);

if (!is_array($records)) {
    echo json_encode(["error" => "無效的數據格式"]);
    exit;
}

class QuantumBaccaratEngine {
    private $records;
    
    const THEORETICAL_B = 50.68;
    const THEORETICAL_P = 49.32;

    public function __construct($records) {
        $this->records = array_filter($records, function($val) {
            return $val === 'B' || $val === 'P';
        });
        $this->records = array_values($this->records);
    }

    private function calculateDynamicBaseWeight() {
        $total = count($this->records);
        if ($total === 0) return ['B' => self::THEORETICAL_B, 'P' => self::THEORETICAL_P];

        $bCount = count(array_filter($this->records, fn($v) => $v === 'B'));
        $pCount = count(array_filter($this->records, fn($v) => $v === 'P'));
        $actualBRate = ($bCount / $total) * 100;
        $actualPRate = ($pCount / $total) * 100;

        $smoothing = min($total / 30, 0.8);
        $dynamicB = (self::THEORETICAL_B * (1 - $smoothing)) + ($actualBRate * $smoothing);
        $dynamicP = (self::THEORETICAL_P * (1 - $smoothing)) + ($actualPRate * $smoothing);

        return ['B' => $dynamicB, 'P' => $dynamicP];
    }

    // A. 構建大路二維矩陣
    private function buildBigRoadMatrix() {
        $matrix = [];
        $currentCol = -1;
        $lastResult = null;

        foreach ($this->records as $result) {
            if ($result !== $lastResult) {
                $currentCol++;
                $matrix[$currentCol] = [];
            }
            $matrix[$currentCol][] = $result;
            $lastResult = $result;
        }
        return $matrix;
    }

    // B. 大路特徵分析
    private function analyzeBigRoadPatterns($matrix) {
        $weight = ['B' => 0, 'P' => 0];
        $confidence = 0;
        $patterns = [];

        $colCount = count($matrix);
        if ($colCount < 2) return ['name' => '大路', 'weight' => $weight, 'confidence' => $confidence, 'patterns' => $patterns, 'matrix' => $matrix];

        $lastColIndex = $colCount - 1;
        $currentCol = $matrix[$lastColIndex];
        $prevCol = $matrix[$lastColIndex - 1];

        $currentLen = count($currentCol);
        $prevLen = count($prevCol);
        $currentOutcome = $currentCol[0];
        $opposite = ($currentOutcome === 'B') ? 'P' : 'B';

        if ($currentLen == $prevLen && $currentLen >= 2) {
            $patterns[] = "齊頭對齊";
            $confidence += 35;
            $weight[$opposite] += 25; 
        }
        if ($currentLen >= 4) {
            $patterns[] = "長龍";
            $confidence += 25;
            $weight[$currentOutcome] += 15; 
        }
        if ($colCount >= 4 && $currentLen == 1 && count($matrix[$lastColIndex - 1]) == 1 && count($matrix[$lastColIndex - 2]) == 1) {
            $patterns[] = "單跳";
            $confidence += 20;
            $weight[$opposite] += 15; 
        }

        return ['name' => '大路', 'weight' => $weight, 'confidence' => $confidence, 'patterns' => $patterns, 'matrix' => $matrix];
    }

    // C. 下三路紅藍筆序列推算 (絕對核心：拍腳、齊頭、長龍判斷)
    private function generateDerivedRoadSequence($bigRoadMatrix, $offset) {
        $sequence = [];
        $colCount = count($bigRoadMatrix);

        for ($col = 0; $col < $colCount; $col++) {
            $rowLength = count($bigRoadMatrix[$col]);
            for ($row = 0; $row < $rowLength; $row++) {
                // 下三路起始點規則
                if ($col < $offset) continue;
                if ($col == $offset && $row == 0) continue;

                if ($row == 0) {
                    // 換列 (橫向)：比較前一列與對應基準列的長度 (齊頭為紅，不齊為藍)
                    $prevColLen = count($bigRoadMatrix[$col - 1]);
                    $compColLen = count($bigRoadMatrix[$col - 1 - $offset]);
                    $sequence[] = ($prevColLen == $compColLen) ? 'R' : 'B';
                } else {
                    // 往下 (直向)：比較當前列與對應基準列的格子狀態
                    $compColLen = isset($bigRoadMatrix[$col - $offset]) ? count($bigRoadMatrix[$col - $offset]) : 0;
                    if ($row < $compColLen) {
                        $sequence[] = 'R'; // 基準列有格子 -> 紅筆 (順勢)
                    } elseif ($row == $compColLen) {
                        $sequence[] = 'B'; // 基準列剛好沒格子 (拍腳斷) -> 藍筆
                    } else {
                        $sequence[] = 'R'; // 基準列繼續沒格子 (長龍) -> 紅筆
                    }
                }
            }
        }
        return $sequence;
    }

    // D. 將下三路的一維序列轉換成二維矩陣
    private function buildDerivedMatrix($sequence) {
        $matrix = [];
        $currentCol = -1;
        $lastResult = null;
        foreach ($sequence as $result) {
            if ($result !== $lastResult) {
                $currentCol++;
                $matrix[$currentCol] = [];
            }
            $matrix[$currentCol][] = $result;
            $lastResult = $result;
        }
        return $matrix;
    }

    // E. 基礎特徵分析框架 (為下三路預留)
    private function detectPatterns($roadName, $roadMatrix) {
        return ['name' => $roadName, 'weight' => ['B' => 0, 'P' => 0], 'confidence' => 0, 'patterns' => [], 'matrix' => $roadMatrix];
    }

    // 整合四大核心
    private function analyzeFourRoads() {
        $bigRoadMatrix = $this->buildBigRoadMatrix();
        $bigRoad = $this->analyzeBigRoadPatterns($bigRoadMatrix);

        // 分別計算大眼仔 (位移1)、小路 (位移2)、曱甴路 (位移3)
        $matBigEye = $this->buildDerivedMatrix($this->generateDerivedRoadSequence($bigRoadMatrix, 1));
        $matSmall = $this->buildDerivedMatrix($this->generateDerivedRoadSequence($bigRoadMatrix, 2));
        $matRoach = $this->buildDerivedMatrix($this->generateDerivedRoadSequence($bigRoadMatrix, 3));

        $bigEyeBoy = $this->detectPatterns('大眼仔', $matBigEye);
        $smallRoad = $this->detectPatterns('小路', $matSmall);
        $roachRoad = $this->detectPatterns('曱甴路', $matRoach);

        return [$bigRoad, $bigEyeBoy, $smallRoad, $roachRoad];
    }

    public function generateRecommendation() {
        if (count($this->records) < 2) {
            return ["status" => "waiting", "recommendation" => "等待數據累積...", "action" => "NONE", "bet_amount" => 0];
        }

        $baseWeight = $this->calculateDynamicBaseWeight();
        $cores = $this->analyzeFourRoads();

        $totalB = $baseWeight['B'];
        $totalP = $baseWeight['P'];
        $totalConfidence = 0;

        foreach ($cores as $core) {
            $totalB += $core['weight']['B'];
            $totalP += $core['weight']['P'];
            $totalConfidence += $core['confidence'];
        }

        $diff = abs($totalB - $totalP);
        $confidenceThreshold = 35; 

        $action = "NONE";
        $recommendation = "觀望 (防禦機制觸發)";
        $betAmount = 0;

        if ($diff > 10 && $totalConfidence >= $confidenceThreshold) {
            if ($totalB > $totalP) { $action = "B"; $recommendation = "正打 莊 (B)"; $betAmount = 100; } 
            else { $action = "P"; $recommendation = "正打 閒 (P)"; $betAmount = 100; }
        } elseif ($diff <= 10) {
            $recommendation = "鎖死觀望 (路單衝突)";
        } elseif ($totalConfidence < $confidenceThreshold) {
             $recommendation = "鎖死觀望 (無明顯特徵)";
        }

        return [
            "status" => "success",
            "action" => $action,
            "recommendation" => $recommendation,
            "bet_amount" => $betAmount,
            "dynamic_base" => ["B" => round($baseWeight['B'], 2), "P" => round($baseWeight['P'], 2)],
            "final_weight" => ["B" => round($totalB, 2), "P" => round($totalP, 2)],
            "total_confidence" => $totalConfidence,
            "cores_analysis" => $cores
        ];
    }
}

$engine = new QuantumBaccaratEngine($records);
echo json_encode($engine->generateRecommendation());
?>
