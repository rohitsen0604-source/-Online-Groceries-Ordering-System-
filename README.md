# Online Grocery Management System

PHP-based Online Grocery Ordering and Management System, containerized with Docker and deployed on **Render** using a cloud-hosted **Aiven MySQL** database.

---

## 🌐 Live Application Links

| Portal | URL | Description |
| :--- | :--- | :--- |
| 🛒 **Customer Store (Public)** | [https://online-groceries-ordering-system.onrender.com/](https://online-groceries-ordering-system.onrender.com/) | Browse items, add to cart, register/login, and place orders. |
| 🛡️ **Admin Panel** | [https://online-groceries-ordering-system.onrender.com/admin/](https://online-groceries-ordering-system.onrender.com/admin/) | Manage products, inventory, orders, categories, and customers. |

---

## 🔑 Login Credentials

### 🛡️ Administrator Account
Use these credentials to log in to the **Admin Panel** (`/admin`):
- **Admin Portal URL**: [https://online-groceries-ordering-system.onrender.com/admin/](https://online-groceries-ordering-system.onrender.com/admin/)
- **Username / ID**: `admin`
- **Password**: `admin123`

### 👤 Customer Account
- Customers can create their own account anytime by clicking the **Register** button on the public storefront.
- Once registered, customers can log in using their credentials to view their cart and manage their orders.

---

## ✨ Features

### 👤 Customer Side (Public Storefront):
- **Product Catalog**: Filter by category (e.g. *Beverages*, *Canned Goods*, *Snacks*) or search by product name.
- **Stock Indicators**: Real-time product availability and price display.
- **Shopping Cart**: Add items, update quantities, and review order totals.
- **Checkout & Orders**: Place grocery orders with delivery details and track order status in **My Orders**.
- **Direct Admin Shortcut**: Quick button in the header navbar to switch directly to the Admin Portal.

### 🛡️ Admin Management Panel:
- **Dashboard Overview**: Summary of categories, products, pending orders, and total customers.
- **Product Management**: Add, edit, or delete grocery products with photos and descriptions.
- **Inventory & Stocking**: Restock inventory, track quantities in/out, and manage stock alerts.
- **Order Processing**: View incoming customer orders, confirm items, and update delivery status (Pending, Confirmed, Shipped, Delivered, Cancelled).
- **Category & Fees Configuration**: Organize store departments and customize delivery charges.

---

## 🚀 Deployment Guide on Render

### 1. Prerequisites
- **GitHub Repository**: [https://github.com/rohitsen0604-source/-Online-Groceries-Ordering-System-](https://github.com/rohitsen0604-source/-Online-Groceries-Ordering-System-)
- **Render Web Service**: Connected via Docker.
- **Aiven MySQL Database**: SSL-enabled cloud MySQL instance.

---

### 2. Environment Variables configured on Render

In your Render Service settings under **Environment**, set the database variables:

```env
DB_HOST=mysql-155eaacb-rohitsen0604-7acc.b.aivencloud.com
DB_PORT=24024
DB_USER=avnadmin
DB_PASS=YOUR_AIVEN_PASSWORD
DB_NAME=defaultdb
```

> **Note**: Aiven MySQL enforces strict primary key requirements (`sql_require_primary_key = ON`) and SSL mode. The app includes SSL auto-negotiation (`PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT = false`) and schema definitions with explicit primary keys on all tables.

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
