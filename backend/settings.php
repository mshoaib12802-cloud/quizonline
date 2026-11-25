<?php
require "bootstrap.php";

admin_required();
ensure_settings_table($db);

$action = $_GET["action"] ?? "status";
$allowedModels = ["gpt-4o-mini", "gpt-4o"];

if ($action === "status" && $_SERVER["REQUEST_METHOD"] === "GET") {
    $encrypted = setting($db, "openai_api_key");
    $key = $encrypted
        ? decrypt_secret($encrypted, $config["app_secret"])
        : ($config["openai_key"] ?? "");
    $savedModel = setting($db, "openai_model");
    reply([
        "configured" => $key !== "",
        "masked_key" => $key ? "••••••••" . substr($key, -4) : "",
        "model" => in_array($savedModel, $allowedModels, true)
            ? $savedModel
            : ($config["openai_model"] ?? "gpt-4o-mini"),
    ]);
}

if ($action === "save" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $data = input();
    $apiKey = clean($data["api_key"] ?? "");
    $model = clean($data["model"] ?? "");

    if ($apiKey !== "" && strlen($apiKey) < 15) {
        reply(["error" => "Please enter a valid OpenAI API key"], 422);
    }
    if (!in_array($model, $allowedModels, true)) {
        $model = "gpt-4o-mini";
    }

    $db->beginTransaction();
    if ($apiKey !== "") {
        $statement = $db->prepare(
            "INSERT INTO app_settings(setting_key, setting_value) VALUES('openai_api_key', ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
        );
        $statement->execute([
            encrypt_secret($apiKey, $config["app_secret"]),
        ]);
    }
    $statement = $db->prepare(
        "INSERT INTO app_settings(setting_key, setting_value) VALUES('openai_model', ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
    );
    $statement->execute([$model]);
    $db->commit();
    reply(["message" => "AI settings saved securely"]);
}

if ($action === "delete" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $statement = $db->prepare(
        "DELETE FROM app_settings WHERE setting_key = 'openai_api_key'",
    );
    $statement->execute();
    reply(["message" => "OpenAI API key removed"]);
}

reply(["error" => "Invalid request method"], 405);
