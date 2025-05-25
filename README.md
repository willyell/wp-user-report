# User Registrations Summary

**A lightweight WordPress plugin that generates daily summaries of new user registrations.** It provides:

- An **admin report** under **Users → Registration Summary** with a 7-day bar chart and table.
- A **shortcode** (`[registration_summary days="N"]`) to display registration counts on any page.
- A **daily email** (sent at 00:30 server time) to the site admin, including:  
  - Your site’s name  
  - A 7-day registration chart  
  - Yesterday’s registration count

---

## Installation

1. Clone or download this repository into `wp-content/plugins/user-registrations-summary/`.
2. Activate **User Registrations Summary** from the **Plugins** screen in your WordPress admin.

## Usage

### Admin Report

Go to **Users → Registration Summary** to view:

- A bar chart of registrations over the last 7 days.
- A table listing daily registration counts.

### Shortcode

Place the shortcode in any post or page:

```php
[registration_summary days="7"]
```

- `days` (optional): Number of days to include (defaults to 7).

### Daily Email Summary

By default, the plugin sends an HTML email to the site admin each morning at **00:30** (server time). The email includes:

- Site name (as the email title)
- A 7-day registrations chart (embedded image)
- New registrations count from yesterday

#### Customize Sender

The plugin automatically uses a **no-reply@your-domain** address and your site title as the sender name. Ensure your DNS records (SPF, DKIM, DMARC) are configured for best deliverability.

## Dependencies

- [Chart.js](https://www.chartjs.org/) (loaded via CDN)
- [QuickChart.io](https://quickchart.io/) for chart images in emails

## Auto-Updates via GitHub Releases

To enable in-dashboard updates from your GitHub releases, include the [plugin-update-checker](https://github.com/YahnisElsts/plugin-update-checker) library in your plugin:

1. **Download the library**
   - Visit the [releases page](https://github.com/YahnisElsts/plugin-update-checker/releases) and download the latest ZIP (`plugin-update-checker.zip`).
   - Unzip and copy the entire `plugin-update-checker` folder into your plugin’s root directory.

2. **Verify file structure**
   
   ```text
   user-registrations-summary/
   ├── plugin-update-checker/
   │   ├── plugin-update-checker.php
   │   └── src/
   ├── user-registrations-summary.php
   └── README.md
   ```

3. **Commit to GitHub**
   ```bash
   git add plugin-update-checker/
   git commit -m "Add plugin-update-checker library for GitHub auto-updates"
   git push
   ```

4. **Configure update checker**
   In your `user-registrations-summary.php`, ensure you have:
   ```php
   require __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
   $updateChecker = Puc_v4_Factory::buildUpdateChecker(
       'https://api.github.com/repos/your-user/user-registrations-summary',
       __FILE__,
       'user-registrations-summary'
   );
   $updateChecker->setBranch('main');
   ```

> **Optional via Composer**  
> If you prefer Composer, add to your `composer.json`:
> ```json
> {
>   "require": {
>     "yahnis-elsts/plugin-update-checker": "^4.11"
>   }
> }
> ```
> Then run `composer install` and update the `require` path to `__DIR__ . '/vendor/autoload.php'`.

## License

MIT © William Yell
