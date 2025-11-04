<?php
declare(strict_types=1);

/*
  scsn_v1.php
  SCSN – Senior Citizens Social Network (Civic / Support Use)

  PHP + PDO + SQLite/MariaDB demo.
  Includes a flip-phone style 1–2–3 keypad:
    1 = Post Help
    2 = Post 911 Alert (flag only)
    3 = Post Emergency and Address (flag only)

  IMPORTANT: This is an educational prototype only.
  It does NOT contact 911, law enforcement, or social services.
*/

/* -----------------------------------------------------------
   1. CONFIGURATION
   ----------------------------------------------------------- */

// Choose "sqlite" or "mariadb"
const SCSN_DB_DRIVER = 'sqlite'; // change to 'mariadb' if desired

// SQLite configuration
const SCSN_SQLITE_PATH = __DIR__ . '/scsn.db';

// MariaDB configuration
const SCSN_MARIADB_DSN  = 'mysql:host=localhost;dbname=scsn;charset=utf8mb4';
const SCSN_MARIADB_USER = 'scsn_user';
const SCSN_MARIADB_PASS = 'change_me';

/* -----------------------------------------------------------
   2. DB CONNECTION + SCHEMA
   ----------------------------------------------------------- */

function scsn_get_pdo(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    if (SCSN_DB_DRIVER === 'sqlite') {
        $dsn = 'sqlite:' . SCSN_SQLITE_PATH;
        $pdo = new PDO($dsn);
        $pdo->exec('PRAGMA foreign_keys = ON');
    } elseif (SCSN_DB_DRIVER === 'mariadb') {
        $pdo = new PDO(
            SCSN_MARIADB_DSN,
            SCSN_MARIADB_USER,
            SCSN_MARIADB_PASS
        );
    } else {
        throw new RuntimeException('Unsupported DB driver: ' . SCSN_DB_DRIVER);
    }

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    scsn_bootstrap_schema($pdo);

    return $pdo;
}

/**
 * Create tables if they do not exist and seed a demo senior user.
 */
function scsn_bootstrap_schema(PDO $pdo): void
{
    if (SCSN_DB_DRIVER === 'sqlite') {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  handle       TEXT    NOT NULL UNIQUE,
  display_name TEXT    NOT NULL,
  bio          TEXT,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
SQL
        );

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS posts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id      INTEGER NOT NULL,
  body         TEXT    NOT NULL,
  kind         TEXT    NOT NULL DEFAULT 'normal', -- normal|help|911|emergency_address
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_highlight INTEGER NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id)
);
SQL
        );
    } else {
        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS users (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  handle       VARCHAR(32)  NOT NULL UNIQUE,
  display_name VARCHAR(80)  NOT NULL,
  bio          TEXT,
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
SQL
        );

        $pdo->exec(<<<SQL
CREATE TABLE IF NOT EXISTS posts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  body         VARCHAR(280) NOT NULL,
  kind         VARCHAR(32)  NOT NULL DEFAULT 'normal',
  created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  is_highlight TINYINT(1)   NOT NULL DEFAULT 0,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;
SQL
        );
    }

    // Seed default user + posts if empty
    $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $stmt = $pdo->prepare(
            'INSERT INTO users (handle, display_name, bio)
             VALUES (:handle, :display_name, :bio)'
        );
        $stmt->execute([
            ':handle'       => '@senior_demo',
            ':display_name' => 'Senior Demo User',
            ':bio'          => 'Practicing safe communication and emergency planning with SCSN.'
        ]);

        $userId = (int)$pdo->lastInsertId();

        $seedPosts = [
            [
                'body' => 'Welcome to the Senior Citizens Social Network. This feed is for civic and support use.',
                'kind' => 'normal',
                'hi'   => 1,
            ],
            [
                'body' => 'Tip: Keep a written list of emergency contacts near the phone in case the device fails.',
                'kind' => 'normal',
                'hi'   => 0,
            ],
            [
                'body' => 'Quick buttons 1–3 are training tools. Always call 911 directly in a real emergency.',
                'kind' => 'normal',
                'hi'   => 0,
            ],
        ];

        $stmtPost = $pdo->prepare(
            'INSERT INTO posts (user_id, body, kind, is_highlight)
             VALUES (:uid, :body, :kind, :hi)'
        );
        foreach ($seedPosts as $post) {
            $stmtPost->execute([
                ':uid'  => $userId,
                ':body' => $post['body'],
                ':kind' => $post['kind'],
                ':hi'   => $post['hi'],
            ]);
        }
    }
}

/* -----------------------------------------------------------
   3. JSON APIS
   ----------------------------------------------------------- */

function scsn_api_list_posts(): void
{
    $pdo = scsn_get_pdo();

    $stmt = $pdo->query(
        'SELECT p.id,
                p.body,
                p.kind,
                p.created_at,
                p.is_highlight,
                u.handle,
                u.display_name
         FROM posts p
         JOIN users u ON p.user_id = u.id
         ORDER BY p.created_at ASC
         LIMIT 100'
    );

    $posts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($posts);
    exit;
}

/**
 * Create a post.
 * JSON body: { "body": "...", "kind": "normal|help|911|emergency_address" }
 */
function scsn_api_create_post(): void
{
    $pdo = scsn_get_pdo();

    $raw  = file_get_contents('php://input');
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }

    $body = trim($data['body'] ?? '');
    $kind = trim($data['kind'] ?? 'normal');

    if ($body === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Body is required']);
        exit;
    }

    $allowedKinds = ['normal', 'help', '911', 'emergency_address'];
    if (!in_array($kind, $allowedKinds, true)) {
        $kind = 'normal';
    }

    // Use the first (seed) user for this educational demo
    $stmtUser = $pdo->query(
        'SELECT id, handle, display_name FROM users ORDER BY id ASC LIMIT 1'
    );
    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'No user found']);
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO posts (user_id, body, kind, is_highlight)
         VALUES (:uid, :body, :kind, 0)'
    );
    $stmt->execute([
        ':uid'  => $user['id'],
        ':body' => $body,
        ':kind' => $kind,
    ]);

    $id        = $pdo->lastInsertId();
    $createdAt = date('Y-m-d H:i:s');

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'id'           => (int)$id,
        'body'         => $body,
        'kind'         => $kind,
        'created_at'   => $createdAt,
        'is_highlight' => 0,
        'handle'       => $user['handle'],
        'display_name' => $user['display_name'],
    ]);
    exit;
}

/* -----------------------------------------------------------
   4. API ROUTING
   ----------------------------------------------------------- */

if (isset($_GET['api'])) {
    $api = $_GET['api'];
    if ($api === 'posts-list') {
        scsn_api_list_posts();
    } elseif ($api === 'posts-create') {
        scsn_api_create_post();
    } else {
        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Unknown API endpoint']);
    }
    exit;
}

/* -----------------------------------------------------------
   5. HTML + FRONT END
   ----------------------------------------------------------- */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>SCSN – Senior Citizens Social Network (Civic Use Demo)</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root {
      --sky: #66b9ff;
      --sky-light: #e8f4ff;
      --sky-dark: #2b7cbc;
      --bg: #f5f7fb;
      --text-main: #133143;
      --text-muted: #4f6b7f;
      --radius-xl: 26px;
      --radius-lg: 18px;
      --radius-md: 12px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: radial-gradient(circle at top left, #ffffff 0, #eef2f7 40%, #dee6f2 100%);
      color: var(--text-main);
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: stretch;
    }
    header.app-header {
      background: var(--sky);
      color: #ffffff;
      padding: 14px 24px;
      border-radius: 0 0 var(--radius-xl) var(--radius-xl);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
      text-align: center;
      font-size: 1.4rem;
      font-weight: 600;
      letter-spacing: 0.03em;
    }
    header.app-header small {
      display: block;
      font-size: 0.75rem;
      font-weight: 400;
      margin-top: 4px;
      opacity: 0.9;
    }
    .app-shell {
      flex: 1;
      display: flex;
      gap: 16px;
      padding: 16px;
      max-width: 1200px;
      width: 100%;
      margin: 0 auto 16px auto;
    }
    aside.sidebar {
      width: 230px;
      min-width: 210px;
      max-width: 260px;
      background: var(--sky);
      border-radius: var(--radius-xl);
      padding: 16px 10px;
      display: flex;
      flex-direction: column;
      gap: 12px;
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    .sidebar-panel {
      background: #ffffff;
      color: var(--text-main);
      border-radius: var(--radius-xl);
      padding: 14px 12px;
      flex: 0 0 auto;
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .sidebar-panel h2 {
      font-size: 0.95rem;
      margin-bottom: 6px;
      text-align: center;
    }
    .user-summary { font-size: 0.85rem; line-height: 1.4; }
    .user-summary .name { font-weight: 600; font-size: 1rem; }
    .user-summary .handle { color: var(--text-muted); font-size: 0.85rem; }
    .user-stats {
      display: flex;
      justify-content: space-between;
      font-size: 0.76rem;
      margin-top: 4px;
    }
    .other-users-list {
      list-style: none;
      font-size: 0.85rem;
      max-height: 220px;
      overflow-y: auto;
      padding-right: 4px;
    }
    .other-users-list li {
      padding: 6px 6px;
      border-radius: var(--radius-md);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    main.main-feed {
      flex: 1;
      background: #ffffff;
      border-radius: var(--radius-xl);
      padding: 16px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.12);
      display: flex;
      flex-direction: column;
      gap: 14px;
      position: relative;
    }
    .feed-inner-frame {
      border-radius: var(--radius-xl);
      padding: 16px;
      background: var(--bg);
      height: 100%;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }
    .highlighted {
      background: var(--sky);
      color: #ffffff;
      border-radius: var(--radius-xl);
      padding: 14px 18px;
      font-weight: 500;
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.45);
    }
    .highlighted-title {
      font-size: 0.9rem;
      letter-spacing: 0.03em;
      text-transform: uppercase;
      margin-bottom: 6px;
      opacity: 0.92;
    }
    .highlighted-body { font-size: 0.95rem; }
    .composer {
      background: #ffffff;
      border-radius: var(--radius-lg);
      padding: 10px 12px;
      display: flex;
      flex-direction: column;
      gap: 8px;
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
    }
    .composer label {
      font-size: 0.82rem;
      color: var(--text-muted);
    }
    .composer textarea {
      width: 100%;
      min-height: 60px;
      resize: vertical;
      border-radius: var(--radius-md);
      border: 1px solid #c3d3e6;
      padding: 6px 8px;
      font-family: inherit;
      font-size: 0.9rem;
    }
    .composer-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    .composer-row small {
      font-size: 0.78rem;
      color: var(--text-muted);
    }
    .composer button {
      padding: 6px 14px;
      border-radius: 999px;
      border: none;
      background: var(--sky);
      color: #ffffff;
      font-size: 0.88rem;
      cursor: pointer;
      font-weight: 500;
      transition: background 0.15s ease, transform 0.05s ease;
    }
    .composer button:hover {
      background: var(--sky-dark);
      transform: translateY(-1px);
    }
    .phone-panel {
      margin-top: 8px;
      background: #ffffff;
      border-radius: var(--radius-lg);
      padding: 10px 12px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .phone-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 6px;
      margin-top: 4px;
    }
    .phone-key {
      height: 40px;
      border-radius: 10px;
      border: none;
      background: var(--sky);
      color: #ffffff;
      font-size: 1.1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.15s ease, transform 0.05s ease;
    }
    .phone-key:hover {
      background: var(--sky-dark);
      transform: translateY(-1px);
    }
    .phone-key.small {
      font-size: 0.9rem;
      font-weight: 500;
    }
    .feed-heading {
      text-align: center;
      font-size: 0.92rem;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: var(--text-muted);
      margin-top: 6px;
    }
    .feed-scroll {
      margin-top: 4px;
      flex: 1;
      overflow-y: auto;
      padding-right: 6px;
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .post {
      position: relative;
      background: var(--sky);
      border-radius: var(--radius-lg);
      padding: 10px 12px 10px 14px;
      max-width: 88%;
      color: #ffffff;
      box-shadow: 0 3px 8px rgba(0, 0, 0, 0.16);
      font-size: 0.9rem;
    }
    .post::after {
      content: "";
      position: absolute;
      right: -16px;
      top: 18px;
      border-width: 8px 0 8px 16px;
      border-style: solid;
      border-color: transparent transparent transparent var(--sky);
    }
    .post.emergency {
      background: #ff7b7b;
    }
    .post.emergency::after {
      border-color: transparent transparent transparent #ff7b7b;
    }
    .post-meta {
      font-size: 0.75rem;
      opacity: 0.92;
      margin-bottom: 4px;
      display: flex;
      justify-content: space-between;
      gap: 8px;
      flex-wrap: wrap;
    }
    .post .handle { font-weight: 600; }
    .post-body { line-height: 1.35; }
    .scroll-bar-hint {
      width: 18px;
      border-radius: var(--radius-xl);
      background: var(--sky);
      margin-left: 4px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 12px 0;
      color: #ffffff;
      font-size: 0.7rem;
      gap: 6px;
      box-shadow: 0 3px 10px rgba(0, 0, 0, 0.18);
    }
    .scroll-arrow {
      width: 0;
      height: 0;
      border-left: 5px solid transparent;
      border-right: 5px solid transparent;
    }
    .scroll-arrow.up { border-bottom: 8px solid #ffffff; }
    .scroll-arrow.down { border-top: 8px solid #ffffff; }
    footer {
      max-width: 1200px;
      margin: 0 auto 18px auto;
      padding: 0 16px;
      font-size: 0.8rem;
      color: var(--text-muted);
    }
    footer h2 { font-size: 0.9rem; margin-bottom: 4px; }
    footer ol { padding-left: 18px; line-height: 1.4; }
    footer li { margin-bottom: 2px; }
    @media (max-width: 900px) {
      .app-shell { flex-direction: column; }
      aside.sidebar {
        width: 100%;
        max-width: none;
        flex-direction: row;
        align-items: stretch;
        justify-content: space-between;
      }
      .sidebar-panel { flex: 1; }
    }
    @media (max-width: 640px) {
      header.app-header { border-radius: 0; }
      .app-shell { padding: 10px; }
      aside.sidebar { flex-direction: column; }
    }
  </style>
</head>

<body>
  <header class="app-header">
    Application Name: Senior Citizens Social Network
    <small>Not a dating application – civic and support use only – educational prototype</small>
  </header>

  <div class="app-shell">
    <aside class="sidebar" aria-label="Sidebar">
      <section class="sidebar-panel" aria-label="User information">
        <h2>User Info</h2>
        <div class="user-summary">
          <div class="name" id="currentUserName">Senior Demo User</div>
          <div class="handle" id="currentUserHandle">@senior_demo</div>
          <p id="currentUserBio">
            Practicing simple communication for older adults. This demo does not contact emergency services.
          </p>
          <div class="user-stats">
            <span><strong id="statPosts">0</strong> posts</span>
            <span><strong>Family</strong> &amp; friends</span>
            <span><strong>Care</strong> network</span>
          </div>
        </div>
      </section>

      <section class="sidebar-panel" aria-label="Other users">
        <h2>Other Users</h2>
        <ul class="other-users-list">
          <li><span>Ada</span><span class="handle">@neighbor_ada</span></li>
          <li><span>Sam</span><span class="handle">@son_sam</span></li>
          <li><span>Lee</span><span class="handle">@caregiver_lee</span></li>
          <li><span>Ravi</span><span class="handle">@caseworker_ravi</span></li>
          <li><span>Amira</span><span class="handle">@nurse_amira</span></li>
          <li><span>Jen</span><span class="handle">@friend_jen</span></li>
        </ul>
      </section>

      <section class="sidebar-panel" aria-label="Menu">
        <h2>Menu</h2>
        <div class="menu-buttons">
          <button type="button" id="btnLogin">Login</button>
          <button type="button" id="btnLogout" disabled>Logout</button>
          <button type="button" id="btnSettings">Settings</button>
        </div>
        <small style="margin-top:6px; display:block; color:var(--text-muted);">
          Posts are stored in a local SQLite or MariaDB database through PDO (PHP Group, 2024;
          SQLite Consortium, 2025; MariaDB Foundation, 2025).
        </small>
      </section>
    </aside>

    <main class="main-feed" aria-label="Main content">
      <div class="feed-inner-frame">
        <section class="highlighted" aria-label="Highlighted content">
          <div class="highlighted-title">Highlighted Content</div>
          <div class="highlighted-body" id="highlightedBody">
            Welcome to SCSN. This feed is for civic and safety-support conversations.
            For real emergencies, always call 911 or your local emergency number.
          </div>
        </section>

        <section class="composer" aria-label="Create a new post">
          <label for="newPostText">
            Share a check-in, question, or note for your support network:
          </label>
          <textarea id="newPostText" maxlength="280"
            placeholder="Example: I have a doctor’s appointment Tuesday at 2 PM, please remind me."></textarea>
          <div class="composer-row">
            <small id="charCount">0 / 280 characters</small>
            <button type="button" id="btnPost">Post to feed</button>
          </div>
        </section>

        <section class="phone-panel" aria-label="Flip phone quick actions">
          <strong>Flip-Phone Quick Actions (training only)</strong>
          <small>
            Buttons 1–3 simulate a simple keypad for older adults.
            They create clearly labeled posts but do not contact 911.
          </small>
          <div class="phone-grid">
            <button class="phone-key" data-kind="help" id="key1">1</button>
            <button class="phone-key" data-kind="911" id="key2">2</button>
            <button class="phone-key" data-kind="emergency_address" id="key3">3</button>
            <button class="phone-key small" disabled>4</button>
            <button class="phone-key small" disabled>5</button>
            <button class="phone-key small" disabled>6</button>
            <button class="phone-key small" disabled>7</button>
            <button class="phone-key small" disabled>8</button>
            <button class="phone-key small" disabled>9</button>
          </div>
          <small>
            1 = Post “Help” · 2 = Flag a “911 Alert” · 3 = Post “Emergency and Address” (training messages only).
          </small>
        </section>

        <div class="feed-heading">Content Feed</div>
        <section class="feed-scroll" id="feedScroll" aria-label="Content feed"></section>
      </div>
    </main>

    <div class="scroll-bar-hint" aria-hidden="true">
      <div class="scroll-arrow up"></div>
      <div>scroll</div>
      <div class="scroll-arrow down"></div>
    </div>
  </div>

  <footer>
    <h2>References (APA, for educational use)</h2>
    <ol>
      <li>PHP Group. (2024). <em>PHP manual: PDO</em>. https://www.php.net/manual/en/book.pdo.php</li>
      <li>SQLite Consortium. (2025). <em>SQLite documentation</em>. https://sqlite.org/docs.html</li>
      <li>MariaDB Foundation. (2025). <em>MariaDB server documentation</em>. https://mariadb.org</li>
      <li>Ready.gov. (2025). <em>Older adults: Disaster preparedness guide</em>. U.S. Department of Homeland
        Security. https://www.ready.gov/older-adults</li>
      <li>American Red Cross. (2025). <em>Emergency preparedness for older adults</em>.
        https://www.redcross.org/get-help/how-to-prepare-for-emergencies/older-adults.html</li>
      <li>OpenAI. (2025). <em>SCSN – Senior Citizens Social Network design conversation</em>
        (ChatGPT conversation with Scott Owen, November 3, 2025).</li>
    </ol>
  </footer>

  <script>
    const textarea = document.getElementById("newPostText");
    const charCount = document.getElementById("charCount");
    const btnPost = document.getElementById("btnPost");
    const btnLogin = document.getElementById("btnLogin");
    const btnLogout = document.getElementById("btnLogout");
    const btnSettings = document.getElementById("btnSettings");
    const feedScroll = document.getElementById("feedScroll");
    const statPosts = document.getElementById("statPosts");
    const highlightedBody = document.getElementById("highlightedBody");

    let isLoggedIn = false;

    function updateCharCount() {
      const len = textarea.value.length;
      charCount.textContent = len + " / 280 characters";
    }

    function renderFeed(posts) {
      feedScroll.innerHTML = "";
      let highlightText = null;

      posts.forEach((post, index) => {
        const isEmergency =
          post.kind === "help" ||
          post.kind === "911" ||
          post.kind === "emergency_address";

        const card = document.createElement("article");
        card.className = "post" + (isEmergency ? " emergency" : "");
        if (index % 2 === 1) card.className += " alt";

        const meta = document.createElement("div");
        meta.className = "post-meta";

        const left = document.createElement("span");
        let label = "";
        if (post.kind === "help") {
          label = "[HELP] ";
        } else if (post.kind === "911") {
          label = "[911 ALERT – TRAINING ONLY] ";
        } else if (post.kind === "emergency_address") {
          label = "[EMERGENCY & ADDRESS – TRAINING ONLY] ";
        }

        left.innerHTML =
          '<span class="handle">' + post.handle +
          "</span>&nbsp;&middot;&nbsp;" + post.display_name +
          (label ? "&nbsp;&nbsp;<strong>" + label + "</strong>" : "");

        const right = document.createElement("span");
        right.textContent = new Date(post.created_at).toLocaleString();

        meta.appendChild(left);
        meta.appendChild(right);

        const body = document.createElement("div");
        body.className = "post-body";
        body.textContent = post.body;

        card.appendChild(meta);
        card.appendChild(body);
        feedScroll.appendChild(card);

        if (String(post.is_highlight) === "1" && highlightText === null) {
          highlightText = post.body;
        }
      });

      if (highlightText !== null) {
        highlightedBody.textContent = highlightText;
      }

      statPosts.textContent = String(posts.length);
      feedScroll.scrollTop = feedScroll.scrollHeight;
    }

    async function loadPosts() {
      try {
        const res = await fetch("?api=posts-list");
        if (!res.ok) {
          console.error("Failed to load posts");
          return;
        }
        const posts = await res.json();
        renderFeed(posts);
      } catch (err) {
        console.error("Error loading posts", err);
      }
    }

    async function createPost(body, kind = "normal") {
      if (!isLoggedIn) {
        alert("Use the Login button to enable posting in this demonstration.");
        return;
      }
      const trimmed = body.trim();
      if (trimmed === "") {
        alert("Please write a short message before posting.");
        return;
      }
      try {
        const res = await fetch("?api=posts-create", {
          method: "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ body: trimmed, kind })
        });
        if (!res.ok) {
          alert("Error creating post.");
          return;
        }
        textarea.value = "";
        updateCharCount();
        await loadPosts();
      } catch (err) {
        console.error("Error creating post", err);
      }
    }

    function toggleLogin(state) {
      isLoggedIn = state;
      btnLogin.disabled = isLoggedIn;
      btnLogout.disabled = !isLoggedIn;
      textarea.disabled = !isLoggedIn;
      btnPost.disabled = !isLoggedIn;

      const bio = document.getElementById("currentUserBio");
      if (isLoggedIn) {
        bio.textContent =
          "Logged in locally. Posts (including keypad messages) are written to the local SCSN database for practice.";
      } else {
        bio.textContent =
          "Viewing in guest mode. Log in to practice posting check-ins and keypad messages. This does not contact 911.";
      }
    }

    textarea.addEventListener("input", updateCharCount);
    btnPost.addEventListener("click", () => createPost(textarea.value, "normal"));

    btnLogin.addEventListener("click", () => {
      toggleLogin(true);
      alert("This is a simulated login for classroom or workshop use. No real accounts are used.");
    });

    btnLogout.addEventListener("click", () => {
      toggleLogin(false);
      alert("You are now logged out of this demonstration.");
    });

    btnSettings.addEventListener("click", () => {
      alert(
        "Settings could control larger text, higher contrast, or contact lists.\n" +
        "In this prototype, the button shows this explanation only."
      );
    });

    document.getElementById("key1").addEventListener("click", () => {
      createPost("I need help. Please check on me or call me when you can.", "help");
    });

    document.getElementById("key2").addEventListener("click", () => {
      createPost(
        "Training alert: This simulates a 911-type emergency message to the support network. " +
        "In real life, call 911 directly.",
        "911"
      );
    });

    document.getElementById("key3").addEventListener("click", () => {
      createPost(
        "Emergency and address (training message): I need immediate help at my home address. " +
        "Contact emergency services and my support network.",
        "emergency_address"
      );
    });

    toggleLogin(false);
    updateCharCount();
    loadPosts();
  </script>
</body>
</html>
