=== Formidable PRO2PDF ===
Contributors: rasmarcus
Donate link: http://formidablepro2pdf.com/
Tags: pro2pdf, pdf, generation, pdftk, formidable, forms, create
Requires at least: 3.0.1
Tested up to: 6.8
Stable tag: 3.21
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Map web forms to PDF forms then with one simple shortcode - display a link on any post,  page, form, or view the merged PDF on a PC or mobile device.

== Description ==

Formidable Form add-on plugin to map Formidable Form fields to PDF form fields. Then - with one simple shortcode - display a download link or button on any post, page, form, or view to the filled-in PDF document on your web user's PC or mobile device.

Features:

= FREE VERSION =

* Create Webform to PDF form Field Maps
* Shortcode to Fill and Download PDFs
* Import/Export Pre-Made Templates
* Includes Complete Working Demo
* [Free Templates](http://formidablepro2pdf.com/templates) on Plugin Site
* Automatic Downloads
* Flatten PDF Form


= CONTRIBUTE VERSION =

* Email PDF as Attachment
* Map Two Datasets to One PDF
* Password Protect PDF File
* Format PDF Fields
* Export to .docx file
* Works with all Formidable Field Types
* Works with Formidable Signature Addon
* Works with Formidable Repeatable Sections
* Works with Formidable Embedded Forms
* Unlimited Forms/Sites Available

Visit the [Formidable PRO2PDF website](http://www.formidablepro2pdf.com/) to compare versions, review documentation, and for support.

[youtube http://www.youtube.com/watch?v=zOA-rGyv-js]

== Installation ==

This section describes how to install the plugin and get it working.

= From your WordPress dashboard = 
1. Visit 'Plugins → Add New'
2. Search for 'Formidable PRO2PDF'
3. Activate Formidable PRO2PDF from your Plugins page.

= From WordPress.org = 

1. Download FormidablePRO2PDF.
2. Upload the 'formidablepro-2-pdf' directory to your `/wp-content/plugins/` directory, using your favorite method (ftp, sftp, scp, etc...)
3. Activate Formidable PRO2PDF from your Plugins page.

= Once Activated = 

1. Make sure that you have at least one Formidable form, **and at least one form entry** in the form you want to merge into a pdf.
2. Choose "New Field Map" in "Field Map to use".
3. Click "Upload a PDF file" in "Field Map Designer" section.
4. Choose new layout in "Layout to use".
5. Add field mappings to your PDF file in "Manage your custom layout here" section.
6. Copy/paste the shortcode or press "Export" button to download merged PDFs.

= Demo Installation Video =
[youtube http://www.youtube.com/watch?v=zOA-rGyv-js]

NOTE: The plugin uses [PDFTK](https://www.pdflabs.com/docs/pdftk-man-page/) to fill in PDF files. This means that your server has to have PHP shell commands enabled. If your server does not have `pdftk` installed, you can still use the plugin to generate 1 PDF file for 1 Formidable form.

If you [purchase](http://formidablepro2pdf.com/) the plugin using [our website](http://formidablepro2pdf.com/), you do not need to install `pdftk` or to enable shell commands on your server. You'll use our API according to the [Terms of Service](http://formidablepro2pdf.com/terms-of-service/). 

Enter the activation code on the plugin options page.

== Frequently Asked Questions ==

= What are the requirements? =

[Formidable Forms plugin](http://www.formidablepro.com), make sure that your server can execute shell commands, and that `pdftk` is installed on your server. 

You'll also need to have PHP `MB` or `iconv` extensions installed. They are usually installed on web servers.

If you purchase the plugin, no additional software installation is need.

PHP version should be at least 5.3.

= Does the plugin create PDF files? =

Not at this time. Currently the plugin populates pre-made PDF form fields with mapped data from Formidable Form and FormidablePro form fields.

Future plans include adding HTML to PDF capabilities.

= Is support offered for the free version? =

Yes - standard user support is available through the support forum or purchase a key code for premium level support.

= Does the plugin work with multisite installations? =

Yes, the plugin works with WordPress Multisite. Site limits still apply.

== Screenshots ==

1. One Simple Shortcode.
2. Map Formidable Form Fields to PDF Text Form Fields.
3. Export Dynamic PDFs in Wordpress.
4. Activatation Page.
5. Settings Page.
6. Templates Page.

== Changelog ==

= 3.21 =
Minor warnings fix

= 3.20 =
Minor warnings fix

= 3.19 =
PDF generation improvement
Temporary files management

= 3.18 =
Layout load edit fix
Multiple mail attachments fix
Opacity PDF to Image conversion fix
Minor improvements

= 3.17 =
Minor warnings fix

= 3.16 =
Timeout increase

= 3.15 =
2nd dataset fails to render in some cases

= 3.14 =
PDF source can’t be loaded error fix

= 3.13 =
Fatal error in some cases

= 3.12 =
Update process

= 3.11 =
Security update

= 3.09 =
Minor warnings and errors fix

= 3.08 =
Templates export fix

= 3.07 =
Custom TMP dir path support

= 3.06 =
PHP8 number format fix

= 3.05 =
PHP8 compatibility fix
Minor bug fixes and improvements

= 3.04 =
Some "Checkbox" not checked due incorrect encoding

= 3.03 =
Optimization

= 3.02 =
"Field format" not working in some cases

= 3.01 =
"Field preview" improvement

= 3.00 =
Minor bug fixes and improvements

= 2.99 =

"Address" and "Credit Card" fields support for repeatable sections
Repeatable 'if' conditions
Repeatable checkboxes fix
Repeatable multiple select field fix
Field keys in repeatable fields
Improved repeatable fields
Additional formatting options for date DD/MM/YY, numbers (1000, 1000.00, Intval), and "Lowercase" format
Additional date formats
Bugfix for Capitalize ALL formatting option
Duplicate signatures fix
Signature field fixes for Formidable 3.x including serialized and PHP warning fixes
Signatures missed with "Do not store entries" option
Filters: 'fpro2pdf_signature', 'fpro2pdf_sig_output_options', 'fpropdf_wpfx_extract_fields'
‘Rotation’ option for images
Added field to change PDF file name in emails
PDF filename bugfix for Firefox
Bugfix for generated PDF hook and multiple PDF attachments
Fixing failed to load PDF issues
Plugin hook for saving PDFs
Added conversion to DOCX using PDFaid
New flatten option to transform text into images preventing copy-paste
Secure download links
Option to restrict remote requests on local PDFTK fail
Bugfix for special UTF-8 characters, unicode support including č unicode fix
Bugfix for encoding data and import field map
Bugfix for format for same line [line1] [line2]
Bugfix for field names in PDF (IP, item key, etc.)
Formidable dynamic field added
Dynamic password and dynamic attachment name/password fix when form uses IDs
Dynamic email attachment name (by Field ID)
Dynamic field multiply shortcodes fix
Bugfix for attachment function compatibility
Delete 'Pdf' attachments after sending mail
Possibility to select which email notifications include PDF attachments
Checkbox "ampersand" value fix and ampersand bugfix after Formidable update
Missed value if field connected to "Create Post" action
Incorrect warnings fix, minor warning fixes, prevent unnecessary error messages in PHP error log
Loopback request fix
Duplicate 'fail' fix
'Notice' error_reporting fixes for frontend, generate/download, and general
Empty phone number format fix
Multiple select field fix
Bugfix for disabled mbstring
Bugfix for temp directory detection and optimization of temp directory cleaning
Uninstall DB fix and uninstall hooks to delete all plugin data
Backup restore entries fix and restore backup fix
Bugfix for show active websites and offline sites
Bugfix for slash issues and empty shortcode issue
Bugfix for compatibility with some other plugins
Bugfix for automatic field map creation
Bugfix for checkboxes/radio labels and multiple checkboxes bugfix (including non-premium users)
Bugfix for version notification and after MySQL functions update
Bugfix for PDF file name
Select field text fix
Image URL field bugfix
Upload folder permission issue fix
Bugfix for carriage return to comma and shortcode empty issue
Bugfix for offline sites
Bugfix for import field map
Compatibility with WP 4.3 and 4.5
Compatibility with MariaDB and PHP7
Removed deprecated mysql_* functions
Check if mysql_connect() exists and show error if not
Compatibility fixes for Formidable 3.x and offline sites
Create and overwrite Web Form/PDF/Field Map option
Added settings page where field previews won’t slow down field map designer unless enabled
Added automatic backups and automatic mapping for new field maps
Add link in admin under ACTIVATED FORMS tab
Show only current site URL in admin
Allow editors to manage fieldsets
Optimize getting dataset field options
Improved "role" shortcode parameter
Additional option for restricting user downloads by role or user ID
New shortcode parameters (role, user)
Image alignment options
Replaced field IDs with field keys in field map designer
Field map designer save button auto-selects saved field map

= 1.7.39 =

Improvements for slow servers and options for offline websites
Only compatible field maps for forms can be selected; made field map names required
Licence key won’t be removed automatically when expired
Removed missing repeatable fields shortcodes
Secure download links
Plugin hook for saving PDFs
Images and image uploads in PDFs
Date for Formidable updated/created fields now in local time
Additional counters and Webform Data Field ID
Additional formatting options for numbers, dates, capitalizing all text, repeatable fields, and removing empty lines in text fields
Fix multiple checkbox fields for non-premium users and other checkbox bugfixes
TypeIn signatures support
Fixed bugs with saving field maps, quotes and special characters in PDF field names
Added field to change PDF file name in emails
Added HTML fields
Warning for old Formidable plugin versions
Temporary folder bugfix for some servers
Bugfix for special UTF-8 characters
New formatting options and general bugfixes
Debug info for clients
PDFaid bugfixes and added conversion to DOCX
New flatten option to transform text into images to prevent copy-paste from PDFs
Added settings page; field previews won’t slow down field map designer unless enabled
Compatibility with WordPress 4.3
Option to select which email notifications include PDF attachments
New shortcode parameter label=0 that outputs only PDF URL
Automatic backups for field maps

= 1.6.0.17 =

Compatibility with WordPress 4.3, 4.2.2, and 4.2.1 (with Formidable Forms 2.0.04)
Better support for multiple checkboxes and dynamic Formidable fields
Added alert for PHP version, password protection, shortcodes, export into PDF, API interface, and Fields Map Designer
Bugfixes including CSS conflict with Fusion Builder, missing files, saving layout, PHP warnings, encoding issues, and general bugfixing
Added new formatting options for dates and removed extra lines in multiline text fields on some servers
Added button to duplicate layouts and removed unused or default form files and WebForm fields
Sorted WebForm Field IDs
Updated plugin URL
Signature plugin
Email attachments support
Add repeatable sections support

* Initial Release

== Upgrade Notice ==

= 1.6 =

First version of the plugin with basic functionality.
