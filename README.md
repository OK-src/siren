#Introduction

The crawler, which is almost finished but still with some defects, is a part of would have been a search engine named "siren"(the name coming from mythological creatures from the odyssey, which told news to men, driving them insane), which I abandoned. I made the error of thinking this could be a side project that I would drop after finding something better to do, but I soon realized that this was a project of massive scale and I don't think that that is something worth my time.

#How it works

All the necessary tables are described in the "mariadb" folder. "siteCrawler.php", using the function from "functions.php", loops on all the sites in the "sites" table, querying each one to extract their contents to fill other tables. First of all check "robots.txt" to check what pages the site allows itself to be seen on. If the site has a sitemat it uses it to show all the pages of the site, else it goes to that site to see what links the front-end has, and therefore it is necessary to extract them through a more rapid process. The pages, sites, and images are analyzed together with "robots.txt" and pages already in the database to determine the priority for updates, and pages to create or remove. Once finished sites and images are added in their respective tables if they aren't already present in the database. To start the operation you must give it an initial site. This is done with "addSites.php" which allows inserting sites even if they aren't attached to other sites. "siteCrawler.php" is meant to be always executed, not only when a user connects to a server, and the feature in "robots.txt" that should remove non existing pages has not yet been implemented, which should have also looped on all the sites to extract information.

#Problems/unfinished features

I am not sure that "robots.txt" works correctly.

The function "validateUrl()" takes a very long time to execute and it isn't able to mark an existing page that contains an immediate redirect to another page.
