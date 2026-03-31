## Syntax

```javascript
// Front-end (remote origin)
const resp = await api.dispatchEvent('contact-form', { data: { name: 'John', email: 'john@example.com', message: 'Hello' } });
```

## Description

Sends a contact form submission to the configured email address. All submitted fields are included in the email body.

- **Event:** `contact-form`
- **Class:** `Zolinga\Commons\ContactForm`
- **Method:** `onContactForm`
- **Origin:** `remote`
- **Event Type:** `\Zolinga\System\Events\WebEvent`

## Parameters

| Field | Type | Description |
|---|---|---|
| `data` | `object` | Key-value pairs of form fields to send |

## Configuration

The recipient email is configured in `$api->config['contact']['email']` (default: `info@{hostname}`).
