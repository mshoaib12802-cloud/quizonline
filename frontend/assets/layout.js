document.body.classList.add("app-page");
if (localStorage.getItem("quizonline-theme") === "dark") {
  document.body.classList.add("dark-theme");
}
function toggleTheme() {
  const dark = document.body.classList.toggle("dark-theme");
  localStorage.setItem("quizonline-theme", dark ? "dark" : "light");
  updateThemeButton();
}
function updateThemeButton() {
  const button = document.querySelector("#theme-toggle");
  if (!button) return;
  const dark = document.body.classList.contains("dark-theme");
  button.innerHTML = dark ? '<span>☀</span><span class="theme-label">Light</span>' : '<span>☾</span><span class="theme-label">Dark</span>';
  button.setAttribute("aria-label", dark ? "Switch to light mode" : "Switch to dark mode");
}
document.head.insertAdjacentHTML(
  "beforeend",
  '<link rel="stylesheet" href="assets/app-shell.css" /><link rel="stylesheet" href="assets/responsive.css" />',
);

document.write(`
  <aside class="app-sidebar" id="app-sidebar">
    <div class="sidebar-brand">
      <a class="brand" href="dashboard.html"><span class="brand-mark">Q</span><span>QuizOnline</span></a>
      <span class="sidebar-role" id="sidebar-role">Portal</span>
    </div>

    <nav class="sidebar-nav">
      <span class="nav-label">Overview</span>
      <a href="dashboard.html" data-page="dashboard"><span class="nav-icon">⌂</span><span>Home</span></a>
      <a href="quizzes.html" data-page="quizzes"><span class="nav-icon">◇</span><span>My quizzes</span></a>
      <a href="profile.html" data-page="profile"><span class="nav-icon">◎</span><span>Profile & results</span></a>

      <span class="nav-label role-management" hidden>Quiz management</span>
      <a class="role-management" href="admin.html" data-page="admin" hidden><span class="nav-icon">▦</span><span>Management</span></a>
      <a class="role-management" href="admin-create.html" data-page="admin-create" hidden><span class="nav-icon">＋</span><span>Create quiz</span></a>
      <a class="role-management" href="admin-ai.html" data-page="admin-ai" hidden><span class="nav-icon">✦</span><span>AI quiz generator</span></a>

      <span class="nav-label role-admin" hidden>Administration</span>
      <a class="role-admin" href="admin-user.html" data-page="admin-user" hidden><span class="nav-icon">♙</span><span>Add user</span></a>
      <a class="role-admin" href="admin-categories.html" data-page="admin-categories" hidden><span class="nav-icon">▤</span><span>Categories</span></a>
      <a class="role-admin" href="admin-ai-settings.html" data-page="admin-ai-settings" hidden><span class="nav-icon">⚿</span><span>AI settings</span></a>
    </nav>

    <div class="sidebar-footer">
      <a href="index.html"><span class="nav-icon">←</span><span>View website</span></a>
      <a href="#" onclick="logout()"><span class="nav-icon">↪</span><span>Logout</span></a>
    </div>
  </aside>

  <header class="app-topbar">
    <button class="menu-toggle" type="button" onclick="toggleSidebar()" aria-label="Toggle menu">☰</button>
    <div class="topbar-identity"><span class="topbar-kicker">ISP ACADEMIC QUIZ PORTAL</span><b id="topbar-user">Loading...</b></div>
    <div class="topbar-actions">
      <button class="theme-toggle" id="theme-toggle" type="button" onclick="toggleTheme()" aria-label="Switch colour mode"></button>
      <a class="topbar-avatar" href="profile.html" id="topbar-avatar">U</a>
    </div>
  </header>
  <div class="sidebar-overlay" onclick="toggleSidebar()"></div>
`);
updateThemeButton();
