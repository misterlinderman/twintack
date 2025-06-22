# Monday.com Integration API

## Overview
This API endpoint allows Make.com scenarios to update grip design posts with Monday.com data including mockup assets, feedback messages, and artwork status.

## Endpoint
```
POST /wp-json/twintack/v1/grip-design/{id}/monday
```

## Authentication
Include an API key in the request header or as a parameter:

**Header (Recommended):**
```
X-API-Key: your-secret-key
```

**Parameter:**
```
api_key=your-secret-key
```

**Default API Key:** `twintack-monday-2024`

**Custom API Key:** Define `TWINTACK_MONDAY_API_KEY` in wp-config.php for security

## Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | integer | Yes | Grip design post ID (in URL path) |
| `monday_feedback` | string | No | Message from design team |
| `mockup_asset_id` | string | No | Monday.com asset ID |
| `mockup_asset_url` | string | No | Direct URL to mockup asset |
| `artwork_status` | string | No | Status code (see status guide below) |

## Artwork Status Guide

### Customer-Facing Statuses
| Status Code | Label | Description |
|------------|-------|-------------|
| `artwork_pending` | Artwork Pending | Initial status, waiting for customer artwork or initial review |
| `pending_review` | Pending Review | Design team is reviewing the artwork |
| `customer_requested_changes` | Requested Changes | Customer has requested modifications |
| `customer_approved` | Customer Approved | Customer has approved the design |
| `artwork_approved` | Artwork Approved | Design approved and ready for production |
| `approved_for_production` | Approved for Production | Final approval after purchase completion |
| `in_production` | In Production | Currently being manufactured |
| `shipped` | Shipped | Order has been shipped to customer |

### Internal Statuses
| Status Code | Label | Description |
|------------|-------|-------------|
| `internal_review` | Internal Review | Under internal team review (hidden from customer) |

### Status Workflow
1. `artwork_pending` → Initial state
2. `pending_review` → After initial artwork submission
3. `customer_requested_changes` ↔ `pending_review` → Customer feedback loop
4. `customer_approved` → Customer approves design
5. `approved_for_production` → After purchase completion
6. `in_production` → Manufacturing started
7. `shipped` → Final state

## Example Request

```bash
curl -X POST "https://yoursite.com/wp-json/twintack/v1/grip-design/123/monday" \
  -H "X-API-Key: twintack-monday-2024" \
  -H "Content-Type: application/json" \
  -d '{
    "monday_feedback": "Design looks great! Ready for production.",
    "mockup_asset_id": "12345678",
    "mockup_asset_url": "https://files.monday.com/asset/12345678",
    "artwork_status": "artwork_approved"
  }'
```

## Example Response

```json
{
  "success": true,
  "grip_id": 123,
  "updated_fields": {
    "monday_feedback": "Design looks great! Ready for production.",
    "mockup_asset_id": "12345678",
    "mockup_asset_url": "https://files.monday.com/asset/12345678",
    "artwork_status": "artwork_approved",
    "artwork_status_label": "Artwork Approved"
  },
  "message": "Grip design updated successfully"
}
```

## Error Responses

**Invalid API Key (401):**
```json
{
  "code": "rest_forbidden",
  "message": "Invalid API key",
  "data": {"status": 401}
}
```

**Grip Design Not Found (404):**
```json
{
  "code": "not_found",
  "message": "Grip design not found",
  "data": {"status": 404}
}
```

## Make.com Integration

### Finding Grip Design ID
You can find grip designs by customer email using the WordPress REST API:
```
GET /wp-json/wp/v2/grip-designs?meta_key=_grip_customer_email&meta_value=customer@example.com
```

### Recommended Make.com Scenario Flow
1. **Monday.com Trigger** - When item status changes or file is uploaded
2. **Get Customer Email** - Extract from Monday.com item
3. **Find Grip Design** - Search WordPress for matching grip design
4. **Update Grip Design** - Use this API endpoint to update with Monday.com data

### Asset URL Handling
The `mockup_asset_url` should be a direct, publicly accessible URL to the asset. If Monday.com provides asset IDs that require authentication, your Make.com scenario should:

1. Get the asset ID from Monday.com
2. Use Monday.com API to get a public/downloadable URL
3. Pass both the asset ID and public URL to this endpoint

## Security Notes
- Change the default API key in production
- Use HTTPS for all API requests
- Consider IP whitelisting for additional security
- Monitor API usage in WordPress debug logs 