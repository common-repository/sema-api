=== SEMA API ===
Contributors: ssema
Tags: year make model search, auto parts search, year make model filter, auto parts filter, SEMA product import
Author: Steven Bao
Requires at least: 6.2
Tested Up To: 6.5.2
Stable tag: 5.24
Requires PHP: 5.2.4
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

== Description ==
The plugin is built to automatically transfer auto parts data from SEMA Data Coop to Wordpress/wooCommerce.  A comprehensive frontend catalog search page offers functions like year make model search, vehicle compatible fitment sheet and parts attributes fitlers. 
Just download the plugin, select the brands and categories of products you want to list, and begin automated imports to your online store, while simultaneously allowing product searches by vehicle, categories, and attribute filters.
Here's a link to [Frontend Demo](http://demo.semadata.org/catalog-search/)

[youtube https://www.youtube.com/watch?v=QOiT2Jin_kg]

== Screenshots ==

1. Catalog search with year/make/model/submodel, category and attribute filters.
2. Year/make/model/submodel fitment sheet for each product.
3. Brand setting page
4. Category setting page
5. Import page.

== Frequently Asked Questions ==

= Do I have to have SEMA SDC account? =
Yes, you might need it to generate a token for API import purpose.

= Does this plugin import product images? =

Yes. You can import product images along with other details and attribute

= Do I have to create a catalog search page? =
Our plugin will create a catalog search page automatically.

== Changelog ==
Version 5.25
Update:
* fixed import notification bubble issue.


Version 5.24
Update:
* minor update on css

Version 5.23
Update:
* wordpress tested up to 6.5.2

Version 5.22
Update:
* add product title check


Version 5.21
Update:
* product application tab updated

Version 5.20
Update:
* curl timeout error fixed

Version 5.19
Update:
* notification bugs fixed.

Version 5.18
Update:
* minor bugs fixed.

Version 5.17
Update:
* add in-system notices.

Version 5.16
Bugs:
* brand id update for non-subscriber.

Version 5.14
Update:
* minor security update.

Version 5.13
Update:
* update for membership check.

Version 5.12
Update:
* Major update to facilitate premium membership and packages support.

Version 4.66
Update:
* API migration to v2.

Version 4.61
Update:
* support wordpress without SESSION.

Version 4.58
Bugs:
* fix critical error issue.


Version 4.53
Update:
* support WP network multisites.


Version 4.39
Update:
* WC tested up to: 6.2.2
* fix minor issues

Version 4.22
Update:
* update all API versions.
Bug:
* fix an issue causing YMM search bar didn't work.

Version 4.21
Bug:
* fix empty price or image issues and other minor issues.

Version 4.15
Update:
* add "sync categories" button.

Version 4.13
Bug:
* fix product count error.

Version 4.12
Update:
* support multiple currency.

Version 4.11
Bug:
* fix category issues.

Version 4.09
Bug:
* fix minor issues.

Version 4.07
Update:
* allow images in media section.

Version 4.06
Bug:
* fix sema search bar and other issues.

Version 4.04
Update:
* fix product category issues.

Version 4.02
Update:
* fix security issues.

Version 3.64
Update:
* fix minor errors.

Version 3.59
Update:
* allow user to choose prices to import.

Version 3.54
Update:
* fix brand table error.

Version 3.53
Update:
* allow user to choose what price to import.

Version 3.51
Bug:
* fix custom Permalink issue.

Version 3.50
Bug:
* fix umimported product number

Version 3.49
Bug:
* minor bug fix

Version 3.48
Bug:
* support break point resume while rebuilding fitments

Version 3.45
Bug:
* correct imported products

Version 3.45
Update:
* compatible with mysql in old versions

Version 3.42
Update:
* speed up page loading for masssive products.

Version 3.40
Update:
* delete fitment in batch.

Version 3.39
Update:
* remove empty brand name.

Version 3.38
Update:
* fix submodel sort, pagination, and homepage shortcode issues.

Version 3.37
Update:
* security updates.

Version 3.36
Bug fix:
* delete unwanted products when brand ID is removed.

Version 3.35
Bug fix:
* fix duplicated case insensitive fitment issue.

Version 3.34
Bug fix:
* fix minor import issues.

Version 3.32
Update:
* add text search bar to catalog search page
Bug fix:
* can not import some brands with special characters.

Version 3.30
Update:
* add REST API to popluate vehicle data.

Version 3.28
Update:
* add Sync Categories button.

Version 3.24
Bug fix:
* fix non-parent category issue.

Version 3.23
Bug fix:
* allow the same category showing on multiple parent categories, like "Service Kits"

Version 3.22
Update:
* add jquery dependences

Version 3.21
Bug fix:
* fix bugs in fitment management

Version 3.20
Update:
* add [semasearchbar] shortcode 

Version 3.19
Bug fix:
* fix remote product image import failure.

Version 3.18
Bug fix:
* fix product fitment import failure.

Version 3.17
Update:
* restructure fitment page
Bug fix:
* fix max key lenghth issue
* turn off php warning and notification

Version 3.16
Update:
* allow to import product fitments without submodel
Bug fix:
* fix bugs while importing product fitments.

Version 3.15
Update:
* comply with SEMA API update.

Version 3.14
Update:
* Allow to update products partially, like title, description, prices, images, fitments and attributes.
Bug fix:
* fix reported bugs.

Version 3.13
Update:
* Add import date and sorting function in brand page.
Bug fix:
* exclude products without SKUs in fitment.

Version 3.11
Update:
* Allow users to rebuilt fitments if YMMS doesn't work.

Version 3.1
Bug fix:
blank page.

Version 3.0
Major Update:
* add YMMS fitment function. Allow users to create custom fitment and assign products to fitments
* add custom prefix function. Allow users to create custom prefix instead of brand id.


Version 2.97
Update:
* optimize database and page loading.
* publish to wordpress marketplace.



Version 2.96
What's New:
Update:
Bug fix:
import page conflict. Import page unaccessable if hosted by wordpress.com

Version 2.95
What's New:
+ add fitment sheet to product page
Update:
* set draft products without images while importing.
* update plugin directory of file path in include files.
* using google cdn to store jquery js/css files.
Bug fix:
fix product title missing.
fix low memory issue while saving categories for brands.
fix the issue of not being able to generate token if wordpress not installed under root directory


Version 2.94
What's New:
+ add weight conversion from GT(kg) to oz/lb/g
+ read weight unit from wooCommerce settings (oz/lb/g/kg).
+ add server name to activity record.
Update:
* re-write js to be compatible with old version jquery. fix $.noconflict() issue.
Bug fix:
* minor bugs

Version 2.93
What's New:
+ add activity tracking via API
Update:
+ create new table index to speed up search
Bug fix:
* ymms dropdown doesn't show right

Version 2.92
What's New:
+ add price markup/discount function in product import


Version 2.9.1
What's New:
+ check user Ip address
Bug fix:
* can't save on setting page if brand ids seperated by space.

Version 2.9
What's New:
+ delete products for each brand
Major Improvement:
+ use single jquery query to finish search instead of two query.
Bug fix:
* minor bugs on backend


What's New in Version 2.8
What's New:
+ add universal part option
Update:
* comply with different database table prefix and support cross domain.
Bug fix:
* correct price display
