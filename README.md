Text Search and replace 
==

[![Build Status](https://travis-ci.org/call-learning/moodle-tool_textcleanup.svg?branch=master)](https://travis-ci.org/call-learning/moodle-tool_textcleanup)

The purpose of this tool is to scan all text content in Moodle that can contain malicious javascript 
and see when/how it has been inserted and its exact location.

***Notice:** this tool is still at an early stage. Make sure you backup your database before you use it on 
a real data set. Best it to try it on a copy of the original database.

**Attention:** cet outil est encore en version de test. Il faut absolument faire une sauvegarde de votre base
de donnée avant de l'utiliser. 
Le mieux c'est de l'utiliser sur une copie de la base de données originale.

Use
==

Install the plugin and go to "Security > Search text and cleanup tool", and click on "Load/Reload data"
so to build a temporary table that will be used to speed up the searches.

Once the "Pre-Load/Reload data" is done, you can search using the widgets (Expression and Types).

The basic use is to:

* search for Strings and
* Then you can cleanup the data using the clean_text funtion ("Cleanup Data") 

The "Cleanup Data" button will scan all text field that are found in the search and clean them
using clean_text moodle function. This should remove all unwanted script. 

There are some plan to add selection to the fields so we just clean the selected ones but this
is work in progress.



Help needed !


TODO
==

* Add selector to search and replace specific text (using regexp) into all selected items
* Change pagination size


