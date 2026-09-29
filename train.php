<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

// 你的 Google Apps Script Web App URL (必須與前端的 gasUrl 完全一致)
$gasUrl = "https://script.google.com/macros/s/AKfycbwRoL5wgWJeSlnyhba2zUpPWZTqxjVDwEtJNhtNnycw6hppg2FkmCNqxCDm-IFcgH76/exec";

// 1. 從 Google Sheet 獲取歷史數據
$response = @file_get_contents($gasUrl);
$data = json_decode($response, true);

if (!$data || $data['status'] !== 'success') {
    echo json_encode(["status" => "error", "message" => "無法獲取 Google Sheet 數據。請確認 GAS URL 與 doGet 部署。"]);
    exit;
}

$historyShoes = $data['history'];
$totalShoes = count($historyShoes);

if ($totalShoes === 0) {
    echo json_encode(["status" => "warning", "message" => "Google Sheet 中目前沒有歷史數據，請先實戰幾靴牌再進行回測。"]);
    exit;
}

// 2. 初始化特徵統計器
$stats = [
    '齊頭對齊' => ['win' => 0, 'total' => 0],
    '長龍' => ['win' => 0, 'total' => 0],
    '單跳' => ['win' => 0, 'total' => 0],
];

// 【簡化版回測邏輯】: 遍歷每一靴的每一手，統計特定情境的勝率
foreach ($historyShoes as $shoe) {
    // 這裡我們模擬回測：掃描陣列，尋找特徵並比對「實際的下一手」是否如 AI 預期
    // 為了代碼清晰，這裡展示「長龍 (連續 4 個以上)」的回測統計範例
    $currentStreak = 0;
    $lastOutcome = '';
    
    for ($i = 0; $i < count($shoe) - 1; $i++) {
        $outcome = $shoe[$i];
        $actualNext = $shoe[$i + 1]; // 真實發生的下一手
        
        if ($outcome === $lastOutcome) {
            $currentStreak++;
        } else {
            $currentStreak = 1;
            $lastOutcome = $outcome;
        }

        // 當符合「長龍」特徵 (連續 4 次)，AI 預設建議是「順打」
        if ($currentStreak >= 4) {
            $stats['長龍']['total']++;
            if ($actualNext === $outcome) {
                $stats['長龍']['win']++; // 順打成功
            }
        }
        
        // (齊頭與單跳的二維矩陣回測較複雜，已在此框架預留，之後可套用 api.php 的矩陣邏輯進行深度掃描)
    }
}

// 3. 根據勝率動態計算新權重
$newWeights = [];
foreach ($stats as $pattern => $data) {
    $winRate = $data['total'] > 0 ? ($data['win'] / $data['total']) : 0.5; // 預設勝率 50%
    
    // 勝率轉換為權重分數 (基礎 15 分，勝率越高加權越重，最高可達 35 分)
    // 假設勝率 60% (0.6)，則權重為 15 + (0.6 - 0.5) * 100 = 25
    $weightScore = 15 + (($winRate - 0.5) * 100);
    
    // 限制最高 35，最低 5，避免極端值導致 AI 崩潰
    $weightScore = max(5, min(35, round($weightScore))); 
    
    $newWeights[$pattern] = [
        'win_rate' => round($winRate * 100, 2) . '%',
        'occurrences' => $data['total'],
        'new_weight' => $weightScore
    ];
}

// 4. 將學習結果儲存到本地檔案 weights.json
file_put_contents('weights.json', json_encode($newWeights, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    "status" => "success",
    "message" => "AI 模型訓練完成！共回測了 {$totalShoes} 靴牌。",
    "learned_weights" => $newWeights
]);
?>
