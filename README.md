Klassenbuch module for Moodle (http://moodle.org/) - a modified version of the Book module for Moodle (http://moodle.org/) - Copyright (C) 2004-2011  Petr Skoda (http://skodak.org/)

The Klassenbuch module makes it easy to create multi-page resources with a book-like format. This module can be used to build complete book-like websites inside of your Moodle course.
This module was developed for Technical University of Liberec (Czech Republic). Many ideas and code were taken from other Moodle modules and Moodle itself

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details: http://www.gnu.org/copyleft/gpl.html

Changes:

* 2017-03-22: M3.2 compatibility fixes (in exportimscp + importhtml tools)
* 2016-11-21: Minor M3.2 compatibility fix (only behat affected)
* 2016-10-17: Several Moodle 2.7+ compatibility fixes, 'class plan' editing option

Differences from the book module:

* Ability of students to subscribe to email updates about the book (sent out when the teacher clicks on the 'email' icon for a chapter).
* Structure the content of each chapter using globally defined custom fields to define the sections within each chapter.
* Treeview for the table of contents (to allow subchapters to be collapsed).
* PDF export option.
* Autosave during chapter creation, to avoid losing unfinished work.

Note about the PDF export.
If you want to include a logo on the export page, create a file called glogo.jpg and save it in mod/klassenbuch/tool/print/pix
(preferably 122x60 pixels). If you want to set the PDF's 'author' name and/or want to add a hyperlink to this logo, then copy
mod/klassenbuch/tool/print/pdfklassenbuch_details_dist.php to mod/klassenbuch/tool/print/pdfklassenbuch_details.php and edit
to set the details you want to use.

Created by:

* Petr Skoda (skodak) - most of the coding & design
* Mojmir Volf, Eloy Lafuente, Antonio Vicent and others
* Klassenbuch changes by Davo Smith and Yair Spielmann of Synergy Learning, on behalf of The Goethe Institut.


Project page:

* https://github.com/synergylearning/moodle-mod_klassenbuch
* http://moodle.org/plugins/view.php?plugin=mod_klassenbuch


Installation:

* http://docs.moodle.org/20/en/Installing_contributed_modules_or_plugins

Intentionally omitted features:

* more chapter levels - it would encourage teachers to write too much complex and long books, better use standard standalone HTML editor and import it as Resource. DocBook format is another suitable solution.
* TOC hiding in normal view - instead use printer friendly view
* PDF export - there is no elegant way AFAIK to convert HTML to PDF, use virtual PDF printer or better use DocBook format for authoring
* detailed student tracking (postponed till officially supported)
* export as zipped set of HTML pages - instead use browser command Save page as... in print view

Future:

* No more development planned
