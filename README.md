# QuizOnline

A comprehensive academic quiz and assessment web application built with PHP, MySQL, HTML5, CSS3, and JavaScript.

## Quick Start (Run from ANY Folder)

You do **not** have to move the folder into `htdocs`!

### On Windows:
1. Make sure **MySQL** is started in XAMPP (Control Panel -> MySQL -> Start).
2. Double-click **`start.bat`** in this folder.
   * It will automatically start the PHP server and open the login page in your default browser at `http://localhost:8000/frontend/login.html`.

### On macOS / Linux:
1. Make sure MySQL is running.
2. In terminal, run:
   ```bash
   bash start.sh
   ```
   *(Or run `php -S localhost:8000` and open `http://localhost:8000/frontend/login.html`)*

### Important Note about VS Code Live Server:
Do **NOT** use VS Code "Live Server" (e.g. `http://127.0.0.1:5500`). Live Server only serves static files and does not execute PHP scripts, which causes a **`405 Method Not Allowed`** error on login. Always use `start.bat`, `start.sh`, or XAMPP Apache.

---

## Alternative: Traditional XAMPP Setup (htdocs)

1. Copy this folder to:
   ```text
   C:\xampp\htdocs\quizonline
   ```
2. Start **Apache** and **MySQL** in XAMPP Control Panel.
3. Open in your browser:
   👉 **`http://localhost/quizonline/frontend/login.html`**

*(Note: The database and demo accounts are automatically initialized by the backend on first launch, or you can import `database.sql` in phpMyAdmin).*

---

## Demo Logins

| Role | Email | Password | Features |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@gmail.com` | `admin` | User management, categories, AI settings, overview |
| **Teacher** | `teacher@gmail.com` | `teacher@` (or `teacher`) | Create quizzes, check student submissions, publish results |
| **Student** | `student@gmail.com` | `student@` (or `student`) | Take semester quizzes, live timer, view score and feedback |

---

## Optional: AI Quiz Generation

1. Log in as **Admin** (`admin@gmail.com` / `admin`).
2. Go to **AI settings** in the sidebar.
3. Enter your OpenAI API key and choose the model (`gpt-4o-mini` or `gpt-4o`).
4. Teachers can now open **AI quiz generator**, input any topic, choose question counts, and generate draft quizzes instantly.
