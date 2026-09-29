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
    
    // 理論天生勝率 (作為動態調整的基準底線)
    const THEORETICAL_B = 50.68;
    const THEORETICAL_P = 49.32;

    public function __construct($records) {
        $this->records = array_filter($records, function($val) {
            return $val === 'B' || $val === 'P';
        });
        $this->records = array_values($this->records);
    }

    /**
     * 1. 動態天生勝率 (Dynamic Base Weight)
     * 根據當前牌靴的實際開牌比例，動態微調天生勝率。
     * 牌局越長，當前牌靴的實際偏差權重佔比越高。
     */
    private function calculateDynamicBaseWeight() {
        $total = count($this->records);
        if ($total === 0) return ['B' => self::THEORETICAL_B, 'P' => self::THEORETICAL_P];

        $bCount = count(array_filter($this->records, fn($v) => $v === 'B'));
        $pCount = count(array_filter($this->records, fn($v) => $v === 'P'));

        $actualBRate = ($bCount / $total) * 100;
        $actualPRate = ($pCount / $total) * 100;

        // 動態平滑係數 (Smoothing Factor)：前 10 局偏重理論值，之後逐漸偏重實際盤勢
        $smoothing = min($total / 30, 0.8); // 最高 80% 參考當前靴實際盤勢

        $dynamicB = (self::THEORETICAL_B * (1 - $smoothing)) + ($actualBRate * $smoothing);
        $dynamicP = (self::THEORETICAL_P * (1 - $smoothing)) + ($actualPRate * $smoothing);

        return ['B' => $dynamicB, 'P' => $dynamicP];
    }

    /**
     * 2. 特徵分析引擎 (通用於四大路)
     * 分析 7 大特徵：「單跳」、「雙跳」、「龍」、「房廳」、「逢跳連」、「齊頭對齊」、「排排連」
     */
    private function detectPatterns($roadName, $roadMatrix) {
        // 初始化該路的權重與可信度
        $weight = ['B' => 0, 'P' => 0]; // 若是下三路，B/P 代表 紅/藍筆
        $confidence = 0;
        $detectedPatterns = [];

        // 這裡將置入二維矩陣的尋路邏輯 (下一步的重點)
        // 模擬特徵偵測機制：
        $length = count($this->records);
        if ($length > 5) {
            // 模擬偵測到「齊頭對齊」特徵 (強勢特徵，給予高權重與高可信度)
            $detectedPatterns[] = "齊頭對齊";
            $weight['P'] += 15; // 假設齊頭對齊強烈暗示下一口開 P (或藍)
            $confidence += 30;
            
            // 模擬偵測到「單跳」特徵
            $detectedPatterns[] = "單跳";
            $weight['P'] += 10;
            $confidence += 20;
        }

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
        // 未來這裡會傳入各自計算好的二維矩陣 ($matrix)
        $bigRoad = $this->detectPatterns('大路', []);
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
            return ["status" => "waiting", "recommendation" => "等待數據累積...", "action" => "NONE"];
        }

        // 取得動態天生勝率
        $baseWeight = $this->calculateDynamicBaseWeight();
        
        // 取得四大核心分析結果
        $cores = $this->analyzeFourRoads();

        $totalB = $baseWeight['B'];
        $totalP = $baseWeight['P'];
        $totalConfidence = 0;
        $conflictScore = 0; // 衝突指數 (防盲目偏向)

        // 整合四大核心權重
        foreach ($cores as $core) {
            $totalB += $core['weight']['B'];
            $totalP += $core['weight']['P'];
            $totalConfidence += $core['confidence'];
        }

        // 防盲目偏向機制 (防禦門檻)
        // 檢查四大路是否有嚴重衝突 (例如大路強烈看莊，但下三路全看閒)
        $diff = abs($totalB - $totalP);
        
        // 假設平均每個核心貢獻 10~20 可信度，總可信度需達到一定門檻
        $confidenceThreshold = 40; 

        $action = "NONE";
        $recommendation = "觀望 (防禦機制觸發)";

        // 如果權重差異太小(互相抵銷) 或 總可信度不足，強制觀望
        if ($diff > 10 && $totalConfidence >= $confidenceThreshold) {
            if ($totalB > $totalP) {
                $action = "B";
                $recommendation = "正打 莊 (B)";
            } else {
                $action = "P";
                $recommendation = "正打 閒 (P)";
            }
        } elseif ($diff <= 10) {
            $recommendation = "鎖死觀望 (路單衝突)";
        }

        return [
            "status" => "success",
            "action" => $action,
            "recommendation" => $recommendation,
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

$engine = new QuantumBaccaratEngine($records);
echo json_encode($engine->generateRecommendation());
?>
