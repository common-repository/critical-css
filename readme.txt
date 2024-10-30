=== Critical CSS and Javascript ===
Contributors: teamperformance
Tags: critical css, defer css, critical css js, critical js, critical javascript, critical stylesheet, critical assets, render blocking, render blocking css, render blocking js
Requires at least: 5.0
Tested up to: 5.4
Stable tag: 1.0.0
License: GNU General Public License v2.0 or later

Defer render blocking CSS and Javascript with the best Critical-CSS WordPress plugin

== Description ==

=  Critical CSS and Javascript =

This Critical CSS plugin was built exclusively to help you remove render-blocking resources such as CSS and Javascript. We use best practices to cautiously update your website scripts to load after your markup has completed loading. 

Our plugin also has the ability to defer scripts that are inline which tend to cause trouble when you defer their dependent scripts. We do this to ensure that your inline scripts will not break functionality on your site, while allowing you to benefit from the performance gains.

== Deferring CSS and Javascript Plugin Settings ==

* Ability to exclude specific and critical CSS files
* Toggle deferring inline Javascript
* Toggle deferring jQuery
* Ability to exclude specific and critical Javascript files

== How to ignore a critical CSS or Javascript file ==

Under the Tools section in the wp-admin of your site you will find a submenu “Defer CSS & Javascript”. Click into that page to find a text area where you can add the file that will need to be loaded immediately and not deferred. You can add filenames or path information to exclude. To add multiple assets to this textarea, simply separate them with a comma.

== Installation ==

* Install the Critical CSS WordPress plugin
* After installation, click activate on plugin and all your CSS and Javascript files will automatically be deferred.
