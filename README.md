\# Meterly – Utility Bill Management System



\## 1. Project Overview



Meterly is a web-based utility bill management system designed to help users manage electricity bills, track payments, manage utility accounts, and receive payment reminders through a centralized platform.



The system provides a platform for customers and administrators to manage utility-related activities, organize billing information, and monitor payment records.



\## 2. Key Features



\### User Management

\- User registration and login.

\- Secure authentication and session management.

\- Password reset functionality.

\- Customer account management.



\### Utility Account Management

\- Add utility accounts.

\- View saved utility accounts.

\- Edit and manage account information.

\- Delete utility accounts.



\### Bill Management

\- Create and manage electricity bills.

\- View bill records.

\- Edit and delete bills.

\- Associate bills with saved utility accounts.



\### Payment Management

\- View payment records.

\- Monitor payment information and statuses.

\- Manage payment-related records.



\### Reminder Management

\- Payment reminder functionality.

\- Reminder notifications for upcoming bills.

\- Administrative reminder management.



\### Administrative Dashboard

\- Manage customers.

\- Manage utility accounts.

\- Manage bills and payment records.

\- Manage utility providers.

\- Monitor system activities.



\## 3. Technologies Used



The Meterly application was developed using the following technologies:



| Technology | Purpose |

|---|---|

| HTML5 | Application structure |

| CSS3 | Interface styling |

| JavaScript | Frontend functionality |

| PHP | Server-side processing |

| MySQL/MariaDB | Database management |

| XAMPP | Local development environment |

| Git and GitHub | Version control and source code hosting |



\## 4. System Requirements



To run Meterly locally, the following are required:



\- Windows operating system.

\- XAMPP server.

\- Apache web server.

\- MySQL or MariaDB database.

\- PHP 8.2 or compatible version.

\- Modern web browser.



\## 5. Installation and Setup



Follow these steps to set up Meterly on a local computer.



\### Step 1: Install XAMPP



Download and install XAMPP, then start the Apache and MySQL services through the XAMPP Control Panel.



\### Step 2: Clone the Repository



Open Command Prompt and navigate to the XAMPP htdocs directory.



Run:



```bash

cd C:\\xampp\\htdocs

```



Clone the project:



```bash

git clone https://github.com/Shegzbanj/Meterly.git

```



\### Step 3: Configure the Database



1\. Open phpMyAdmin through `http://localhost/phpmyadmin`.

2\. Create a MySQL database named `meterly\_db`.

3\. Import the Meterly SQL database export when provided.

4\. Configure the database connection using the example configuration in `config/database.example.php`.

5\. Set the appropriate database host, database name, username, and password for your local environment.



Keep actual database credentials private.



\### Step 4: Install PHP Dependencies



If Composer is installed, navigate to the project directory and run:



```bash

composer install

```



\### Step 5: Run the Application



Open your browser and visit:



```text

http://localhost/Meterly/

```



The application should load in your local development environment.



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



\## 7. Implementation Limitations



Some features are not fully integrated with external services.



\- External utility-provider services are not fully integrated.

\- Payment confirmation through an external payment gateway is not fully integrated.

\- The application does not currently provide a fully implemented data-purchasing service.



These limitations should be considered when evaluating the application's functionality.



\## 8. Security Considerations



\- Database credentials should not be committed to a public repository.

\- Environment variables should be used for sensitive configuration.

\- User authentication and session management should be properly maintained.

\- Real customer information should not be published in database exports.

\- Private configuration files are excluded through `.gitignore`.



\## 9. Project Purpose



Meterly was developed as an academic software development project to demonstrate the application of web development technologies and database management in building a utility bill management platform.



\## 10. Repository



GitHub Repository:



https://github.com/Shegzbanj/Meterly



\## 11. License



This project was developed for academic purposes. All rights are reserved by the project developers unless otherwise stated.

