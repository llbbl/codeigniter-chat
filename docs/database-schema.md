# CodeIgniter Chat Database Schema

This document describes the database schema used by the CodeIgniter Chat application. It includes details about each table, its columns, and the relationships between tables.

## Tables Overview

The CodeIgniter Chat application uses the following tables:

1. `users` - Stores user account information
2. `messages` - Stores chat messages
3. `ci_sessions` - Stores session data (CodeIgniter's built-in session management)

## Table Structures

### users

The `users` table stores information about registered users.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | int | PRIMARY KEY, AUTO_INCREMENT | Unique identifier for the user |
| username | varchar(255) | NOT NULL | User's username (used for login) |
| email | varchar(255) | NOT NULL | User's email address |
| password | varchar(255) | NOT NULL | Hashed password |
| created_at | datetime | NOT NULL | Timestamp when the user account was created |
| updated_at | datetime | NOT NULL | Timestamp when the user account was last updated |

#### Indexes
- PRIMARY KEY on `id`
- UNIQUE INDEX on `username`
- UNIQUE INDEX on `email`

### messages

The `messages` table stores chat messages sent by users.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | int(7) | PRIMARY KEY, AUTO_INCREMENT | Unique identifier for the message |
| user | varchar(255) | NOT NULL | Username of the message sender |
| msg | text | NOT NULL | Content of the message |
| time | int(11) | NOT NULL, DEFAULT '0' | Unix timestamp when the message was sent |

#### Indexes
- PRIMARY KEY on `id`

### ci_sessions

The `ci_sessions` table is used by CodeIgniter to store session data.

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | varchar(40) | PRIMARY KEY | Session identifier |
| ip_address | varchar(45) | NOT NULL | IP address of the user |
| timestamp | int(10) unsigned | NOT NULL, DEFAULT '0' | Unix timestamp when the session was last updated |
| data | blob | NOT NULL | Serialized session data |

#### Indexes
- PRIMARY KEY on `id`
- INDEX `ci_sessions_timestamp` on `timestamp`

## Relationships

### Logical Relationships

While there are no explicit foreign key constraints in the database schema, there are logical relationships between the tables:

1. **User to Messages (One-to-Many)**
   - A user can send multiple messages
   - Each message is associated with one user through the `user` column in the `messages` table, which corresponds to the `username` column in the `users` table

### Data Integrity

Since there are no explicit foreign key constraints, data integrity is maintained at the application level:

- When a message is inserted, the application ensures that the user exists
- The application validates user input before inserting data into the database
- The application uses parameterized queries to prevent SQL injection

## Database Diagram

```
+---------------+       +---------------+
|    users      |       |   messages    |
+---------------+       +---------------+
| id (PK)       |       | id (PK)       |
| username      |<----->| user          |
| email         |       | msg           |
| password      |       | time          |
| created_at    |       |               |
| updated_at    |       |               |
+---------------+       +---------------+
                        
+---------------+
| ci_sessions   |
+---------------+
| id (PK)       |
| ip_address    |
| timestamp     |
| data          |
+---------------+
```

## SQL Scripts

### Create Tables

The following SQL scripts can be used to create the necessary tables:

```sql
-- Create users table
CREATE TABLE `users` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create messages table
CREATE TABLE `messages` (
  `id` int(7) NOT NULL AUTO_INCREMENT,
  `user` varchar(255) CHARACTER SET latin1 NOT NULL,
  `msg` text CHARACTER SET latin1 NOT NULL,
  `time` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Create ci_sessions table
CREATE TABLE `ci_sessions` (
  `id` varchar(40) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `timestamp` int(10) unsigned NOT NULL DEFAULT '0',
  `data` blob NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ci_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
```

## Notes

1. The `users` table is managed by the `UserModel` class in `app/Models/UserModel.php`.
2. The `messages` table is managed by the `ChatModel` class in `app/Models/ChatModel.php`.
3. The `ci_sessions` table is managed by CodeIgniter's built-in session management.
4. Passwords are hashed using PHP's `password_hash()` function with the `PASSWORD_DEFAULT` algorithm.
5. The `time` column in the `messages` table stores Unix timestamps, which can be converted to human-readable dates using PHP's `date()` function.