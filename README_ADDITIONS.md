## Performance Features (v1.1.0)

### Ultra-Fast Email Listing

For listing emails in inbox views, use the new `getEmailOverviews()` method which is **10-100x faster** than `getEmails()`:

```php
// Fast listing without body content (recommended for inbox views)
$emails = $mailReader->getEmailOverviews(false, 30, 0);

foreach ($emails as $email) {
    echo "[{$email['id']}] {$email['subject']}\n";
    echo "From: {$email['from']} | Date: {$email['date']}\n";
    echo "Seen: " . ($email['seen'] ? 'Yes' : 'No') . "\n\n";
}
```

### Pagination Support

Both `getEmails()` and `getEmailOverviews()` now support pagination:

```php
// Page 1: first 20 emails
$page1 = $mailReader->getEmailOverviews(false, 20, 0);

// Page 2: next 20 emails
$page2 = $mailReader->getEmailOverviews(false, 20, 20);

// Page 3: next 20 emails
$page3 = $mailReader->getEmailOverviews(false, 20, 40);
```

### Unread Count

Quickly get the count of unread messages:

```php
$unreadCount = $mailReader->getUnreadCount();
echo "You have {$unreadCount} unread emails";
```

### Control Body Fetching

`getEmails()` now has an `$includeBody` parameter (defaults to `false` for performance):

```php
// Fast: headers only
$emails = $mailReader->getEmails(false, 30, 0, false);

// Slow: full body content
$emails = $mailReader->getEmails(false, 30, 0, true);
```

### Recommended Pattern

Use a two-step approach for best performance:

```php
// Step 1: List emails quickly (headers only)
$emails = $mailReader->getEmailOverviews(false, 30);

// Step 2: Fetch full details only when user selects an email
$selectedEmail = $mailReader->getEmail($emails[0]['id'], true);
echo $selectedEmail['body_html'];
```

See [IMPROVEMENTS.md](IMPROVEMENTS.md) for detailed performance benchmarks and migration guide.
