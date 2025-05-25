# User Registrations Summary

**A lightweight WordPress plugin that generates daily summaries of new user registrations.** It provides:

* An **admin report** under **Users → Registration Summary** with a 7‑day bar chart and table.
* A **shortcode** (`[registration_summary days="N"]`) to display registration counts on any page.
* A **daily email** (sent at 00:30 server time) to the site admin, including:

  * Your site’s name
  * A 7‑day registration chart
  * Yesterday’s registration count

---

## Installation

1. Clone or download this repository into `wp-content/plugins/user-registrations-summary/`.
2. Activate **User Registrations Summary** from the **Plugins** screen in your WordPress admin.

## Usage

### Admin Report

Go to **Users → Registration Summary** to view:

* A bar chart of registrations over the last 7 days.
* A table listing daily registration counts.

### Shortcode

Place the shortcode in any post or page:

```php
[registration_summary days="7"]
```

* `days` (optional): Number of days to include (defaults to 7).

### Daily Email Summary

By default, the plugin sends an HTML email to the site admin each morning at **00:30** (server time). The email includes:

* Site name (as the email title)
* A 7‑day registrations chart (embedded image)
* New registrations count from yesterday

#### Customize Sender

The plugin automatically uses a **no-reply\@your-domain** address and your site title as the sender name. Ensure your DNS records (SPF, DKIM, DMARC) are configured for best deliverability.

## Dependencies

* [Chart.js](https://www.chartjs.org/) (loaded via CDN)
* [QuickChart.io](https://quickchart.io/) for chart images in emails

## License

MIT © William Yell
