# Online Grocery Management System

PHP based Online Grocery Ordering and Management System, ready for deployment on **Render** with **Aiven MySQL** cloud database.

---

## 🚀 Deployment Guide on Render

### 1. Prerequisites
- **GitHub Repository**: Push this code to your GitHub repo.
- **Aiven MySQL Service**: A running MySQL service on [Aiven.io](https://aiven.io).

---

### 2. Steps to Deploy on Render

1. Log in to your [Render Dashboard](https://dashboard.render.com/).
2. Click **New +** > **Web Service**.
3. Connect your GitHub repository (`online-grocery-management-system`).
4. Set the following settings:
   - **Name**: `online-grocery-system` (or your choice)
   - **Language / Runtime**: `Docker`
   - **Branch**: `main`
   - **Instance Type**: `Free`
5. Scroll down to **Environment Variables** and add your Aiven MySQL credentials.

---

### 3. Environment Variables for Aiven MySQL

You can use **either Option A (Single URL)** or **Option B (Separate fields)**:

#### Option A: Single Service URI (Easiest)
Copy the **Service URI** from your Aiven MySQL overview page:
```env
DATABASE_URL=mysql://avnadmin:YOUR_PASSWORD@YOUR_AIVEN_HOST:PORT/defaultdb?ssl-mode=REQUIRED
```

#### Option B: Individual Variables
```env
DB_HOST=YOUR_AIVEN_HOST (e.g. mysql-xyz-project.aivencloud.com)
DB_PORT=YOUR_AIVEN_PORT (e.g. 12345)
DB_USER=avnadmin
DB_PASSWORD=YOUR_AIVEN_PASSWORD
DB_NAME=defaultdb
```

> **Note**: Aiven requires SSL connections by default. The application is pre-configured with SSL support (`PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = false`), so you do not need to upload custom CA certificates.

---

### 4. Automatic Database Setup

You do **not** need to manually import any database dump.
When the application runs for the first time:
- It automatically creates all required MySQL tables (`admin_list`, `customer_list`, `category_list`, `fees_list`, `product_list`, etc.).
- It automatically seeds the default Administrator account and sample categories/fees.

*(Optional)* If you still want to import the schema manually, the complete SQL script is available in [`schema.sql`](schema.sql).

---

## 🔑 Default Login Credentials

- **Admin Portal**: `https://<your-render-url>/admin`
  - **Username**: `admin`
  - **Password**: `admin123`

- **Customer Portal**: `https://<your-render-url>/`
  - Customers can register their own accounts from the homepage.
