# Weddingcity Query

PHP query snippet for the **Weddingcity WordPress plugin**. Returns users without listings, vendor contact details, and listing information as formatted JSON, without changing database records.

## Usage

1. Copy [weddingcity-query.php](weddingcity-query.php) into your PHP snippet plugin, omitting `<?php` if the editor supplies it.
2. Edit the settings at the top to select the report, dates, and listing titles.
3. Run in the admin area and open a dashboard page. Results appear at the bottom for administrators. Deactivate the snippet when finished.

Tested against WordPress/MySQL fixtures on PHP 7.4 and 8.3; a live Weddingcity installation was not tested.

MIT licensed; see [LICENSE](LICENSE).
