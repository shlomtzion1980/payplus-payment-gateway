# PayPlus Product Syncer — API & Feature Reference

All endpoints live under the `payplus/v1` REST namespace.
Base URL: `https://yoursite.com/wp-json/payplus/v1/...`

## Prerequisites

- **Enable Partners Features** must be set to **Yes** in the PayPlus gateway settings for the REST API routes to register.
- The Product Syncer class (`WC_PayPlus_Product_Syncer`) is loaded and instantiated by the main plugin file.

---

## Authentication

All REST endpoints use the same Authorization header — a JSON string containing the PayPlus API credentials:

```
Authorization: {"api_key":"YOUR_API_KEY","secret_key":"YOUR_SECRET_KEY"}
```

The plugin validates these against the stored gateway settings (respects test/dev mode automatically).

---

## Endpoints

### 1. GET `/products` — List Products (Paginated)

Returns products in **PayPlus Commerce Format** with pagination.

| Parameter  | Type | Default | Description                    |
|------------|------|---------|--------------------------------|
| `page`     | int  | 1       | Page number (starts at 1)      |
| `per_page` | int  | 50      | Products per page (max 200)    |

**Example:**

```bash
curl "https://yoursite.com/wp-json/payplus/v1/products?page=2&per_page=50" \
  -H 'Authorization: {"api_key":"...","secret_key":"..."}'
```

**Response:**

```json
{
  "page": 2,
  "per_page": 50,
  "total_products": 230,
  "total_pages": 5,
  "products_count": 50,
  "products": [ ... ]
}
```

---

### 2. POST `/products/export` — Generate Full Export File

Builds a complete JSON file of **all** products in Commerce Format and returns a download URL.
Products are fetched in batches of 50 internally. The file is saved to `wp-content/uploads/payplus-exports/` with a randomized filename.

**Example:**

```bash
curl -X POST "https://yoursite.com/wp-json/payplus/v1/products/export" \
  -H 'Authorization: {"api_key":"...","secret_key":"..."}'
```

**Response (201):**

```json
{
  "total_products": 230,
  "file": "payplus-products-2026-03-24-134500-a1b2c3d4e5f6g7h8.json",
  "download_url": "https://yoursite.com/wp-content/uploads/payplus-exports/payplus-products-2026-03-24-134500-a1b2c3d4e5f6g7h8.json",
  "generated_at": "2026-03-24T13:45:00Z"
}
```

---

### 3. DELETE `/products/export` — Delete an Export File

Deletes a previously generated export file after it has been downloaded.

| Parameter | Type   | Required | Description                              |
|-----------|--------|----------|------------------------------------------|
| `file`    | string | Yes      | The filename returned by the POST export |

**Example:**

```bash
curl -X DELETE "https://yoursite.com/wp-json/payplus/v1/products/export?file=payplus-products-2026-03-24-134500-a1b2c3d4e5f6g7h8.json" \
  -H 'Authorization: {"api_key":"...","secret_key":"..."}'
```

**Response (200):**

```json
{
  "deleted": true,
  "file": "payplus-products-2026-03-24-134500-a1b2c3d4e5f6g7h8.json"
}
```

---

### 4. POST `/products/inventory` — Update Single Product Stock

Updates the stock quantity for a single product or variation.

**Request Body:**

```json
{
  "product_id": 123,
  "stock_quantity": 10
}
```

**Example:**

```bash
curl -X POST "https://yoursite.com/wp-json/payplus/v1/products/inventory" \
  -H 'Authorization: {"api_key":"...","secret_key":"..."}' \
  -H 'Content-Type: application/json' \
  -d '{"product_id": 123, "stock_quantity": 10}'
```

**Response (200):**

```json
{
  "product_id": 123,
  "success": true,
  "name": "Blue T-Shirt",
  "sku": "BTS-001",
  "previous_stock": 15,
  "new_stock": 10,
  "stock_status": "instock"
}
```

Stock status is automatically set to `outofstock` when quantity reaches 0.

---

### 5. POST `/products/inventory/bulk` — Bulk Update Stock

Updates stock quantities for multiple products in a single request.

**Request Body:**

```json
{
  "products": [
    { "product_id": 123, "stock_quantity": 10 },
    { "product_id": 456, "stock_quantity": 0 },
    { "product_id": 789, "stock_quantity": 25 }
  ]
}
```

**Example:**

```bash
curl -X POST "https://yoursite.com/wp-json/payplus/v1/products/inventory/bulk" \
  -H 'Authorization: {"api_key":"...","secret_key":"..."}' \
  -H 'Content-Type: application/json' \
  -d '{"products":[{"product_id":123,"stock_quantity":10},{"product_id":456,"stock_quantity":0}]}'
```

**Response (200):**

```json
{
  "total": 2,
  "success": 2,
  "failed": 0,
  "results": [
    { "product_id": 123, "success": true, "name": "Blue T-Shirt", "sku": "BTS-001", "previous_stock": 15, "new_stock": 10, "stock_status": "instock" },
    { "product_id": 456, "success": true, "name": "Red Hat", "sku": "RH-002", "previous_stock": 3, "new_stock": 0, "stock_status": "outofstock" }
  ]
}
```

Failed items are included in `results` with `"success": false` and an `error` message.

---

## Admin UI — Service Activation

The **Product Syncer** admin page (under PayPlus menu when Partners Features is enabled) includes a **Service Activation** section:

- An **Activation Endpoint URL** input field, pre-filled with the default PayPlus endpoint. Can be changed if instructed by PayPlus.
- An **Activate Product Syncer** button that sends a POST handshake to the activation URL.
- On successful activation, the button disappears and the received **token** is displayed and stored in `wp_options` (`payplus_product_syncer_token`).

The activation POST sends:

```json
{
  "payment_page_uid": "...",
  "domain": "https://yoursite.com",
  "platform": "woocommerce"
}
```

With the same `Authorization` header used by all other endpoints.

---

## Other Changes

- **`platform_id`** in the Commerce Format is set to **2** (WooCommerce) across all product and variant external IDs.
- **Send to PayPlus** button on the admin page sends Commerce Format products to the PayPlus gateway.
- All inventory updates and activation events are logged via `wc_get_logger()` under source `payplus-product-syncer`.
