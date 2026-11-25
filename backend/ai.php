<?php
require "bootstrap.php";

$user = teacher_required();
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    reply(["error" => "Invalid request method"], 405);
}

$encryptedKey = setting($db, "openai_api_key");
$apiKey = $encryptedKey
    ? decrypt_secret($encryptedKey, $config["app_secret"])
    : $config["openai_key"];

$allowedModels = ["gpt-4o-mini", "gpt-4o"];
$savedModel = setting($db, "openai_model");
$model = in_array($savedModel, $allowedModels, true)
    ? $savedModel
    : ($config["openai_model"] ?? "gpt-4o-mini");

if (!in_array($model, $allowedModels, true)) {
    $model = "gpt-4o-mini";
}

if (!$apiKey) {
    reply(["error" => "Admin must configure an OpenAI API key in AI settings first."], 422);
}

$data = input();
$topic = clean($data["topic"] ?? "");
$mcqCount = max(0, min(15, (int) ($data["mcq_count"] ?? 5)));
$shortCount = max(0, min(10, (int) ($data["short_count"] ?? 0)));
$mcqMarks = max(1, min(20, (int) ($data["mcq_marks"] ?? 1)));
$shortMarks = max(1, min(20, (int) ($data["short_marks"] ?? 5)));
$semester = max(1, min(8, (int) ($data["semester"] ?? 1)));
$timeLimit = max(1, min(180, (int) ($data["time_limit"] ?? 15)));
$categoryId = (int) ($data["category_id"] ?? 0);
$totalQuestions = $mcqCount + $shortCount;
$totalMarks = ($mcqCount * $mcqMarks) + ($shortCount * $shortMarks);

if ($topic === "") {
    reply(["error" => "Quiz topic is required"], 422);
}
if ($totalQuestions < 1 || $totalQuestions > 25) {
    reply(["error" => "Choose between 1 and 25 total questions"], 422);
}
if ($categoryId < 1) {
    reply(["error" => "Please select a category"], 422);
}

$systemPrompt = "You are an academic exam writer. Generate a quiz strictly in JSON format.
Your output must be a single JSON object with this exact structure:
{
  \"title\": \"Clear concise quiz title\",
  \"description\": \"One or two sentence quiz overview\",
  \"mcqs\": [
    {
      \"question\": \"Question text\",
      \"a\": \"Option A text\",
      \"b\": \"Option B text\",
      \"c\": \"Option C text\",
      \"d\": \"Option D text\",
      \"correct\": \"A\",
      \"explanation\": \"Brief explanation of why this answer is correct\"
    }
  ],
  \"short_questions\": [
    {
      \"question\": \"Short question text\",
      \"sample_answer\": \"Model answer or evaluation points for the teacher\"
    }
  ]
}
Important:
- Provide exactly {$mcqCount} items in 'mcqs'.
- Provide exactly {$shortCount} items in 'short_questions'.
- In 'mcqs', 'correct' must be one of 'A', 'B', 'C', or 'D'.
- Do not output any markdown code fences, return pure JSON.";

$body = [
    "model" => $model,
    "messages" => [
        ["role" => "system", "content" => $systemPrompt],
        ["role" => "user", "content" => "Topic: {$topic}. Semester: {$semester}. Create high-quality university academic questions."],
    ],
    "response_format" => ["type" => "json_object"],
    "temperature" => 0.7,
];

$curl = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 90,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . $apiKey,
        "Content-Type: application/json",
    ],
    CURLOPT_POSTFIELDS => json_encode($body),
]);

$raw = curl_exec($curl);
$status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = curl_error($curl);
curl_close($curl);

$response = json_decode($raw ?: "", true);
if ($status !== 200) {
    $msg = $response["error"]["message"] ?? $curlError ?: "OpenAI API returned status " . $status;
    reply(["error" => $msg], 502);
}

$rawContent = $response["choices"][0]["message"]["content"] ?? "";
$generated = json_decode($rawContent, true);

if (!$generated || empty($generated["title"])) {
    reply(["error" => "Failed to parse AI response into structured quiz format."], 502);
}

$mcqs = is_array($generated["mcqs"] ?? null) ? $generated["mcqs"] : [];
$shortQuestions = is_array($generated["short_questions"] ?? null) ? $generated["short_questions"] : [];

try {
    $db->beginTransaction();

    $statement = $db->prepare(
        "INSERT INTO quizzes(title, description, category_id, semester, time_limit, total_marks, is_active, publish_status, created_by)
         VALUES(?, ?, ?, ?, ?, ?, 1, 'draft', ?)",
    );
    $statement->execute([
        clean($generated["title"]),
        clean($generated["description"] ?? ""),
        $categoryId,
        $semester,
        $timeLimit,
        $totalMarks,
        $user["id"],
    ]);
    $quizId = (int) $db->lastInsertId();

    $questionStatement = $db->prepare(
        "INSERT INTO questions(quiz_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, marks, explanation)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    );

    foreach ($mcqs as $question) {
        $correct = strtoupper(clean($question["correct"] ?? "A"));
        if (!in_array($correct, ["A", "B", "C", "D"], true)) {
            $correct = "A";
        }
        $questionStatement->execute([
            $quizId,
            clean($question["question"] ?? "Untitled question"),
            "mcq",
            clean($question["a"] ?? ""),
            clean($question["b"] ?? ""),
            clean($question["c"] ?? ""),
            clean($question["d"] ?? ""),
            $correct,
            $mcqMarks,
            clean($question["explanation"] ?? ""),
        ]);
    }

    foreach ($shortQuestions as $question) {
        $questionStatement->execute([
            $quizId,
            clean($question["question"] ?? "Untitled question"),
            "short",
            "",
            "",
            "",
            "",
            "A",
            $shortMarks,
            clean($question["sample_answer"] ?? ""),
        ]);
    }

    $db->commit();
    reply(["quiz_id" => $quizId]);
} catch (Throwable $error) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    reply(["error" => "Could not save the generated quiz: " . $error->getMessage()], 500);
}
