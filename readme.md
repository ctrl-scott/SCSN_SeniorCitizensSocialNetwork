

---

````markdown
# 🕊️ SCSN — Senior Citizens Social Network  
**(Civic & Support Communication Platform — Educational Use Only)**

![Concept Wireframe](Screenshot%202025-11-03%20at%205.54.11%20PM.png)

---

## 📘 Overview
The **Senior Citizens Social Network (SCSN)** is a front-end and PHP-based educational prototype illustrating how older adults might safely communicate with trusted contacts and social-service networks using both modern and simplified “flip-phone style” interfaces.

This application **does not connect to emergency services** and is intended **solely for classroom or civic-technology demonstrations**.

---

## 🧱 Features
| Category | Description |
|-----------|--------------|
| **Web Interface** | Modeled after the *Bitter* educational layout, refactored for civic-use posting, highlighted messages, and safety checks. |
| **Flip-Phone Keypad Simulation** | Keys 1–3 post pre-written training messages: “Help,” “911 Alert,” and “Emergency & Address.” |
| **Local Database Support** | Works with **SQLite** (default) or **MariaDB/MySQL** via PHP PDO. |
| **No External Server Calls** | Self-contained; safe for offline demonstration. |
| **Semantic HTML5 & Accessible Design** | Structured for readability and screen-reader compatibility. |
| **APA Citations Included** | Academic transparency for civic-tech or classroom study. |

---

## ⚙️ Technology Stack

| Layer | Technology |
|--------|-------------|
| Front-end | HTML5 / CSS3 (Flexbox) / Vanilla JavaScript |
| Back-end | PHP 8 + PDO |
| Storage | SQLite (`scsn.db`) or MariaDB |
| Architecture | Flat-file / Single-page demo |
| License | CC BY-NC-SA 4.0 (Non-commercial, Share Alike) |

---

## 🪜 Installation & Setup

### 🔹 1. Clone Repository
```bash
git clone https://github.com/yourusername/SCSN_SeniorCitizensSocialNetwork.git
cd SCSN_SeniorCitizensSocialNetwork
````

### 🔹 2. Local PHP Server (Safest Option)

```bash
php -S localhost:8080
```

Then open [http://localhost:8080/scsn_v1.php](http://localhost:8080/scsn_v1.php) in your browser.

### 🔹 3. Database Choice

By default, SCSN uses **SQLite** and auto-creates `scsn.db` in the project folder.
To switch to MariaDB:

1. Edit `SCSN_DB_DRIVER` in `scsn_v1.php`:

   ```php
   const SCSN_DB_DRIVER = 'mariadb';
   ```
2. Configure credentials:

   ```php
   const SCSN_MARIADB_DSN  = 'mysql:host=localhost;dbname=scsn;charset=utf8mb4';
   const SCSN_MARIADB_USER = 'scsn_user';
   const SCSN_MARIADB_PASS = 'your_password';
   ```

---

## 🧩 Interface Guide

| Section             | Function                                         |
| ------------------- | ------------------------------------------------ |
| **Header**          | App title and non-commercial notice.             |
| **Sidebar**         | Local user info panel + demo family/friend list. |
| **Menu**            | Login / Logout / Settings placeholders.          |
| **Main Feed**       | Highlighted content and scrollable posts.        |
| **Composer**        | Text box for short messages (≤ 280 characters).  |
| **Flip-Phone Pad**  | Training keys 1–3 post preset alerts.            |
| **Scroll Bar Hint** | Visual aid for scrolling through feed.           |

---

## 🔒 Safety Disclaimer

This prototype:

* Does **not** transmit real messages to 911 or law enforcement.
* Stores posts only in a local database.
* Should be used for educational demonstrations and UI training only.

---

## 🧠 Educational Concepts Demonstrated

* Safe civic communication design for vulnerable users.
* Event handling and DOM updates in vanilla JavaScript.
* PHP PDO usage with SQLite and MariaDB.
* Human-centered UX considerations for older adults.
* Data privacy in offline or LAN-only environments.

---

## 🧪 Testing Keypad Behavior

| Key   | Simulated Message                                                                     | Type                |
| ----- | ------------------------------------------------------------------------------------- | ------------------- |
| **1** | “I need help. Please check on me or call me when you can.”                            | Help                |
| **2** | “Training alert: This simulates a 911-type emergency message to the support network.” | 911 (Training)      |
| **3** | “Emergency and address (training message): I need immediate help at my home address.” | Emergency & Address |

---

## 📚 References (APA 7th Edition)

1. PHP Group. (2024). *PHP manual: PDO*. [https://www.php.net/manual/en/book.pdo.php](https://www.php.net/manual/en/book.pdo.php)
2. SQLite Consortium. (2025). *SQLite documentation*. [https://sqlite.org/docs.html](https://sqlite.org/docs.html)
3. MariaDB Foundation. (2025). *MariaDB server documentation*. [https://mariadb.org](https://mariadb.org)
4. Ready.gov. (2025). *Older adults: Disaster preparedness guide*. U.S. Department of Homeland Security. [https://www.ready.gov/older-adults](https://www.ready.gov/older-adults)
5. American Red Cross. (2025). *Emergency preparedness for older adults*. [https://www.redcross.org/get-help/how-to-prepare-for-emergencies/older-adults.html](https://www.redcross.org/get-help/how-to-prepare-for-emergencies/older-adults.html)
6. Owen, S., & OpenAI. (2025, November 3). *SCSN – Senior Citizens Social Network design conversation*. ChatGPT (GPT-5).

---

## 🪪 License

**Creative Commons Attribution Non-Commercial Share-Alike 4.0 International (CC BY-NC-SA 4.0)**
**ChatGPT Link: [https://chatgpt.com/share/690925e3-efa4-800c-8f88-9d731da827a8
](https://chatgpt.com/share/690925e3-efa4-800c-8f88-9d731da827a8
)\ — Developed with assistance from ChatGPT (GPT-5) for educational purposes.

---

## 🧩 Keywords

`PHP` · `SQLite` · `MariaDB` · `CivicTech` · `SeniorSafety` · `OfflineApps` · `EducationalDemo`

```

---


