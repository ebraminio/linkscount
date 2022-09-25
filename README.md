# Linkscount
Backend for https://www.wikidata.org/wiki/MediaWiki:Linkscount.js, running at https://linkscount.toolforge.org/.

## System requirements
* Bare-bones PHP 7.3+, running behind a web server (e.g. Apache or lighttpd). No Composer or other fancy stuff.
* Have an INI file called `replica.my.cnf` containing the database user and password in the root directory (next to the `public_html` directory). This should be automatically provided on Toolforge; if you don’t have Toolforge access, you’ll need to get it, otherwise you can’t access the databases anyway.
