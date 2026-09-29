<?php
// 允許跨域請求與設定回傳 JSON 格式
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Content-Type: application/json');

// 接收前端 JS 傳來的開牌紀錄 (Array: ['B', 'P', 'B', 'B', ...])
$input = file_get_contents('php://input');
$records = json_decode($input, true);

if (!is_array($records)) {
    echo json_encode(["error" => "無效的數據格式"]);
    exit;
}

class QuantumBaccaratEngine {
    private $records;
    
    // 理論天生勝率 (作為動態調整的基準底線)
    const THEORETICAL_B = 50.68;
    const THEORETICAL_P = 49.32;

    public function __construct($records) {
        // 過濾掉和局(T)，因為標準打法中，和局不影響大路與下三路的走向判斷
        $this->records = array_filter($records, function($val) {
            return $val === 'B' || $val === 'P';
        });
        $this->records = array_values($this->records);
    }

    /**
     * 1. 動態天生勝率 (Dynamic Base Weight)
     * 根據當前牌靴的實際開牌比例，動態微調天生勝率。
     */
    private function calculateDynamicBaseWeight() {
        $total = count($this->records);
        if ($total === 0) return ['B' => self::THEORETICAL_B, 'P' => self::THEORETICAL_P];

        $bCount = count(array_filter($this->records, fn($v) => $v === 'B'));
        $pCount = count(array_filter($this->records, fn($v) => $v === 'P'));

        $actualBRate = ($bCount / $total) * 100;
        $actualPRate = ($pCount / $total) * 100;

        // 動態平滑係數 (Smoothing Factor)：前 10 局偏重理論值，之後逐漸偏重實際盤勢
        $smoothing = min($total / 30, 0.8);

        $dynamicB = (self::THEORETICAL_B * (1 - $smoothing)) + ($actualBRate * $smoothing);
        $dynamicP = (self::THEORETICAL_P * (1 - $smoothing)) + ($actualPRate * $smoothing);

        return ['B' => $dynamicB, 'P' => $dynamicP];
    }

    /**
     * A. 構建大路二維矩陣 (Big Road Matrix)
     * 將流水帳轉換為直欄橫列，這是判斷所有路單特徵的基礎。
     */
    private function buildBigRoadMatrix() {
        $matrix = [];
        $currentCol = -1;
        $lastResult = null;

        foreach ($this->records as $result) {
            if ($result !== $lastResult) {
                // 遇到不同結果 (例如 B 換到 P)：新增一列 (Column)
                $currentCol++;
                $matrix[$currentCol] = [];
            }
            // 繼續在當前列往下堆疊
            $matrix[$currentCol][] = $result;
            $lastResult = $result;
        }
        return $matrix;
    }

    /**
     * B. 大路特徵精準判定 (包含齊頭對齊、單跳、長龍)
     */
    private function analyzeBigRoadPatterns($matrix) {
        $weight = ['B' => 0, 'P' => 0];
        $confidence = 0;
        $patterns = [];

        $colCount = count($matrix);
        if ($colCount < 2) {
            return ['name' => '大路', 'weight' => $weight, 'confidence' => $confidence, 'patterns' => $patterns, 'matrix' => $matrix];
        }

        // 取得最新的一列 (目前正在開的這條路) 與上一列
        $lastColIndex = $colCount - 1;
        $currentCol = $matrix[$lastColIndex];
        $prevCol = $matrix[$lastColIndex - 1];

        $currentLen = count($currentCol);
        $prevLen = count($prevCol);
        $currentOutcome = $currentCol[0];
        $opposite = ($currentOutcome === 'B') ? 'P' : 'B';

        // 特徵 1：【齊頭對齊】(極高可信度防禦/攻擊點)
        if ($currentLen == $prevLen && $currentLen >= 2) {
            $patterns[] = "齊頭對齊 (臨界點)";
            $confidence += 35;
            $weight[$opposite] += 25; // 強烈建議反打，維持齊頭狀態
        }

        // 特徵 2：【長龍】
        if ($currentLen >= 4) {
            $patterns[] = "長龍現身";
            $confidence += 25;
            $weight[$currentOutcome] += 15; // 順打當前結果
        }

        // 特徵 3：【單跳】
        if ($colCount >= 4) {
            if ($currentLen == 1 && count($matrix[$lastColIndex - 1]) == 1 && count($matrix[$lastColIndex - 2]) == 1) {
                $patterns[] = "單跳規律";
                $confidence += 20;
                $weight[$opposite] += 15; // 預期繼續單跳
            }
        }

        return [
            'name' => '大路',
            'weight' => $weight,
            'confidence' => $confidence,
            'patterns' => $patterns,
            'matrix' => $matrix
        ];
    }

    /**
     * 2. 通用特徵分析框架 (為下三路預留)
     */
    private function detectPatterns($roadName, $roadMatrix) {
        $weight = ['B' => 0, 'P' => 0]; // 下三路的 B/P 視為 紅/藍
        $confidence = 0;
        $detectedPatterns = [];

        // 未來這裡將接入大眼仔、小路、曱甴路的特定紅藍筆特徵判斷
        
        return [
            'name' => $roadName,
            'weight' => $weight,
            'confidence' => $confidence,
            'patterns' => $detectedPatterns
        ];
    }

    /**
     * 3. 四大核心獨立運算
     */
    private function analyzeFourRoads() {
        // 第一步：先建構大路矩陣
        $bigRoadMatrix = $this->buildBigRoadMatrix();
        
        // 第二步：分析大路特徵
        $bigRoad = $this->analyzeBigRoadPatterns($bigRoadMatrix);
        
        // 第三步：預留推算下三路
        $bigEyeBoy = $this->detectPatterns('大眼仔', []);
        $smallRoad = $this->detectPatterns('小路', []);
        $roachRoad = $this->detectPatterns('曱甴路', []);

        return [$bigRoad, $bigEyeBoy, $smallRoad, $roachRoad];
    }

    /**
     * 4. 最終預測建議 (AI 整合與防盲目偏向)
     */
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
        $confidenceThreshold = 35; // 可信度門檻設定

        $action = "NONE";
        $recommendation = "觀望 (防禦機制觸發)";
        $betAmount = 0;

        if ($diff > 10 && $totalConfidence >= $confidenceThreshold) {
            if ($totalB > $totalP) {
                $action = "B";
                $recommendation = "正打 莊 (B)";
                $betAmount = 100;
            } else {
                $action = "P";
                $recommendation = "正打 閒 (P)";
                $betAmount = 100;
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
            "dynamic_base" => [
                "B" => round($baseWeight['B'], 2),
                "P" => round($baseWeight['P'], 2)
            ],
            "final_weight" => [
                "B" => round($totalB, 2),
                "P" => round($totalP, 2)
            ],
            "total_confidence" => $totalConfidence,
            "cores_analysis" => $cores
        ];
    }
}

// 執行引擎並輸出結果
$engine = new QuantumBaccaratEngine($records);
echo json_encode($engine->generateRecommendation());
?>
