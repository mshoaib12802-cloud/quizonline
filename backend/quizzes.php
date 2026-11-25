<?php
require "bootstrap.php";

$viewer = login_required();
$action = $_GET["action"] ?? "list";

if ($action === "categories") {
    reply([
        "categories" => $db
            ->query("SELECT * FROM categories ORDER BY name")
            ->fetchAll(),
    ]);
}

if ($action === "list") {
    $category = (int) ($_GET["category"] ?? 0);
    $sql =
        "SELECT q.*, c.name AS category,
         (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS total,
         (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND question_type = 'mcq') AS mcq_count,
         (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND question_type = 'short') AS short_count
         FROM quizzes q
         JOIN categories c ON c.id = q.category_id
         WHERE q.is_active = 1 AND q.publish_status = 'published'";
    $args = [];

    if ($category > 0) {
        $sql .= " AND c.id = ?";
        $args[] = $category;
    }

    if ($viewer["role"] === "student" && !empty($viewer["semester"])) {
        $sql .= " AND q.semester = ?";
        $args[] = (int) $viewer["semester"];
    }

    $s = $db->prepare($sql . " ORDER BY q.created_at DESC");
    $s->execute($args);
    reply(["quizzes" => $s->fetchAll()]);
}

if ($action === "one") {
    $quizId = (int) ($_GET["id"] ?? 0);
    $sql =
        "SELECT q.*, c.name AS category,
         (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS total,
         (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND question_type = 'mcq') AS mcq_count,
         (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id AND question_type = 'short') AS short_count
         FROM quizzes q
         JOIN categories c ON c.id = q.category_id
         WHERE q.id = ? AND q.is_active = 1 AND q.publish_status = 'published'";
    $args = [$quizId];

    if ($viewer["role"] === "student" && !empty($viewer["semester"])) {
        $sql .= " AND q.semester = ?";
        $args[] = (int) $viewer["semester"];
    }

    $s = $db->prepare($sql);
    $s->execute($args);
    $quiz = $s->fetch();

    if (!$quiz) {
        reply(["error" => "Quiz not found or not published for your semester"], 404);
    }
    reply(["quiz" => $quiz]);
}

if ($action === "questions") {
    $quizId = (int) ($_GET["id"] ?? 0);
    $sql =
        "SELECT qu.id, qu.question_text, qu.question_type, qu.marks, qu.option_a, qu.option_b, qu.option_c, qu.option_d
         FROM questions qu
         JOIN quizzes q ON q.id = qu.quiz_id
         WHERE qu.quiz_id = ? AND q.is_active = 1 AND q.publish_status = 'published'";
    $args = [$quizId];

    if ($viewer["role"] === "student" && !empty($viewer["semester"])) {
        $sql .= " AND q.semester = ?";
        $args[] = (int) $viewer["semester"];
    }

    $s = $db->prepare($sql . " ORDER BY qu.id ASC");
    $s->execute($args);
    reply(["questions" => $s->fetchAll()]);
}

reply(["error" => "Invalid request"], 404);
