\# Meterly – Utility Bill Management System



\## 1. Project Overview



Meterly is a web-based utility bill management system designed to help users manage electricity bills, track payments, manage utility accounts, and receive payment reminders through a centralized platform.



The application provides customer and administrative functions for managing utility accounts, billing information, payment records, and reminders.



\## 2. Key Features



\### User Management

\- User registration and login.

\- User authentication and session management.

\- Password reset functionality.



\### Utility Account Management

\- Add utility accounts.

\- View saved utility accounts.

\- Edit and delete utility account information.



\### Bill Management

\- Create and manage electricity bills.

\- View and update bill records.

\- Associate bills with saved utility accounts.



\### Payment Management

\- View payment records.

\- Monitor payment information and statuses.



\### Reminder Management

\- Manage bill payment reminders.

\- Support reminder notifications.



\### Administrative Functions

\- Manage customer accounts.

\- Manage utility providers.

\- Manage bills, payments, and reminders.

\- View administrative activity.



\## 3. Technologies Used



| Technology | Purpose |

|---|---|

| HTML5 | Application structure |

| CSS3 | Interface styling |

| JavaScript | Frontend functionality |

| PHP | Server-side processing |

| MySQL/MariaDB | Database management |

| XAMPP | Local development environment |

| Git/GitHub | Version control and source code hosting |



\## 4. System Requirements



\- Windows or another compatible operating system.

\- XAMPP.

\- Apache web server.

\- MySQL or MariaDB.

\- PHP 8.2 or compatible version.

\- Modern web browser.

\- Composer for PHP dependency installation.



\## 5. Installation and Setup



\### Step 1: Install XAMPP



Install XAMPP and start Apache and MySQL using the XAMPP Control Panel.



\### Step 2: Clone the Repository



Open Command Prompt and navigate to the XAMPP htdocs directory.



```bash

cd C:\\xampp\\htdocs

```



Clone the repository:



```bash

git clone https://github.com/Shegzbanj/Meterly.git

```



\### Step 3: Create the Database



1\. Open `http://localhost/phpmyadmin`.

2\. Create a database named `meterly\_db`.

3\. Select the newly created database.

4\. Open the Import tab.

5\. Import the file `database/meterly\_schema.sql`.



This creates the database tables and relationships.



\### Step 4: Configure the Database Connection



Use `config/database.example.php` as the configuration reference.



Create a local copy named `config/database.php`.



On Windows Command Prompt, run:



```cmd

copy config\\database.example.php config\\database.php

```



Edit the local configuration or set the required environment variables for your MySQL connection.



Do not upload actual database passwords or private credentials.



\### Step 5: Install Dependencies



From the Meterly project directory, run:



```bash

composer install

```



\### Step 6: Run Meterly



Open your browser and visit:



```text

http://localhost/Meterly/

```



\## 6. Project Structure



```text

Meterly/

│

├── auth/

│   ├── login.php

│   ├── register.php

│   ├── logout.php

│   └── ...

│

├── config/

│   └── database.example.php

│

├── database/

│   └── meterly\_schema.sql

│

├── composer.json

├── composer.lock

├── index.html

├── index\_backup.html

├── reset\_password.php

├── send\_bill\_reminders.php

├── .gitignore

└── README.md

```



\## 7. Database Information



\- Database name: `meterly\_db`

\- Database system: MySQL/MariaDB

\- Schema file: `database/meterly\_schema.sql`

\- Database connection: PHP PDO



The published schema contains table definitions without existing customer records.



\## 8. Implementation Limitations



The following functionality is not fully integrated:



\- External utility-provider services.

\- External payment gateway confirmation.

\- Data purchasing functionality.



These limitations should be considered when evaluating the application's implementation.



\## 9. Security Considerations



\- Keep database credentials private.

\- Do not publish customer information or passwords.

\- Use environment variables for sensitive configuration.

\- Keep private configuration files excluded through `.gitignore`.



\## 10. Project Purpose



Meterly was developed as an academic software development project to demonstrate the use of web development technologies and database management in building a utility bill management platform.



\## 11. GitHub Repository



Repository: https://github.com/Shegzbanj/Meterly



\## 12. License



This project was developed for academic purposes. All rights are reserved by the project developers unless otherwise stated.

