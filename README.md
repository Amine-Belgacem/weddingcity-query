# Weddingcity Query

Four WordPress reporting scripts consolidated into one configurable PHP snippet. The reports return users without listings, vendor contact details, and listing information for a supplied list of titles. They read the database without changing records.

## Run with a snippet plugin

1. Open [weddingcity-query.php](weddingcity-query.php) and copy its contents into a new **PHP** snippet. Omit the opening `<?php` tag when the snippet editor already supplies it.
2. Edit `$weddingcity_query_settings` at the top of the snippet. Set `report` to one of the values below, then set the dates or titles that report needs.
3. Select **admin area only** execution in the snippet plugin, or **run everywhere** if that is the available option. Activate the snippet and open a WordPress dashboard page.
4. The report appears as formatted JSON at the bottom of the admin page, above the footer. Only a signed-in user with `manage_options` can see it. Copy the JSON and deactivate the snippet when finished.

This file is a snippet, so it does not need to be installed as a WordPress plugin. The existing Weddingcity data must be present in the same WordPress database.

## Reports

| `report` value | Result | Settings |
| --- | --- | --- |
| `users_without_listings` | User ID, email, first name, last name | `listing_status` |
| `users_without_listings_by_date` | The same fields plus registration date, filtered by registration dates | `start_date`, `end_date`, `listing_status` |
| `vendors_without_listings` | The date-filtered users enriched with vendor names and phone numbers | `start_date`, `end_date`, `listing_status` |
| `listings_by_title` | Listing ID, date, author details, and whether each requested title was found | `titles` |

The first three reports select users whose **`user_role` metadata exists**, regardless of its value. This preserves the original scripts' selection rule; it is separate from WordPress's built-in account roles.

By default, a **published** `listing` post counts as a listing. A user with only draft listings is therefore included. Set `listing_status` to another registered post status or to `any` to count every status, including drafts and trash.

Dates use `YYYY-MM-DD`. Both boundary dates are included, through `23:59:59` on the last day. Dates are compared with `user_registered` as stored, without timezone conversion. The dates already in the settings reproduce the original reporting period; change them for a different period.

The vendor report reads published `vendor` posts and connects each profile to a user through the profile's `user_id` metadata. It reads `user_contact`, `first_name`, and `last_name`. If several profiles belong to the same user, the highest-ID published profile is selected. Missing or empty vendor names fall back to the user's names. Users without a profile remain in the report with an empty phone number.

The title report searches `listing` posts across **all statuses**, as the original script did. Matching follows the database's title collation, which may ignore case or accents. When several listings match, the lowest ID is selected. Missing titles return `found: false`, null post fields, and empty author fields. This is a title lookup, not an automatic diagnosis of broken listings. The original list of 35 titles remains in the settings and can be replaced.

## Use from PHP

The same file can return arrays to other code without registering the dashboard output:

```php
define('WEDDINGCITY_QUERY_LIBRARY_ONLY', true);
require_once '/absolute/path/to/weddingcity-query.php'; // Inside a loaded WordPress environment.

$rows = weddingcity_query('vendors_without_listings', array(
    'start_date' => '2021-09-07',
    'end_date' => '2021-09-30',
    'listing_status' => 'publish',
));
```

Invalid report names, dates, statuses, or title lists raise `InvalidArgumentException`. Database query failures raise `RuntimeException`. Dashboard output displays these errors as text. The callable function is for trusted PHP code; only the dashboard renderer checks administrator capability.

## Validation

Validation uses an isolated WordPress 6.4.12 / MySQL 8.4.3 database with matching `listing`, `vendor`, and metadata fixtures, on PHP 7.4 and 8.3. It covers all four reports, date boundaries, missing and duplicate records, output escaping, administrator access, repeated inclusion, and database errors. The actual Weddingcity plugin and a live Weddingcity site were unavailable for integration testing.

MIT licensed; see [LICENSE](LICENSE).
