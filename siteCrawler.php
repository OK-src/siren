<?php
	require_once('functions.php');
	$conn = connect();
	
	while(TRUE){
		$counter = 1;
		$maxId = 1;
		
		//updating cycle of information on the sites
		while($counter <= $maxId){
			//pick up the url 
			$query = mysqli_query($conn, "SELECT url FROM `sites` WHERE id = '$counter'");
			$fromObj = mysqli_fetch_assoc($query);  
			$url = $fromObj["url"];
			
			if(validateUrl($url)){ //check if the url exist
				//search for robots.txt
				$domain = domain($url);
				$robotsFile = $domain[0] . '/robots.txt';
				
				if(validateUrl($robotsFile)){
					$robotsContent = file_get_contents($robotsFile);
					
					//control which pages are allowed to siren
					$robots = checkRobots($robotsContent);
					//check if the site completely blocks our crawlers 
					if((count($robots[0]) == 0) AND (count($robots[1]) == 1)){
						if((preg_match('^(All|\/|\*)$/', $robots[1][0])) OR ($robots[1][0] == '')){
							//removal of all pages of the site from the database
							$sql = "DELETE FROM pages WHERE site = '$counter'";
							$conn->query($sql);
						}
					} else {
						//search for all pages on the site via sitemap or crawling 
						if(preg_match('/Sitemap: (.*)/', $robotsContent, $matches)){
							//sitemap method
							$sitemap = $matches[1];
							$pages = extractSitemap($sitemap);
							$crawl = crawlBySitemap($pages, $domain[0]);
							$otherSites = $crawl[0];
							$images = $crawl[1];
						} else {
							//crawling method
							$crawl = crawl($domain[0]);
							$pages = $crawl[0];
							$otherSites = $crawl[1];
							$images = $crawl[2];
						}
						
						//extraction of the pages of the site from the database
						$query = mysqli_query($conn, "SELECT * FROM pages WHERE site = $counter");
						$pagesInDatabase = array();
						if($query){
							while($fromObj = mysqli_fetch_assoc($query)){
								array_push($pagesInDatabase, array($fromObj['id'], $fromObj['url'], $fromObj['priority']));
							}
						}
						
						//inserting pages in the database
						$actionNeeded = updatePages($pages, $pagesInDatabase, $robots);
						
						//creation of new pages
						$anOtherCounter = 0;
						while($anOtherCounter < count($actionNeeded[0])){
							$url = $actionNeeded[0][$anOtherCounter][0];
							$priority = $actionNeeded[0][$anOtherCounter][1];
							$sql = "INSERT INTO pages (url, priority) VALUES ('$url', '$priority')";
							$conn->query($sql);
							$anOtherCounter++;
						}
						
						//deletion of existing pages
						$anOtherCounter = 0;
						while($anOtherCounter < count($actionNeeded[1])){
							$id = $actionNeeded[1][$anOtherCounter];
							$sql = "DELETE FROM pages WHERE id = '$id'";
							$conn->query($sql);
							$anOtherCounter++;
						}
						
						//priority update
						$anOtherCounter = 0;
						while($anOtherCounter < count($actionNeeded[2])){
							$id = $actionNeeded[2][$anOtherCounter][0];
							$priority = $id = $actionNeeded[2][$anOtherCounter][1];
							$sql = "UPDATE pages SET priority = '$priority' WHERE id = '$id'";
							$conn->query($sql);
							$anOtherCounter++;
						}
					}
				} else {
					//search for all pages on the site via crawling
					$crawl = crawl($domain[0]);
					$pages = $crawl[0];
					$otherSites = $crawl[1];
					$images = $crawl[2];
					
					//extraction of the pages of the site from the database
					$query = mysqli_query($conn, "SELECT * FROM pages WHERE site = $counter");
					$pagesInDatabase = array();
					if($query){
						while($fromObj = mysqli_fetch_assoc($query)){
							array_push($pagesInDatabase, array($fromObj['id'], $fromObj['url'], $fromObj['priority']));
						}
					}
					
					//inserting pages in the database
					$ActionNeeded = minimalUpdatePages($pages, $pagesInDatabase);
					
					//creation of new pages
					$anOtherCounter = 0;
					while($anOtherCounter < count($actionNeeded[0])){
						$url = $actionNeeded[0][$anOtherCounter][0];
						$priority = $actionNeeded[0][$anOtherCounter][1];
						$sql = "INSERT INTO pages (site, url, priority) VALUES ('$counter', '$url', '$priority')";
						$conn->query($sql);
						$anOtherCounter++;
					}
					
					//priority update
					$anOtherCounter = 0;
					while($anOtherCounter < count($actionNeeded[1])){
						$id = $actionNeeded[1][$anOtherCounter][0];
						$priority = $id = $actionNeeded[1][$anOtherCounter][1];
						$sql = "UPDATE pages SET priority = '$priority' WHERE id = '$id'";
						$conn->query($sql);
						$anOtherCounter++;
					}
				}
				
				//insertion in the database of sites linked internally
				$anOtherCounter = 0;
				while($anOtherCounter < count($otherSites)){
					//check if the site is already present in the database
					$url = $otherSites[$anOtherCounter];
					$query = mysqli_query($conn, "SELECT id FROM sites WHERE url = '$url'");
					$fromObj = mysqli_fetch_assoc($query);
					if(@count($fromObj) == 0){
						//add the site in the database
						$sql = "INSERT INTO sites (url) VALUES ('$url')";
						$conn->query($sql);
					}
					$anOtherCounter++;
				}
				
				//insertion in the database of  images linked internally
				$anOtherCounter = 0;
				while($anOtherCounter < count($images)){
					//check if the site is already present in the database
					$url = $images[$anOtherCounter];
					$query = mysqli_query($conn, "SELECT id FROM images WHERE url = '$url'");
					$fromObj = mysqli_fetch_assoc($query);
					if(@count($fromObj) == 0){
						//add the site in the database
						$sql = "INSERT INTO images (site, url) VALUES ('$counter' '$url')";
						$conn->query($sql);
					}
					$anOtherCounter++;
				}
				
				//for small sites with only one page:
				if((count($pagesInDatabase) == 0) AND (count($actionNeeded[0]) == 0)){
					//add the index page in the database
					$domain = $domain[0];
					$sql = "INSERT INTO pages (site, url, priority) VALUES ('$counter', '$domain', 'NULL')";
					$conn->query($sql);
				}
			} else {
				//cancellation of the site because it does not exist
				$sql = "DELETE FROM sites WHERE id = '$counter'";
				$conn->query($sql);
			}
			
			//counting how many sites are present in the database 
			$query = mysqli_query($conn, "SELECT MAX(id) AS maxId FROM sites");
			$fromObj = @mysqli_fetch_array($query);
			$maxId = $fromObj["maxId"];
			
			//move to the next site 
			$counter++;
		}
	}
?>
