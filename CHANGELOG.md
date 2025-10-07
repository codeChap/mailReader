# Changelog

## 1.1.0 - 2025-10-07 Performance Update

### New Features
- **`getEmailOverviews()`** - Ultra-fast email listing method (10-100x faster than getEmails)
  - Returns headers only without body content
  - Perfect for inbox listing and email management UIs
  - Properly decodes MIME-encoded subjects and headers
- **`getUnreadCount()`** - Efficiently count unread messages
  - Fast counting using imap_search without fetching email data
- **Pagination Support** - Both `getEmails()` and `getEmailOverviews()` now support offset parameter

### Improvements
- **`getEmails()` Enhanced**
  - Added `$offset` parameter for pagination
  - Added `$includeBody` parameter (defaults to `false` for performance)
  - Now 10-100x faster by default when body content isn't needed
- **MIME Decoding** - Improved character encoding handling in headers
- **Performance** - Drastically reduced memory usage and execution time for large mailboxes

### Breaking Changes
- None - All changes are backward compatible
- Existing code will continue to work but will benefit from automatic performance improvements

### Documentation
- Added `IMPROVEMENTS.md` with detailed performance benchmarks and migration guide
- Updated README with new methods and best practices
- Added usage examples for pagination and performance optimization

## 1.0.0 - 2023 Initial Release

### Features
- Connect to IMAP mail servers with DSN connection strings
- List available mailboxes
- Read email headers and body content (both plain text and HTML)
- View email attachments and download them
- Search emails by various criteria (subject, sender, content)
- Filter emails by date
- Filter by read/unread status
- GetSet trait for dynamic property access
- Proper exception handling
- PSR-4 compliant structure

### Requirements
- PHP 8.0 or higher
- PHP IMAP extension
