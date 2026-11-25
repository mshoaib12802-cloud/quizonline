const API = "../backend/";
let pendingRequests = 0;

function ensureLoader() {
  if (document.querySelector("#global-loader")) return;
  document.body.insertAdjacentHTML(
    "beforeend",
    `<div class="global-loader" id="global-loader" hidden aria-live="polite" aria-busy="true">
      <div class="loader-progress"><span></span></div>
      <div class="loader-card">
        <div class="loader-spinner" aria-hidden="true"><span></span><span></span><span></span></div>
        <b id="loader-title">Please wait...</b>
        <p id="loader-message">Your request is being processed.</p>
        <small id="loader-time"></small>
      </div>
    </div>`,
  );
}

function startLoading(file) {
  ensureLoader();
  pendingRequests += 1;
  const loader = document.querySelector("#global-loader");
  const isAI = file.startsWith("ai.php");
  loader.hidden = false;
  document.body.classList.add("is-processing");
  document.querySelector("#loader-title").textContent = isAI
    ? "Generating your quiz..."
    : "Processing...";
  document.querySelector("#loader-message").textContent = isAI
    ? "AI is preparing questions and answers. This can take up to a minute."
    : "Please wait while QuizOnline completes your request.";
  document.querySelector("#loader-time").textContent = "";
  if (isAI) {
    loader.dataset.timer = setTimeout(() => {
      const time = document.querySelector("#loader-time");
      if (time) time.textContent = "Still working — please keep this page open.";
    }, 8000);
  }
  const activeButton = document.activeElement?.closest?.("button");
  if (activeButton && !activeButton.disabled) {
    activeButton.disabled = true;
    activeButton.classList.add("button-loading");
    activeButton.dataset.loadingText = activeButton.textContent;
    activeButton.textContent = isAI ? "Generating..." : "Please wait...";
    return activeButton;
  }
  return null;
}

function stopLoading(activeButton) {
  pendingRequests = Math.max(0, pendingRequests - 1);
  if (activeButton) {
    activeButton.disabled = false;
    activeButton.classList.remove("button-loading");
    activeButton.textContent = activeButton.dataset.loadingText || "Submit";
    delete activeButton.dataset.loadingText;
  }
  if (pendingRequests === 0) {
    const loader = document.querySelector("#global-loader");
    if (loader) {
      clearTimeout(Number(loader.dataset.timer));
      loader.hidden = true;
    }
    document.body.classList.remove("is-processing");
  }
}

async function api(file, options = {}) {
  const activeButton = startLoading(file);
  try {
    const r = await fetch(API + file, {
      headers: { "Content-Type": "application/json" },
      ...options,
    });
    const text = await r.text();
    let d;
    try {
      d = text ? JSON.parse(text) : {};
    } catch (parseErr) {
      const cleanMessage = text.replace(/<[^>]*>/g, " ").replace(/\s+/g, " ").trim();
      throw new Error(cleanMessage || `Server returned an empty or invalid response (HTTP ${r.status})`);
    }
    if (!r.ok) throw new Error(d.error || `Request failed with status ${r.status}`);
    return d;
  } finally {
    stopLoading(activeButton);
  }
}
const $ = (s) => document.querySelector(s),
  esc = (s) =>
    String(s ?? "").replace(
      /[&<>'"]/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[c],
    );
async function auth(required = true) {
  const { user } = await api("auth.php?action=me");
  if (required && !user) {
    location = "login.html";
    return null;
  }
  const manageLink = document.querySelector("#manage-link");
  if (manageLink && user && ["admin", "teacher"].includes(user.role)) {
    manageLink.hidden = false;
  }
  if (user) {
    document
      .querySelectorAll(".role-management")
      .forEach(
        (item) => (item.hidden = !["admin", "teacher"].includes(user.role)),
      );
    document
      .querySelectorAll(".role-admin")
      .forEach((item) => (item.hidden = user.role !== "admin"));
    const roleLabel = document.querySelector("#sidebar-role");
    const topbarUser = document.querySelector("#topbar-user");
    const avatar = document.querySelector("#topbar-avatar");
    if (roleLabel) roleLabel.textContent = user.role.toUpperCase();
    if (topbarUser) topbarUser.textContent = user.name;
    if (avatar) {
      avatar.textContent = user.name.charAt(0).toUpperCase();
      if (user.profile_image)
        avatar.style.backgroundImage = `url('${user.profile_image}')`;
    }
    const page = location.pathname.split("/").pop().replace(".html", "");
    document.querySelector(`[data-page="${page}"]`)?.classList.add("active");
  }
  return user;
}
function toggleSidebar() {
  document.body.classList.toggle("sidebar-open");
}
function error(e) {
  const msgEl = $("#message");
  if (msgEl) {
    msgEl.innerHTML = `<div class="error">${esc(e.message)}</div>`;
  } else {
    alert(e.message);
  }
}
async function login() {
  try {
    const d = await api("auth.php?action=login", {
      method: "POST",
      body: JSON.stringify({
        email: $("#email").value,
        password: $("#password").value,
      }),
    });
    location = ["admin", "teacher"].includes(d.user.role)
      ? "admin.html"
      : "dashboard.html";
  } catch (e) {
    error(e);
  }
}
async function register() {
  try {
    await api("auth.php?action=register", {
      method: "POST",
      body: JSON.stringify({
        name: $("#name").value,
        email: $("#email").value,
        password: $("#password").value,
        role: $("#role").value,
      }),
    });
    location = "login.html";
  } catch (e) {
    error(e);
  }
}
async function dashboard() {
  const u = await auth(),
    d = await api("attempts.php?action=dashboard"),
    q = await api("quizzes.php?action=list");
  $("#welcome").textContent = "Welcome, " + u.name;
  $("#stats").innerHTML =
    `<div class="card"><div class="stat">${d.stats.attempts}</div>Attempts</div><div class="card"><div class="stat">${d.stats.average}%</div>Average</div><div class="card"><div class="stat">${d.stats.best}%</div>Best</div>`;
  renderQuizzes(q.quizzes);
  renderHistory(d.history);
}
function renderQuizzes(list) {
  $("#quiz-list").innerHTML =
    list
      .map(
        (q) =>
          `<div class="card"><small>${esc(q.category)}</small><h3>${esc(q.title)}</h3><p>${esc(q.description)}</p><p>${q.total} questions · ${q.total_marks} marks · ${q.time_limit} minutes</p><a class="button" href="instructions.html?id=${q.id}">Open</a></div>`,
      )
      .join("") || "<p>No quizzes found.</p>";
}
function renderHistory(list) {
  $("#history").innerHTML =
    `<tr><th>Quiz</th><th>Semester</th><th>Score</th><th>Status</th><th>Review</th></tr>` +
    list
      .map(
        (r) =>
          `<tr><td>${esc(r.title)}</td><td>Semester ${r.semester}</td><td>${r.status === "published" ? `${r.score}/${r.total} (${r.percentage}%)` : "Pending"}</td><td><span class="status ${r.status}">${esc(r.status)}</span></td><td><a href="result.html?id=${r.id}">View</a></td></tr>`,
      )
      .join("");
}
async function quizzes() {
  await auth();
  const d = await api("quizzes.php?action=list");
  renderQuizzes(d.quizzes);
}
async function instructions() {
  await auth();
  const id = new URLSearchParams(location.search).get("id"),
    { quiz: q } = await api("quizzes.php?action=one&id=" + id);
  $("#content").innerHTML =
    `<div class="form-card"><small>${esc(q.category)}</small><h1>${esc(q.title)}</h1><p>${esc(q.description)}</p><div class="instruction-stats"><span><b>${q.total}</b>Total questions</span><span><b>${q.mcq_count}</b>MCQs</span><span><b>${q.short_count}</b>Short questions</span><span><b>${q.total_marks}</b>Total marks</span><span><b>${q.time_limit}</b>Minutes</span></div><ul><li>Marks for each question will be displayed on the screen during the quiz.</li><li>Multiple choice questions are graded automatically; short answers are reviewed by your teacher.</li><li>The quiz will automatically submit when the timer expires.</li><li>Detailed results and answer reviews will be available upon completion or teacher grading.</li></ul><a class="button" href="attempt.html?id=${q.id}">Start Quiz</a></div>`;
}
let quizId,
  questions,
  index = 0,
  answers = {},
  clock;
async function attempt() {
  await auth();
  quizId = new URLSearchParams(location.search).get("id");
  const [{ quiz: q }, { questions: list }] = await Promise.all([
    api("quizzes.php?action=one&id=" + quizId),
    api("quizzes.php?action=questions&id=" + quizId),
  ]);
  questions = list || [];
  $("#attempt-total-marks").textContent = q.total_marks;
  $("#answered-count").textContent = `0/${questions.length}`;

  if (!questions.length) {
    $("#question").innerHTML = "<p>This quiz does not have any questions yet. Please check back later.</p>";
    return;
  }

  showQuestion();
  let left = q.time_limit * 60;
  clock = setInterval(() => {
    left--;
    $("#timer").textContent =
      Math.floor(left / 60) + ":" + String(left % 60).padStart(2, "0");
    if (left <= 0) submitQuiz();
  }, 1000);
}
function showQuestion() {
  const q = questions[index];
  $("#progress").style.width = ((index + 1) / questions.length) * 100 + "%";
  const answerField =
    q.question_type === "short"
      ? `<textarea id="short-answer" placeholder="Write your answer here...">${esc(answers[q.id] || "")}</textarea>`
      : `<div class="options">${["a", "b", "c", "d"].map((o) => `<label><input type="radio" name="answer" value="${o.toUpperCase()}" ${answers[q.id] === o.toUpperCase() ? "checked" : ""}> ${esc(q["option_" + o])}</label>`).join("")}</div>`;
  $("#question").innerHTML =
    `<div class="question-meta"><span>Question ${index + 1} of ${questions.length}</span><span class="question-marks">${q.marks} ${Number(q.marks) === 1 ? "mark" : "marks"} · ${q.question_type === "short" ? "Short answer" : "MCQ"}</span></div><h2>${esc(q.question_text)}</h2>${answerField}<div class="quiz-actions"><button onclick="previous()" ${index === 0 ? "disabled" : ""}>Previous</button>${index === questions.length - 1 ? '<button onclick="submitQuiz()">Submit quiz</button>' : '<button onclick="next()">Save & next</button>'}</div>`;
  document
    .querySelectorAll("[name=answer]")
    .forEach((x) => (x.onchange = () => { answers[q.id] = x.value; updateAnsweredCount(); }));
  if ($("#short-answer"))
    $("#short-answer").oninput = (e) => { answers[q.id] = e.target.value; updateAnsweredCount(); };
  updateAnsweredCount();
}
function updateAnsweredCount() {
  const answered = questions.filter((question) => String(answers[question.id] || "").trim() !== "").length;
  const counter = $("#answered-count");
  if (counter) counter.textContent = `${answered}/${questions.length}`;
}
function next() {
  if (index < questions.length - 1) {
    index++;
    showQuestion();
  }
}
function previous() {
  if (index > 0) {
    index--;
    showQuestion();
  }
}
async function submitQuiz() {
  clearInterval(clock);
  try {
    const d = await api("attempts.php?action=submit", {
      method: "POST",
      body: JSON.stringify({ quiz_id: quizId, answers }),
    });
    location = "result.html?id=" + d.attempt_id;
  } catch (e) {
    error(e);
  }
}
async function result() {
  await auth();
  const id = new URLSearchParams(location.search).get("id"),
    d = await api("attempts.php?action=result&id=" + id),
    r = d.result;
  $("#summary").innerHTML =
    r.status !== "published"
      ? `<div class="hero"><h1>Submitted</h1><p>Your short answers are waiting for teacher review. The result will appear here after publication.</p></div>`
      : `<div class="hero"><h1>${r.percentage}%</h1><p>${r.score} out of ${r.total} marks in ${esc(r.title)}</p><p>${esc(r.teacher_feedback || "")}</p><a class="button" href="instructions.html?id=${r.quiz_id}">Retake</a></div>`;
  $("#answers").innerHTML = d.answers
    .map((a, i) => {
      let key = (a.selected_option || "").toLowerCase();
      const response =
        a.question_type === "short"
          ? esc(a.text_answer || "Not answered")
          : key
            ? esc(a.selected_option + ". " + a["option_" + key])
            : "Not answered";
      const correctAnswer =
        a.question_type === "mcq"
          ? `<p class="answer-correct"><b>Correct answer:</b> ${esc(a.correct_option + ". " + a["option_" + a.correct_option.toLowerCase()])}</p>`
          : "";
      const verdict =
        Number(a.awarded_marks) === Number(a.marks)
          ? "Correct"
          : Number(a.awarded_marks) > 0
            ? "Partially correct"
            : "Incorrect";
      return `<div class="card result-answer ${Number(a.awarded_marks) > 0 ? "correct" : "incorrect"}"><div class="answer-heading"><h3>${i + 1}. ${esc(a.question_text)}</h3><span class="answer-verdict">${verdict}</span></div><p><b>Your answer:</b> ${response}</p>${correctAnswer}<p><b>Marks:</b> ${a.awarded_marks}/${a.marks}</p>${a.explanation ? `<p class="muted"><b>Explanation:</b> ${esc(a.explanation)}</p>` : ""}${a.feedback ? `<p><b>Teacher feedback:</b> ${esc(a.feedback)}</p>` : ""}</div>`;
    })
    .join("");
}
async function profile() {
  const u = await auth(),
    d = await api("attempts.php?action=dashboard");
  $("#name").value = u.name;
  $("#email").value = u.email;
  $("#phone").value = u.phone || "";
  $("#gender").value = u.gender || "";
  $("#profile-photo").src =
    u.profile_image || "assets/images/default-avatar.svg";
  $("#profile-details").innerHTML =
    `<div><span>Role</span><b>${esc(u.role)}</b></div><div><span>Roll number</span><b>${esc(u.roll_number || "Not assigned")}</b></div><div><span>Semester</span><b>${esc(u.semester || "Not assigned")}</b></div><div><span>Department</span><b>${esc(u.department || "Not assigned")}</b></div>`;
  renderHistory(d.history);
}
async function saveProfile() {
  try {
    await api("auth.php?action=profile", {
      method: "POST",
      body: JSON.stringify({
        name: $("#name").value,
        email: $("#email").value,
        phone: $("#phone").value,
        gender: $("#gender").value,
      }),
    });
    alert("Saved");
  } catch (e) {
    error(e);
  }
}
async function uploadPhoto() {
  const form = new FormData();
  form.append("photo", $("#photo").files[0]);
  const r = await fetch(API + "auth.php?action=upload_photo", {
    method: "POST",
    body: form,
  });
  const d = await r.json();
  if (!r.ok) return error(new Error(d.error));
  $("#profile-photo").src = d.path;
}
async function logout() {
  await api("auth.php?action=logout");
  location = "index.html";
}
async function adminDashboard() {
  const u = await auth();
  if (!["admin", "teacher"].includes(u.role)) location = "dashboard.html";
  $("#panel-title").textContent =
    u.role === "admin" ? "Admin dashboard" : "Teacher dashboard";
  const d = await api("admin.php?action=overview");
  $("#admin-stats").innerHTML =
    `<div class="card"><span class="eyebrow">QUIZZES</span><div class="stat">${d.quizzes.length}</div><p class="muted">Managed quizzes</p></div><div class="card"><span class="eyebrow">STUDENTS</span><div class="stat">${d.students.length}</div><p class="muted">Registered learners</p></div><div class="card"><span class="eyebrow">TO REVIEW</span><div class="stat">${d.pending.length}</div><p class="muted">Pending submissions</p></div>`;
  $("#pending").innerHTML = d.pending.length
    ? d.pending
        .map(
          (a) =>
            `<div class="card"><b>${esc(a.student)}</b> · ${esc(a.roll_number || "No roll number")}<p>${esc(a.title)} · Semester ${a.semester}</p><small class="muted">Quiz created by ${esc(a.quiz_creator || "Administrator")}</small><p><a class="button" href="grading.html?id=${a.id}">Check answers</a></p></div>`,
        )
        .join("")
    : '<p class="muted">No submissions waiting for review.</p>';
  $("#admin-quizzes").innerHTML =
    "<tr><th>Quiz</th><th>Semester</th><th>Questions</th><th>Status</th><th>Action</th></tr>" +
    d.quizzes
      .map(
        (q) =>
          `<tr><td>${esc(q.title)}<small>${esc(q.category)}</small></td><td>Semester ${q.semester}</td><td>${q.questions}</td><td><span class="status ${q.publish_status}">${esc(q.publish_status)}</span></td><td><div class="action-links"><a href="admin-create.html?id=${q.id}">Edit</a><a href="admin-questions.html?id=${q.id}">Questions</a>${q.publish_status === "draft" ? `<a href="#" onclick="publishQuiz(${q.id})">Publish</a>` : ""}<a class="danger-link" href="#" onclick="deleteQuiz(${q.id})">Delete</a></div></td></tr>`,
      )
      .join("");
  $("#students").innerHTML =
    "<tr><th>Name</th><th>Email</th><th>Attempts</th><th>Average</th></tr>" +
    d.students
      .map(
        (s) =>
          `<tr><td>${esc(s.name)}</td><td>${esc(s.email)}</td><td>${s.attempts}</td><td>${s.average}%</td></tr>`,
      )
      .join("");
  if (u.role === "admin") {
    $("#all-users").innerHTML =
      "<h2>All users</h2><table><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Actions</th></tr>" +
      d.users
        .map(
          (x) =>
            `<tr><td>${esc(x.name)}</td><td>${esc(x.email)}</td><td>${esc(x.role)}</td><td>${x.is_active == 1 ? "Active" : "Disabled"}</td><td><a href="admin-user.html?id=${x.id}">Edit</a> · <a href="#" onclick="deleteUser(${x.id})">Delete</a></td></tr>`,
        )
        .join("") +
      "</table>";
  }
}
async function loadAdminForm() {
  const u = await auth();
  if (!["admin", "teacher"].includes(u.role)) location = "dashboard.html";
  const d = await api("quizzes.php?action=categories");
  $("#category").innerHTML =
    '<option value="">Choose</option>' +
    d.categories
      .map((c) => `<option value="${c.id}">${esc(c.name)}</option>`)
      .join("");
  const id = new URLSearchParams(location.search).get("id");
  if (id) {
    const { quiz } = await api("admin.php?action=quiz&id=" + id);
    $("#quiz-form-title").textContent = "Edit semester quiz";
    $("#title").value = quiz.title;
    $("#description").value = quiz.description || "";
    $("#category").value = quiz.category_id;
    $("#semester").value = quiz.semester;
    $("#limit").value = quiz.time_limit;
  }
}
async function createQuiz() {
  try {
    const id = new URLSearchParams(location.search).get("id");
    const d = await api(
      `admin.php?action=${id ? "update_quiz" : "create_quiz"}`,
      {
        method: "POST",
        body: JSON.stringify({
          id: id || 0,
          title: $("#title").value,
          description: $("#description").value,
          category_id: $("#category").value,
          time_limit: $("#limit").value,
          semester: $("#semester").value,
        }),
      },
    );
    location = "admin-questions.html?id=" + d.quiz_id;
  } catch (e) {
    error(e);
  }
}
async function addQuestion() {
  try {
    const questionId = $("#question-id").value;
    await api(
      `admin.php?action=${questionId ? "update_question" : "add_question"}`,
      {
        method: "POST",
        body: JSON.stringify({
          id: questionId || 0,
          quiz_id: new URLSearchParams(location.search).get("id"),
          question: $("#question").value,
          a: $("#a").value,
          b: $("#b").value,
          c: $("#c").value,
          d: $("#d").value,
          correct: $("#correct").value,
          explanation: $("#explanation").value,
          question_type: $("#question-type").value,
          marks: $("#marks").value,
        }),
      },
    );
    alert(questionId ? "Question updated" : "Question added");
    document.querySelector("form").reset();
    $("#question-id").value = "";
    $("#question-form-title").textContent = "Add question";
    const mcqFields = $("#mcq-fields");
    if (mcqFields) mcqFields.hidden = false;
    loadQuestionEditor();
  } catch (e) {
    error(e);
  }
}
let editorQuestions = [];
async function loadQuestionEditor() {
  await auth();
  const id = new URLSearchParams(location.search).get("id");
  const d = await api("admin.php?action=quiz&id=" + id);
  editorQuestions = d.questions;
  $("#question-list").innerHTML = d.questions.length
    ? d.questions
        .map(
          (q, i) =>
            `<article class="question-row"><div><span class="question-number">${i + 1}</span><div><h3>${esc(q.question_text)}</h3><p>${esc(q.question_type.toUpperCase())} · ${q.marks} marks</p></div></div><div class="action-links"><button type="button" onclick="editQuestion(${q.id})">Edit</button><button type="button" class="danger-button" onclick="deleteQuestion(${q.id})">Delete</button></div></article>`,
        )
        .join("")
    : '<div class="empty-state">No questions yet. Add the first question above.</div>';
}
function editQuestion(id) {
  const q = editorQuestions.find((x) => Number(x.id) === Number(id));
  if (!q) return;
  $("#question-id").value = q.id;
  $("#question-form-title").textContent = "Edit question";
  $("#question").value = q.question_text;
  $("#question-type").value = q.question_type;
  $("#marks").value = q.marks;
  $("#a").value = q.option_a || "";
  $("#b").value = q.option_b || "";
  $("#c").value = q.option_c || "";
  $("#d").value = q.option_d || "";
  $("#correct").value = q.correct_option;
  $("#explanation").value = q.explanation || "";
  $("#mcq-fields").hidden = q.question_type === "short";
  scrollTo({ top: 0, behavior: "smooth" });
}
async function deleteQuestion(id) {
  if (!confirm("Delete this question?")) return;
  try {
    await api("admin.php?action=delete_question", {
      method: "POST",
      body: JSON.stringify({ id }),
    });
    loadQuestionEditor();
  } catch (e) {
    error(e);
  }
}
async function deleteUser(id) {
  if (confirm("Delete this user and all related records?")) {
    await api("admin.php?action=delete_user", {
      method: "POST",
      body: JSON.stringify({ id }),
    });
    adminDashboard();
  }
}
async function loadAdminUser() {
  const current = await auth();
  if (current.role !== "admin") location = "admin.html";
  const id = new URLSearchParams(location.search).get("id");
  if (!id) return;
  const { user: u } = await api("admin.php?action=user&id=" + id);
  $("#name").value = u.name;
  $("#email").value = u.email;
  $("#role").value = u.role;
  $("#roll").value = u.roll_number || "";
  $("#semester").value = u.semester || "";
  $("#department").value = u.department || "";
  $("#phone").value = u.phone || "";
  $("#gender").value = u.gender || "";
  $("#active").checked = u.is_active == 1;
}
async function saveUser() {
  try {
    const payload = {
      id: new URLSearchParams(location.search).get("id") || 0,
      name: $("#name").value,
      email: $("#email").value,
      password: $("#password").value,
      role: $("#role").value,
      roll_number: $("#roll").value,
      semester: $("#semester").value,
      department: $("#department").value,
      phone: $("#phone").value,
      gender: $("#gender").value,
      is_active: $("#active").checked,
    };
    await api("admin.php?action=save_user", {
      method: "POST",
      body: JSON.stringify(payload),
    });
    location = "admin.html";
  } catch (e) {
    error(e);
  }
}
async function grading() {
  await auth();
  const id = new URLSearchParams(location.search).get("id"),
    d = await api("admin.php?action=grading&id=" + id);
  $("#grading-title").textContent = `${d.attempt.student} — ${d.attempt.title}`;
  $("#grading-list").innerHTML = d.answers
    .map(
      (a, i) => {
        let studentResponse = a.text_answer;
        if (a.question_type === "mcq") {
          const optKey = (a.selected_option || "").toLowerCase();
          const optText = a["option_" + optKey] ? ` (${a["option_" + optKey]})` : "";
          studentResponse = a.selected_option
            ? `${a.selected_option}${optText}`
            : "Not answered";
        } else if (!studentResponse) {
          studentResponse = "Not answered";
        }

        const correctAnswer = a.question_type === "mcq"
          ? `<p class="muted"><b>Correct option:</b> ${esc(a.correct_option + ". " + (a["option_" + a.correct_option.toLowerCase()] || ""))}</p>`
          : "";

        const rubric = a.explanation
          ? `<div class="muted" style="margin: 8px 0;"><small><b>Model answer / Rubric:</b> ${esc(a.explanation)}</small></div>`
          : "";

        return `<div class="card grade-row" data-id="${a.id}">
          <h3>${i + 1}. ${esc(a.question_text)}</h3>
          <p><b>Type:</b> ${a.question_type.toUpperCase()} · <b>Max marks:</b> ${a.marks}</p>
          <p><b>Student answer:</b> ${esc(studentResponse)}</p>
          ${correctAnswer}
          ${rubric}
          <label>Marks awarded (0 to ${a.marks})</label>
          <input class="grade-marks" type="number" min="0" max="${a.marks}" step="0.5" value="${a.awarded_marks}">
          <label>Feedback for student</label>
          <input class="grade-feedback" value="${esc(a.feedback || "")}" placeholder="Optional feedback...">
        </div>`;
      }
    )
    .join("");
}
async function publishGrade() {
  const answers = [...document.querySelectorAll(".grade-row")].map((x) => ({
    id: x.dataset.id,
    marks: x.querySelector(".grade-marks").value,
    feedback: x.querySelector(".grade-feedback").value,
  }));
  await api("admin.php?action=publish_grade", {
    method: "POST",
    body: JSON.stringify({
      attempt_id: new URLSearchParams(location.search).get("id"),
      feedback: $("#overall-feedback").value,
      answers,
    }),
  });
  location = "admin.html";
}
let adminCategories = [];
async function loadCategories() {
  const u = await auth();
  if (u.role !== "admin") location = "admin.html";
  const d = await api("admin.php?action=categories");
  adminCategories = d.categories;
  $("#category-list").innerHTML = d.categories
    .map(
      (c) =>
        `<article class="category-card"><div class="category-icon">${esc(c.name.charAt(0).toUpperCase())}</div><div><h3>${esc(c.name)}</h3><p>${c.quiz_count} quizzes</p></div><div class="action-links"><button onclick="editCategory(${c.id})">Edit</button><button class="danger-button" onclick="deleteCategory(${c.id})">Delete</button></div></article>`,
    )
    .join("");
}
function editCategory(id) {
  const c = adminCategories.find((x) => Number(x.id) === Number(id));
  if (!c) return;
  $("#category-id").value = c.id;
  $("#category-name").value = c.name;
  $("#category-submit").textContent = "Update category";
  scrollTo({ top: 0, behavior: "smooth" });
}
function resetCategoryForm() {
  $("#category-id").value = "";
  $("#category-name").value = "";
  $("#category-submit").textContent = "Add category";
}
async function saveCategory() {
  try {
    await api("admin.php?action=save_category", {
      method: "POST",
      body: JSON.stringify({
        id: $("#category-id").value || 0,
        name: $("#category-name").value,
      }),
    });
    resetCategoryForm();
    loadCategories();
  } catch (e) {
    error(e);
  }
}
async function deleteCategory(id) {
  if (!confirm("Delete this category?")) return;
  try {
    await api("admin.php?action=delete_category", {
      method: "POST",
      body: JSON.stringify({ id }),
    });
    loadCategories();
  } catch (e) {
    error(e);
  }
}
async function deleteQuiz(id) {
  if (confirm("Delete this quiz and its results?")) {
    await api("admin.php?action=delete_quiz", {
      method: "POST",
      body: JSON.stringify({ id }),
    });
    adminDashboard();
  }
}
async function publishQuiz(id) {
  if (!confirm("Publish this quiz to students of its selected semester?"))
    return;
  try {
    await api("admin.php?action=publish_quiz", {
      method: "POST",
      body: JSON.stringify({ id }),
    });
    adminDashboard();
  } catch (e) {
    error(e);
  }
}
async function generateAI() {
  try {
    const d = await api("ai.php", {
      method: "POST",
      body: JSON.stringify({
        topic: $("#topic").value,
        category_id: $("#category").value,
        semester: $("#semester").value,
        time_limit: $("#limit").value,
        mcq_count: $("#mcq-count").value,
        short_count: $("#short-count").value,
        mcq_marks: $("#mcq-marks").value,
        short_marks: $("#short-marks").value,
      }),
    });
    location = "admin-questions.html?id=" + d.quiz_id;
  } catch (e) {
    error(e);
  }
}
async function loadAISettings() {
  try {
    await auth();
    const data = await api("settings.php?action=status");
    $("#model").value = data.model;
    $("#key-status").innerHTML = data.configured
      ? `<span class="status-dot ready"></span><div><b>API key is connected</b><small>${esc(data.masked_key)} · Ready for AI quiz generation</small></div>`
      : '<span class="status-dot"></span><div><b>No API key connected</b><small>Add a key below to enable online AI quiz generation.</small></div>';
    $("#remove-key").hidden = !data.configured;
  } catch (e) {
    error(e);
  }
}
async function saveAISettings() {
  try {
    const data = await api("settings.php?action=save", {
      method: "POST",
      body: JSON.stringify({
        api_key: $("#api-key").value.trim(),
        model: $("#model").value.trim(),
      }),
    });
    $("#message").innerHTML = `<div class="success-message">${esc(data.message)}</div>`;
    $("#api-key").value = "";
    await loadAISettings();
  } catch (e) {
    error(e);
  }
}
async function removeAIKey() {
  if (!confirm("Remove the saved OpenAI API key? AI generation will stop.")) return;
  try {
    const data = await api("settings.php?action=delete", { method: "POST" });
    $("#message").innerHTML = `<div class="success-message">${esc(data.message)}</div>`;
    await loadAISettings();
  } catch (e) {
    error(e);
  }
}
async function setupAdmin() {
  try {
    await api("setup.php", {
      method: "POST",
      body: JSON.stringify({
        name: $("#name").value,
        email: $("#email").value,
        password: $("#password").value,
      }),
    });
    location = "login.html";
  } catch (e) {
    error(e);
  }
}
