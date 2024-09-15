<?php
// 出力バッファリングを無効化
ini_set('output_buffering', 'off');
ini_set('zlib.output_compression', false);
ob_implicit_flush(true);

// バッファリングが開始されている場合、終了する
while (ob_get_level()) {
    ob_end_flush();
}

// config.php をインポートしてAPIキーを読み込む
require 'config.php';

// クライアントから送られてきたデータを取得する
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// デバッグ用にリクエストデータをログに出力
error_log("Request data: " . print_r($data, true));

// 必須のmodelパラメータがあるか確認
if (!isset($data['model'])) {
    error_log("Model parameter is missing.");
    echo json_encode([
        "error" => [
            "message" => "Model parameter is missing",
            "type" => "invalid_request_error"
        ]
    ]);
    exit;  // 処理を終了
}

// OpenAI APIに送信するデータをログに記録
error_log("Sending request to OpenAI API with data: " . json_encode($data));

// APIリクエストを行う
$apiUrl = 'https://api.openai.com/v1/chat/completions'; // OpenAIのAPIエンドポイント
$headers = [
    'Authorization: Bearer ' . _AUTHO_KEY,
    'Content-Type: application/json'
];

// ヘッダーを最初に送信
header('Content-Type: application/json');

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

// リアルタイムにレスポンスを受信
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) {
    echo $chunk;  // クライアントにチャンクをリアルタイムで送信
    flush();      // クライアントに送信を即座に実行
    return strlen($chunk);  // 受信したチャンクの長さを返す
});

// リクエストを実行
$response = curl_exec($ch);

// HTTPステータスコードを取得
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

error_log("HTTP status code: " . $httpCode);
error_log("OpenAI API response: " . $response);

// エラーチェック
if (curl_errno($ch)) {
    $error_msg = curl_error($ch);
    error_log("cURL error: " . $error_msg);
    echo json_encode([
        "error" => [
            "message" => "cURL error: " . $error_msg,
            "type" => "request_error"
        ]
    ]);
} elseif ($httpCode >= 400) {
    error_log("HTTP error: " . $httpCode);
    echo json_encode([
        "error" => [
            "message" => "HTTP error occurred with status code: " . $httpCode,
            "type" => "http_error",
            "status_code" => $httpCode
        ]
    ]);
} elseif ($response === "1") {
    // cURLの結果が `1` であれば、実際に返されているレスポンス内容を確認
    error_log("Unexpected response: '1'");
    echo json_encode([
        "error" => [
            "message" => "Unexpected response: '1'",
            "type" => "response_error"
        ]
    ]);
} else {
    // 正常なレスポンスをクライアントに返す
    echo $response;
}

curl_close($ch);

?>
