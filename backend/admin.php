<?php
require "bootstrap.php";

$u = teacher_required();
$action = $_GET["action"] ?? "overview";

function owned_quiz(PDO $db, array $user, int $quizId): array
{
    $sql = "SELECT * FROM quizzes WHERE id = ?";
    $args = [$quizId];
    if ($user["role"] !== "admin") {
        $sql .= " AND created_by = ?";
        $args[] = (int) $user["id"];
    }
    $s = $db->prepare($sql);
    $s->execute($args);
    $quiz = $s->fetch();
    if (!$quiz) {
        reply(["error" => "Quiz not found or access denied"], 404);
    }
    return $quiz;
}

if ($action === "overview") {
    $quizWhere = $u["role"] === "admin" ? "" : " WHERE q.created_by = " . (int) $u["id"];
    $quizzes = $db
        ->query(
            "SELECT q.*, c.name AS category,
             (SELECT COUNT(*) FROM questions WHERE quiz_id = q.id) AS questions
             FROM quizzes q
             JOIN categories c ON c.id = q.category_id" .
             $quizWhere .
             " ORDER BY q.id DESC",
        )
        ->fetchAll();

    $students = $db
        ->query(
            "SELECT u.id, u.name, u.email, u.roll_number, u.semester, u.department,
             COUNT(a.id) AS attempts,
             COALESCE(ROUND(AVG(CASE WHEN a.status = 'published' THEN a.percentage END)), 0) AS average
             FROM users u
             LEFT JOIN attempts a ON a.user_id = u.id
             WHERE u.role = 'student'
             GROUP BY u.id, u.name, u.email, u.roll_number, u.semester, u.department
             ORDER BY u.id DESC",
        )
        ->fetchAll();

    $users = $u["role"] === "admin"
        ? $db->query(
            "SELECT id, name, email, roll_number, semester, department, phone, role, is_active, profile_image, created_at
             FROM users
             ORDER BY created_at DESC",
          )->fetchAll()
        : [];

    $pendingSql =
        "SELECT a.id, u.name AS student, u.roll_number, q.title, q.semester, a.created_at, creator.name AS quiz_creator
         FROM attempts a
         JOIN users u ON u.id = a.user_id
         JOIN quizzes q ON q.id = a.quiz_id
         LEFT JOIN users creator ON creator.id = q.created_by
         WHERE a.status = 'submitted'";
    if ($u["role"] !== "admin") {
        $pendingSql .= " AND q.created_by = " . (int) $u["id"];
    }
    $pendingSql .= " ORDER BY a.created_at ASC";

    $pending = $db->query($pendingSql)->fetchAll();

    reply(compact("quizzes", "students", "users", "pending"));
}

if ($action === "save_user" && $_SERVER["REQUEST_METHOD"] === "POST") {
    admin_required();
    $d = input();
    $id = (int) ($d["id"] ?? 0);
    $role = in_array($d["role"] ?? "", ["student", "teacher", "admin"], true) ? $d["role"] : "student";
    $name = clean($d["name"] ?? "");
    $email = strtolower(clean($d["email"] ?? ""));

    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        reply(["error" => "Valid name and email are required"], 422);
    }

    $semester = (!empty($d["semester"]) && is_numeric($d["semester"])) ? (int) $d["semester"] : null;
    $isActive = !empty($d["is_active"]) ? 1 : 0;

    try {
        if ($id > 0) {
            $sql = "UPDATE users SET name=?, email=?, roll_number=?, semester=?, department=?, phone=?, gender=?, role=?, is_active=?";
            $args = [
                $name,
                $email,
                clean($d["roll_number"] ?? "") ?: null,
                $semester,
                clean($d["department"] ?? ""),
                clean($d["phone"] ?? ""),
                clean($d["gender"] ?? ""),
                $role,
                $isActive,
            ];
            if (!empty($d["password"])) {
                $sql .= ", password=?";
                $args[] = password_hash((string) $d["password"], PASSWORD_DEFAULT);
            }
            $sql .= " WHERE id=?";
            $args[] = $id;
            $db->prepare($sql)->execute($args);
        } else {
            if (strlen((string) ($d["password"] ?? "")) < 4) {
                reply(["error" => "Password must be at least 4 characters"], 422);
            }
            $s = $db->prepare(
                "INSERT INTO users(name, email, roll_number, semester, department, phone, gender, password, role, is_active)
                 VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            );
            $s->execute([
                $name,
                $email,
                clean($d["roll_number"] ?? "") ?: null,
                $semester,
                clean($d["department"] ?? ""),
                clean($d["phone"] ?? ""),
                clean($d["gender"] ?? ""),
                password_hash((string) $d["password"], PASSWORD_DEFAULT),
                $role,
                $isActive,
            ]);
            $id = (int) $db->lastInsertId();
        }
        reply(["id" => $id, "message" => "User saved successfully"]);
    } catch (PDOException $e) {
        if ($e->getCode() === "23000") {
            reply(["error" => "This email or roll number is already registered"], 409);
        }
        reply(["error" => "Could not save user: " . $e->getMessage()], 500);
    }
}

if ($action === "user") {
    admin_required();
    $s = $db->prepare(
        "SELECT id, name, email, roll_number, semester, department, phone, gender, role, is_active FROM users WHERE id = ?",
    );
    $s->execute([(int) ($_GET["id"] ?? 0)]);
    $account = $s->fetch();
    if (!$account) {
        reply(["error" => "User not found"], 404);
    }
    reply(["user" => $account]);
}

if ($action === "delete_user" && $_SERVER["REQUEST_METHOD"] === "POST") {
    admin_required();
    $d = input();
    $targetId = (int) ($d["id"] ?? 0);
    if ($targetId === (int) $u["id"]) {
        reply(["error" => "You cannot delete your own account"], 422);
    }
    $db->prepare("DELETE FROM users WHERE id = ?")->execute([$targetId]);
    reply(["message" => "User deleted"]);
}

if ($action === "create_quiz" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $title = clean($d["title"] ?? "");
    $catId = (int) ($d["category_id"] ?? 0);

    if (!$title) {
        reply(["error" => "Quiz title is required"], 422);
    }
    if ($catId <= 0) {
        reply(["error" => "Please select a category"], 422);
    }

    $s = $db->prepare(
        "INSERT INTO quizzes(title, description, category_id, semester, time_limit, total_marks, is_active, publish_status, created_by)
         VALUES(?, ?, ?, ?, ?, 0, 1, 'draft', ?)",
    );
    $s->execute([
        $title,
        clean($d["description"] ?? ""),
        $catId,
        max(1, min(8, (int) ($d["semester"] ?? 1))),
        max(1, (int) ($d["time_limit"] ?? 10)),
        $u["id"],
    ]);
    reply(["quiz_id" => (int) $db->lastInsertId()]);
}

if ($action === "publish_quiz" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $quizId = (int) ($d["id"] ?? 0);
    owned_quiz($db, $u, $quizId);

    $count = $db->prepare("SELECT COUNT(*) FROM questions WHERE quiz_id = ?");
    $count->execute([$quizId]);
    if ((int) $count->fetchColumn() === 0) {
        reply(["error" => "Add at least one question before publishing"], 422);
    }

    $db->prepare("UPDATE quizzes SET publish_status = 'published', published_at = NOW() WHERE id = ?")
       ->execute([$quizId]);
    reply(["message" => "Quiz published successfully"]);
}

if ($action === "add_question" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $quizId = (int) ($d["quiz_id"] ?? 0);
    owned_quiz($db, $u, $quizId);

    $type = ($d["question_type"] ?? "mcq") === "short" ? "short" : "mcq";
    $questionText = clean($d["question"] ?? "");
    if (!$questionText) {
        reply(["error" => "Question text is required"], 422);
    }

    $s = $db->prepare(
        "INSERT INTO questions(quiz_id, question_text, question_type, option_a, option_b, option_c, option_d, correct_option, marks, explanation)
         VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
    );
    $s->execute([
        $quizId,
        $questionText,
        $type,
        clean($d["a"] ?? ""),
        clean($d["b"] ?? ""),
        clean($d["c"] ?? ""),
        clean($d["d"] ?? ""),
        $type === "mcq" ? strtoupper(clean($d["correct"] ?? "A")) : "A",
        max(1, (int) ($d["marks"] ?? 1)),
        clean($d["explanation"] ?? ""),
    ]);

    $db->prepare(
        "UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks), 0) FROM questions WHERE quiz_id = ?) WHERE id = ?",
    )->execute([$quizId, $quizId]);

    reply(["message" => "Question added"]);
}

if ($action === "grading") {
    $id = (int) ($_GET["id"] ?? 0);
    $s = $db->prepare(
        "SELECT a.*, u.name AS student, u.roll_number, q.title, q.created_by
         FROM attempts a
         JOIN users u ON u.id = a.user_id
         JOIN quizzes q ON q.id = a.quiz_id
         WHERE a.id = ?",
    );
    $s->execute([$id]);
    $attempt = $s->fetch();

    if (!$attempt) {
        reply(["error" => "Submission not found"], 404);
    }
    if ($u["role"] !== "admin" && (int) $attempt["created_by"] !== (int) $u["id"]) {
        reply(["error" => "Access denied to grade this submission"], 403);
    }

    $s = $db->prepare(
        "SELECT aa.id, aa.attempt_id, aa.question_id, aa.selected_option, aa.text_answer,
                aa.is_correct, aa.awarded_marks, aa.feedback,
                q.question_text, q.question_type, q.marks, q.correct_option,
                q.option_a, q.option_b, q.option_c, q.option_d, q.explanation
         FROM attempt_answers aa
         JOIN questions q ON q.id = aa.question_id
         WHERE aa.attempt_id = ?
         ORDER BY q.id ASC",
    );
    $s->execute([$id]);
    reply(["attempt" => $attempt, "answers" => $s->fetchAll()]);
}

if ($action === "publish_grade" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $id = (int) ($d["attempt_id"] ?? 0);

    $check = $db->prepare("SELECT q.created_by FROM attempts a JOIN quizzes q ON q.id = a.quiz_id WHERE a.id = ?");
    $check->execute([$id]);
    $creator = $check->fetchColumn();

    if ($creator === false) {
        reply(["error" => "Submission not found"], 404);
    }
    if ($u["role"] !== "admin" && (int) $creator !== (int) $u["id"]) {
        reply(["error" => "Access denied to grade this submission"], 403);
    }

    $s = $db->prepare(
        "UPDATE attempt_answers aa
         JOIN questions q ON q.id = aa.question_id
         SET aa.awarded_marks = LEAST(q.marks, GREATEST(0, ?)),
             aa.feedback = ?,
             aa.is_correct = IF(LEAST(q.marks, GREATEST(0, ?)) >= q.marks, 1, 0)
         WHERE aa.id = ? AND aa.attempt_id = ?",
    );

    if (isset($d["answers"]) && is_array($d["answers"])) {
        foreach ($d["answers"] as $a) {
            $marksAwarded = (float) ($a["marks"] ?? 0);
            $s->execute([
                $marksAwarded,
                clean($a["feedback"] ?? ""),
                $marksAwarded,
                (int) $a["id"],
                $id,
            ]);
        }
    }

    $sum = $db->prepare("SELECT COALESCE(SUM(awarded_marks), 0) FROM attempt_answers WHERE attempt_id = ?");
    $sum->execute([$id]);
    $score = (float) $sum->fetchColumn();

    $total = $db->prepare("SELECT total FROM attempts WHERE id = ?");
    $total->execute([$id]);
    $max = (float) $total->fetchColumn();

    $db->prepare(
        "UPDATE attempts SET score = ?, percentage = ?, status = 'published', teacher_feedback = ?, graded_at = NOW() WHERE id = ?",
    )->execute([
        $score,
        $max > 0 ? round(($score / $max) * 100, 2) : 0,
        clean($d["feedback"] ?? ""),
        $id,
    ]);

    reply(["message" => "Result published to student"]);
}

if ($action === "quiz") {
    $quizId = (int) ($_GET["id"] ?? 0);
    $quiz = owned_quiz($db, $u, $quizId);
    $s = $db->prepare("SELECT * FROM questions WHERE quiz_id = ? ORDER BY id ASC");
    $s->execute([$quizId]);
    reply(["quiz" => $quiz, "questions" => $s->fetchAll()]);
}

if ($action === "update_quiz" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $quizId = (int) ($d["id"] ?? 0);
    owned_quiz($db, $u, $quizId);

    $s = $db->prepare(
        "UPDATE quizzes SET title = ?, description = ?, category_id = ?, semester = ?, time_limit = ? WHERE id = ?",
    );
    $s->execute([
        clean($d["title"] ?? ""),
        clean($d["description"] ?? ""),
        (int) ($d["category_id"] ?? 1),
        max(1, min(8, (int) ($d["semester"] ?? 1))),
        max(1, (int) ($d["time_limit"] ?? 10)),
        $quizId,
    ]);
    reply(["quiz_id" => $quizId, "message" => "Quiz updated"]);
}

if ($action === "update_question" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $questionId = (int) ($d["id"] ?? 0);
    $find = $db->prepare("SELECT quiz_id FROM questions WHERE id = ?");
    $find->execute([$questionId]);
    $quizId = (int) $find->fetchColumn();
    owned_quiz($db, $u, $quizId);

    $type = ($d["question_type"] ?? "mcq") === "short" ? "short" : "mcq";
    $s = $db->prepare(
        "UPDATE questions SET question_text = ?, question_type = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, marks = ?, explanation = ? WHERE id = ?",
    );
    $s->execute([
        clean($d["question"] ?? ""),
        $type,
        clean($d["a"] ?? ""),
        clean($d["b"] ?? ""),
        clean($d["c"] ?? ""),
        clean($d["d"] ?? ""),
        $type === "mcq" ? strtoupper(clean($d["correct"] ?? "A")) : "A",
        max(1, (int) ($d["marks"] ?? 1)),
        clean($d["explanation"] ?? ""),
        $questionId,
    ]);

    $db->prepare(
        "UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks), 0) FROM questions WHERE quiz_id = ?) WHERE id = ?",
    )->execute([$quizId, $quizId]);

    reply(["message" => "Question updated"]);
}

if ($action === "delete_question" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $questionId = (int) ($d["id"] ?? 0);
    $find = $db->prepare("SELECT quiz_id FROM questions WHERE id = ?");
    $find->execute([$questionId]);
    $quizId = (int) $find->fetchColumn();
    owned_quiz($db, $u, $quizId);

    $db->prepare("DELETE FROM questions WHERE id = ?")->execute([$questionId]);
    $db->prepare(
        "UPDATE quizzes SET total_marks = (SELECT COALESCE(SUM(marks), 0) FROM questions WHERE quiz_id = ?) WHERE id = ?",
    )->execute([$quizId, $quizId]);

    reply(["message" => "Question deleted"]);
}

if ($action === "categories") {
    admin_required();
    $categories = $db
        ->query(
            "SELECT c.id, c.name, COUNT(q.id) AS quiz_count
             FROM categories c
             LEFT JOIN quizzes q ON q.category_id = c.id
             GROUP BY c.id, c.name
             ORDER BY c.name ASC",
        )
        ->fetchAll();
    reply(["categories" => $categories]);
}

if ($action === "save_category" && $_SERVER["REQUEST_METHOD"] === "POST") {
    admin_required();
    $d = input();
    $id = (int) ($d["id"] ?? 0);
    $name = clean($d["name"] ?? "");
    if (!$name) {
        reply(["error" => "Category name is required"], 422);
    }
    try {
        if ($id > 0) {
            $db->prepare("UPDATE categories SET name = ? WHERE id = ?")->execute([$name, $id]);
        } else {
            $s = $db->prepare("INSERT INTO categories(name) VALUES(?)");
            $s->execute([$name]);
            $id = (int) $db->lastInsertId();
        }
    } catch (PDOException $e) {
        reply(["error" => "Category name already exists"], 409);
    }
    reply(["id" => $id, "message" => "Category saved"]);
}

if ($action === "delete_category" && $_SERVER["REQUEST_METHOD"] === "POST") {
    admin_required();
    $d = input();
    $id = (int) ($d["id"] ?? 0);
    $count = $db->prepare("SELECT COUNT(*) FROM quizzes WHERE category_id = ?");
    $count->execute([$id]);
    if ((int) $count->fetchColumn() > 0) {
        reply(["error" => "Cannot delete category: quizzes are assigned to it"], 422);
    }
    $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
    reply(["message" => "Category deleted"]);
}

if ($action === "delete_quiz" && $_SERVER["REQUEST_METHOD"] === "POST") {
    $d = input();
    $quizId = (int) ($d["id"] ?? 0);
    owned_quiz($db, $u, $quizId);
    $db->prepare("DELETE FROM quizzes WHERE id = ?")->execute([$quizId]);
    reply(["message" => "Quiz deleted"]);
}

reply(["error" => "Invalid request"], 404);
