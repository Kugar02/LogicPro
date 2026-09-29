<?php
// 允許跨域請求與設定回傳 JSON 格式
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

// 接收前端傳來的開牌紀錄 (Array: ['B', 'P', 'B', 'B', ...])
$input = file_get_contents('php://input');
$records = json_decode($input, true);

if (!is_array($records)) {
    echo json_encode(["error" => "無效的數據格式"]);
    exit;
}

class QuantumBaccaratEngine {
    private $records;
    
    // 理論天生勝率
    const THEORETICAL_B = 50.68;
    const THEORETICAL_P = 49.32;

    public function __construct($records) {
        $this->records = array_filter($records, function($val) {
            return $val === 'B' || $val === 'P';
        });
        $this->records = array_values($this->records);
    }

    // 1. 動態天生勝率
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

    // 2. 構建大路二維矩陣
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

    // 3. 下三路紅藍筆序列推算
    private function generateDerivedRoadSequence($bigRoadMatrix, $offset) {
        $sequence = [];
        $colCount = count($bigRoadMatrix);

        for ($col = 0; $col < $colCount; $col++) {
            $rowLength = count($bigRoadMatrix[$col]);
            for ($row = 0; $row < $rowLength; $row++) {
                if ($col < $offset) continue;
                if ($col == $offset && $row == 0) continue;

                if ($row == 0) {
                    $prevColLen = count($bigRoadMatrix[$col - 1]);
                    $compColLen = count($bigRoadMatrix[$col - 1 - $offset]);
                    $sequence[] = ($prevColLen == $compColLen) ? 'R' : 'B';
                } else {
                    $compColLen = isset($bigRoadMatrix[$col - $offset]) ? count($bigRoadMatrix[$col - $offset]) : 0;
                    if ($row < $compColLen) {
                        $sequence[] = 'R'; 
                    } elseif ($row == $compColLen) {
                        $sequence[] = 'B'; 
                    } else {
                        $sequence[] = 'R'; 
                    }
                }
            }
        }
        return $sequence;
    }

    // 4. 下三路序列轉換為矩陣
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

    // 5. 通用特徵分析引擎 (支援四大路的 7 大特徵擴充)
    private function detectPatterns($roadName, $roadMatrix, $isDerived = false) {
        $weight = ['B' => 0, 'P' => 0];
        $confidence = 0;
        $patterns = [];

        $colCount = count($roadMatrix);
        if ($colCount < 2) return ['name' => $roadName, 'weight' => $weight, 'confidence' => $confidence, 'patterns' => $patterns, 'matrix' => $roadMatrix];

        $lastColIndex = $colCount - 1;
        $currentCol = $roadMatrix[$lastColIndex];
        $prevCol = $roadMatrix[$lastColIndex - 1];

        $currentLen = count($currentCol);
        $prevLen = count($prevCol);
        $currentOutcome = $currentCol[0]; 
        
        // 如果是下三路，權重轉換為紅藍筆趨勢輔助判斷
        $opposite = ($currentOutcome === 'B' || $currentOutcome === 'R') ? ($isDerived ? 'B' : 'P') : ($isDerived ? 'R' : 'B');

        // 特徵 1：【齊頭對齊】
        if ($currentLen == $prevLen && $currentLen >= 2) {
            $patterns[] = "齊頭對齊";
            $confidence += 20;
            if (!$isDerived) $weight[$opposite] += 15; 
        }

        // 特徵 2：【長龍】
        if ($currentLen >= 4) {
            $patterns[] = "長龍";
            $confidence += 25;
            if (!$isDerived) $weight[$currentOutcome] += 15;
        }

        // 特徵 3：【單跳】
        if ($colCount >= 4 && $currentLen == 1 && count($roadMatrix[$lastColIndex - 1]) == 1 && count($roadMatrix[$lastColIndex - 2]) == 1) {
            $patterns[] = "單跳";
            $confidence += 15;
            if (!$isDerived) $weight[$opposite] += 15;
        }

        // 特徵 4：【雙跳】
        if ($colCount >= 4 && $currentLen == 2 && count($roadMatrix[$lastColIndex - 1]) == 2) {
            $patterns[] = "雙跳";
            $confidence += 15;
            if (!$isDerived) $weight[$opposite] += 10;
        }

        return ['name' => $roadName, 'weight' => $weight, 'confidence' => $confidence, 'patterns' => $patterns, 'matrix' => $roadMatrix];
    }

    // 6. 核心探路引擎 (Ask Road Simulation)
    private function simulateNextAskRoad($bigRoadMatrix) {
        $simulatedWeight = ['B' => 0, 'P' => 0];
        $simulatedConfidence = 0;

        $colCount = count($bigRoadMatrix);
        if ($colCount > 2) {
            $lastColLen = count($bigRoadMatrix[$colCount - 1]);
            $prevColLen = count($bigRoadMatrix[$colCount - 2]);

            // 簡易的下三路紅筆趨勢模擬判定
            if ($lastColLen == $prevColLen) {
                $lastOutcome = $bigRoadMatrix[$colCount - 1][0];
                $opposite = ($lastOutcome === 'B') ? 'P' : 'B';
                $simulatedWeight[$opposite] += 30; // 齊頭強烈加權給反打
                $simulatedConfidence += 25;
            } else if ($lastColLen < $prevColLen) {
                $lastOutcome = $bigRoadMatrix[$colCount - 1][0];
                $simulatedWeight[$lastOutcome] += 20; // 順打產生紅筆
                $simulatedConfidence += 15;
            }
        }
        return ['weight' => $simulatedWeight, 'confidence' => $simulatedConfidence];
    }

    // 7. 整合四大核心與探路
    private function analyzeFourRoads() {
        $bigRoadMatrix = $this->buildBigRoadMatrix();
        $bigRoad = $this->detectPatterns('大路', $bigRoadMatrix, false);

        $matBigEye = $this->buildDerivedMatrix($this->generateDerivedRoadSequence($bigRoadMatrix, 1));
        $matSmall = $this->buildDerivedMatrix($this->generateDerivedRoadSequence($bigRoadMatrix, 2));
        $matRoach = $this->buildDerivedMatrix($this->generateDerivedRoadSequence($bigRoadMatrix, 3));

        $bigEyeBoy = $this->detectPatterns('大眼仔', $matBigEye, true);
        $smallRoad = $this->detectPatterns('小路', $matSmall, true);
        $roachRoad = $this->detectPatterns('曱甴路', $matRoach, true);

        $askRoadResult = $this->simulateNextAskRoad($bigRoadMatrix);

        return [
            'cores' => [$bigRoad, $bigEyeBoy, $smallRoad, $roachRoad],
            'ask_road' => $askRoadResult
        ];
    }

    // 8. 最終預測建議 (AI 整合與防盲目偏向)
    public function generateRecommendation() {
        if (count($this->records) < 2) {
            return ["status" => "waiting", "recommendation" => "等待數據累積...", "action" => "NONE", "bet_amount" => 0];
        }

        $baseWeight = $this->calculateDynamicBaseWeight();
        $analysis = $this->analyzeFourRoads();
        
        $cores = $analysis['cores'];
        $askRoad = $analysis['ask_road'];

        $totalB = $baseWeight['B'] + $askRoad['weight']['B'];
        $totalP = $baseWeight['P'] + $askRoad['weight']['P'];
        $totalConfidence = $askRoad['confidence'];

        foreach ($cores as $core) {
            $totalB += $core['weight']['B'] ?? 0;
            $totalP += $core['weight']['P'] ?? 0;
            $totalConfidence += $core['confidence'];
        }

        $diff = abs($totalB - $totalP);
        $confidenceThreshold = 40; // 防禦門檻

        $action = "NONE";
        $recommendation = "觀望 (防禦機制觸發)";
        $betAmount = 0;

        if ($diff > 10 && $totalConfidence >= $confidenceThreshold) {
            if ($totalB > $totalP) { 
                $action = "B"; $recommendation = "正打 莊 (B)"; $betAmount = 100; 
            } else { 
                $action = "P"; $recommendation = "正打 閒 (P)"; $betAmount = 100; 
            }
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
            "cores_analysis" => $cores // 直接傳遞 cores，讓前端 JS 完美接軌
        ];
    }
}

// 執行引擎並輸出結果
$engine = new QuantumBaccaratEngine($records);
echo json_encode($engine->generateRecommendation());
?>
