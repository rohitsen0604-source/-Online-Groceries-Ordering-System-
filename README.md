# 🛒 Online Grocery Management System

[![Live Demo](https://img.shields.io/badge/Live_Demo-Render-46E3B7?style=for-the-badge&logo=render&logoColor=white)](https://online-groceries-ordering-system.onrender.com/)
[![Database](https://img.shields.io/badge/Database-Aiven_MySQL-00758F?style=for-the-badge&logo=mysql&logoColor=white)](https://aiven.io)
[![PHP](https://img.shields.io/badge/PHP-8.2-777BB4?style=for-the-badge&logo=php&logoColor=white)](https://php.net)

PHP-based Online Grocery Ordering and Management System, containerized with Docker and deployed live on **Render** with a cloud-hosted **Aiven MySQL** database.

---

## 🔗 Live Application URLs

| Portal | Live Link | Purpose |
| :--- | :--- | :--- |
| 🛒 **Public Customer Store** | **[https://online-groceries-ordering-system.onrender.com/](https://online-groceries-ordering-system.onrender.com/)** | Browse groceries, add items to cart, register/login, and place orders. |
| 🛡️ **Admin Management Portal** | **[https://online-groceries-ordering-system.onrender.com/admin/](https://online-groceries-ordering-system.onrender.com/admin/)** | Manage products, inventory/stock, customer orders, and categories. |

---

## 🔑 Login Credentials

### 🛡️ Administrator Login (Owner / Manager)
- **Admin Portal URL**: [https://online-groceries-ordering-system.onrender.com/admin/](https://online-groceries-ordering-system.onrender.com/admin/)
- **Username / ID**: `admin`
- **Password**: `admin123`

*(Note: Admin panel me jakar aap naye products aur photos add kar sakte ho, stock badha sakte ho, aur customer ke orders confirm/deliver mark kar sakte ho).*

### 👤 Customer Login (Shopper)
- **Customer Store URL**: [https://online-groceries-ordering-system.onrender.com/](https://online-groceries-ordering-system.onrender.com/)
- Customer website kholkar top-right par **Register** button se naya account bana sakte hain aur shopping kar sakte hain.

---

## ✨ Features

### 👤 Customer Side (Public Storefront):
- **Product Catalog**: Filter by category (e.g. *Beverages*, *Canned Goods*, *Snacks*) or search by product name.
- **Stock Indicators**: Real-time product availability and price display.
- **Shopping Cart**: Add items, update quantities, and review order totals.
- **Checkout & Orders**: Place grocery orders with delivery details and track order status in **My Orders**.
- **Admin Quick Button**: Direct button in the header navbar to switch directly to the Admin Portal.

### 🛡️ Admin Management Panel:
- **Dashboard Overview**: Summary of categories, products, pending orders, and total customers.
- **Product Management**: Add, edit, or delete grocery products with photos and descriptions.
- **Inventory & Stocking**: Restock inventory, track quantities in/out, and manage stock alerts.
- **Order Processing**: View incoming customer orders, confirm items, and update delivery status (*Pending, Confirmed, Shipped, Delivered, Cancelled*).
- **Category & Fees Configuration**: Organize store departments and customize delivery charges.

---

## 🚀 Deployment Guide on Render

### 1. Prerequisites
- **GitHub Repository**: [https://github.com/rohitsen0604-source/-Online-Groceries-Ordering-System-](https://github.com/rohitsen0604-source/-Online-Groceries-Ordering-System-)
- **Render Web Service**: Connected via Docker runtime.
- **Aiven MySQL Database**: SSL-enabled cloud MySQL instance.

---

### 2. Environment Variables Configured on Render

In your Render Service settings under **Environment**, set the database variables:

```env
DB_HOST=mysql-155eaacb-rohitsen0604-7acc.b.aivencloud.com
DB_PORT=24024
DB_USER=avnadmin
DB_PASS=YOUR_AIVEN_PASSWORD
DB_NAME=defaultdb
```

> **Note**: Aiven MySQL enforces strict primary key requirements (`sql_require_primary_key = ON`) and SSL mode. The application includes SSL auto-negotiation (`PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = false`) and schema definitions with explicit primary keys on all tables.

---

## 🗄️ Database Structure

The schema is defined in [`schema.sql`](schema.sql) and automatically verified on startup by `DBConnection.php`.
- `admin_list` — Admin accounts and credentials.
- `customer_list` — Registered grocery shoppers.
- `category_list` — Grocery product categories.
- `product_list` — Product catalog and pricing.
- `inventory_list` — Stock levels and batch logs.
- `cart_list` — Active customer shopping baskets.
- `order_list` — Placed grocery orders and delivery details.
- `order_items` — Itemized order lines.
- `delivery_charges` / `fees_list` — Configurable shipping/delivery rates.
