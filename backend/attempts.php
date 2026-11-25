<?php
require "bootstrap.php";

$u = login_required();
$action = $_GET["action"] ?? "";

if ($action === "submit" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $quizId = (int) ($d["quiz_id"] ?? 0);

    if ($u["role"] !== "student") {
        reply(["error" => "Only students can submit quizzes"], 403);
    }

    $allowedSql = "SELECT id FROM quizzes WHERE id = ? AND is_active = 1 AND publish_status = 'published'";
    $allowedArgs = [$quizId];
    if (!empty($u["semester"])) {
        $allowedSql .= " AND semester = ?";
        $allowedArgs[] = (int) $u["semester"];
    }

    $allowed = $db->prepare($allowedSql);
    $allowed->execute($allowedArgs);
    if (!$allowed->fetch()) {
        reply(["error" => "This quiz is not available for your semester"], 403);
    }

    $s = $db->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
    $s->execute([$quizId]);
    $questions = $s->fetchAll();

    if (!$questions) {
        reply(["error" => "Quiz has no questions"], 422);
    }

    $earned = 0.0;
    $total = 0;
    $hasShort = false;
    $rows = [];

    foreach ($questions as $q) {
        $total += (int) $q["marks"];
        $value = $d["answers"][$q["id"]] ?? null;

        if ($q["question_type"] === "mcq") {
            $formattedOpt = ($value !== null && $value !== "") ? strtoupper(clean((string) $value)) : null;
            $selectedOpt = in_array($formattedOpt, ['A', 'B', 'C', 'D'], true) ? $formattedOpt : null;
            $isCorrect = ($selectedOpt !== null && $selectedOpt === strtoupper((string) $q["correct_option"]));
            $marks = $isCorrect ? (float) $q["marks"] : 0.0;
            $earned += $marks;
            $rows[] = [
                $q["id"],
                $selectedOpt,
                null,
                $isCorrect ? 1 : 0,
                $marks,
            ];
        } else {
            $hasShort = true;
            $rows[] = [
                $q["id"],
                null,
                clean((string) $value),
                0,
                0.0,
            ];
        }
    }

    $status = $hasShort ? "submitted" : "published";
    $percentage = $total > 0 ? round(($earned / $total) * 100, 2) : 0.0;

    $db->beginTransaction();
    try {
        $s = $db->prepare(
            "INSERT INTO attempts(user_id, quiz_id, score, total, percentage, status) VALUES(?, ?, ?, ?, ?, ?)",
        );
        $s->execute([$u["id"], $quizId, $earned, $total, $percentage, $status]);
        $id = (int) $db->lastInsertId();

        $s = $db->prepare(
            "INSERT INTO attempt_answers(attempt_id, question_id, selected_option, text_answer, is_correct, awarded_marks) VALUES(?, ?, ?, ?, ?, ?)",
        );
        foreach ($rows as $r) {
            $s->execute([$id, ...$r]);
        }
        $db->commit();
        reply(["attempt_id" => $id, "status" => $status]);
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        reply(["error" => "Could not record attempt: " . $e->getMessage()], 500);
    }
}

if ($action === "result") {
    $attemptId = (int) ($_GET["id"] ?? 0);
    $s = $db->prepare(
        "SELECT a.*, q.title, q.semester
         FROM attempts a
         JOIN quizzes q ON q.id = a.quiz_id
         WHERE a.id = ? AND a.user_id = ?",
    );
    $s->execute([$attemptId, $u["id"]]);
    $result = $s->fetch();

    if (!$result) {
        reply(["error" => "Result not found"], 404);
    }

    $answers = [];
    if ($result["status"] === "published") {
        $s = $db->prepare(
            "SELECT aa.id AS answer_id, aa.attempt_id, aa.question_id, aa.selected_option, aa.text_answer,
                    aa.is_correct, aa.awarded_marks, aa.feedback,
                    q.question_text, q.question_type, q.marks, q.correct_option,
                    q.option_a, q.option_b, q.option_c, q.option_d, q.explanation
             FROM attempt_answers aa
             JOIN questions q ON q.id = aa.question_id
             WHERE aa.attempt_id = ?
             ORDER BY q.id ASC",
        );
        $s->execute([$result["id"]]);
        $answers = $s->fetchAll();
    }
    reply(["result" => $result, "answers" => $answers]);
}

if ($action === "dashboard") {
    $s = $db->prepare(
        "SELECT COUNT(*) AS attempts,
         COALESCE(ROUND(AVG(CASE WHEN status = 'published' THEN percentage END)), 0) AS average,
         COALESCE(MAX(CASE WHEN status = 'published' THEN percentage END), 0) AS best
         FROM attempts WHERE user_id = ?",
    );
    $s->execute([$u["id"]]);
    $stats = $s->fetch();

    $s = $db->prepare(
        "SELECT a.*, q.title, q.semester
         FROM attempts a
         JOIN quizzes q ON q.id = a.quiz_id
         WHERE a.user_id = ?
         ORDER BY a.created_at DESC",
    );
    $s->execute([$u["id"]]);
    reply(["stats" => $stats, "history" => $s->fetchAll()]);
}

reply(["error" => "Invalid request"], 404);
